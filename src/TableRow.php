<?php
namespace Kyriakos\TableRow;

use Brainvial\TableRow\TableRowIterator;
use Brainvial\TableRow\Point;
use Brainvial\TableRow\Polygon;

use mysqli;

class TableRow {

	/**
	 * @var \mysqli $db
	 */
	static $db;

	/**
	 * @var Callable $logger ;
	 */
	static $logger;

	static $_table = null;
	static $_types = [
		'int'      => 'i',
		'float'    => 'd',
		'string'   => 's',
		'blob'     => 'b',
		'DateTime' => 's',
		'Polygon'  => 's',
		'Point'    => 's',
		'char'     => 's',
		'enum'     => 's'
	];
	protected $_skip = array( 'id' ), $loaded = false, $_properties = [ ], $lazy = false;


	static function toLog( $entryText ) {
		if ( is_callable( TableRow::$logger ) ) {
			call_user_func( TableRow::$logger, $entryText );
		}
	}

	function __construct( $id = null, $lazy = false ) {
		if ( ( $id != null ) && ( (int) $id != 0 ) ) {
			$id = ( int ) $id;
			$this->loaded = false;

			if ( $lazy ) {
				$this->lazy = true;
				$this->id = $id;
				$this->loaded = false;
			} else {
				$this->loadObject( $id );
			}
		}
	}

	protected function loadObject( $id ) {
		$decodeFields = '';


		foreach ( $this->_properties as $field => $pro ) {
			$type = $pro['type'];
			if ( ( $type == 'Polygon' ) || ( $type == 'Point' ) ) {
				$decodeFields = 'asText(`' . $field . '`) as  trdec_' . $field;
			}
		}

		if ( strlen( $decodeFields ) > 0 ) {
			$decodeFields = ',' . $decodeFields;
		}

		$r = TableRow::$db->query( "select * $decodeFields from `" . static::$_table . "` where id = '" . $id . "'", MYSQLI_STORE_RESULT );

		if ( $r ) {
			$d = $r->fetch_assoc();
			if ( $d != null ) {
				foreach ( $this->_properties as $field => $pro ) {
					$v = $d[ $field ];
					if ( $pro['type'] == 'Point' ) {
						$v = $d[ 'trdec_' . $field ];
					}

					if ( $pro['type'] == 'Polygon' ) {
						$v = $d[ 'trdec_' . $field ];
					}
					$this->_properties[ $field ] = TableRow::TR_setValue( $pro, $v );
				}
				$this->loaded = true;
				$this->id = $id;
			}
		}

	}

	protected static function TR_setValue( $prop, $value, $field = null ) {
		$type = $prop['type'];
		if ( ( $type == 'int' ) || ( $type == 'float' ) || ( $type == 'string' ) ) {
			$prop['value'] = $value;
		} else if ( $type == 'DateTime' ) {
			$prop['value'] = new \DateTime( $value );
		} else if ( $type == 'Point' ) {
			$prop['value'] = Point::fromString( $value );
		} else if ( $type == 'Polygon' ) {
			$prop['value'] = Polygon::fromString( $value );
		} else {
			if ( $prop['hasRelation'] ) {
				$class = $prop['relatedClass'];

				if ( ( is_string( $value ) ) && ( (int) $value > 0 ) ) {
					$value = new $class( $value, true );
				}
				$prop['value'] = $value;
			} else {
				$prop['value'] = $value;
			}

		}

		return $prop;

	}

	static function sanitize( $s ) {
		return static::$db->real_escape_string( $s );
	}

	static function count( $q = null, $values = null, $debug = false ) {
		if ( $q == null ) {
			$q = 1;
		}

		$query = "SELECT count(id) AS totalcols FROM " . static::$_table . ' WHERE ' . $q;
		if ( is_array( $values ) ) {
			static::replaceModelsWithIDs( $values );
			$res = TableRow::preparedQuery( $query, $values, $debug );
		} else {
			if ( $values === true ) {
				$debug = true;
			}
			$res = TableRow::$db->query( $query );
			if ( $debug ) {
				TableRow::toLog( $query);
			}
		}


		return $res->fetch_array()[0];
	}

	private static function replaceModelsWithIDs( &$values ) {
		foreach ( $values as $key => $val ) {
			if ( is_object( $val ) ) {

				if ( is_subclass_of( $val, 'Brainvial\TableRow\TableRow' ) === false ) {
					throw new \Exception( 'TableRow: Bind Value an object but not subclass of Brainvial\TableRow\TableRow' );
				} else {
					$values[ $key ] = $val->id;
				}
			} else {
				$values[ $key ] = $val;
			}
		}
	}

	/**
	 * @param $q
	 * @param $values
	 * @param bool $debug
	 *
	 * @return bool|\mysqli_result|null
	 */
	static function preparedQuery( $q, $values, $debug = false ) {
		$s = TableRow::$db->stmt_init();

		if ( $s->prepare( $q ) ) {

			$types = TableRow::getMYSQLiValueTypes( $values );
			$bindResult = call_user_func_array( [
				$s,
				'bind_param'
			], TableRow::refValues( array_merge( [ $types ], $values ) ) );
			if ( $bindResult ) {
				if ( $s->execute() ) {
					return $s->get_result();
				} else {
					if ( $debug ) {
						TableRow::toLog('Error Executing query:' . $q . ' types:' . $types . ' values:' . print_r( $values, true ));
					}

					return null;
				}

			} else {
				if ( $debug ) {
					TableRow::toLog( 'Error Binding query:' . $q . ' types:' . $types . ' values:' . print_r( $values, true ) );

				}

				return null;
			}
		} else {
			// @TODO log error
			return null;
		}


	}

	private static function getMYSQLiValueTypes( $values ) {
		$s = '';
		foreach ( $values as $v ) {
			if ( is_int( $v ) ) {
				$s .= 'i';
			}
			if ( is_string( $v ) ) {
				$s .= 's';
			}
			if ( ( is_double( $v ) ) || ( is_float( $v ) ) ) {
				$s .= 'f';
			}
			if ( is_object( $v ) ) {
				$class = get_class( $v );
				if ( ( $class == 'DateTime' ) || ( $class == 'Polygon' ) || ( $class == 'Point' ) ) {
					$s .= 's';
				} else {
					TableRow::toLog( 'getMYSQLiValueTypes:unknown type');
				}
			}
		}

		return $s;
	}

	protected static function refValues( $arr ) {
		$refs = array();
		foreach ( $arr as $key => $value ) {
			$refs[ $key ] = &$arr[ $key ];
		}

		return $refs;
	}

	static function connectDB( $config ) {
		$db = new \mysqli( $config['host'], $config['user'], $config['pass'], $config['name'] );
		$db->set_charset(
			'"utf8"'
		);

		$db->query( "SET NAMES 'UTF8';" );

		static::$db = $db;
	}

	/**
	 * @return string
	 */
	static function getTableName() {
		return static::$_table;
	}

	/**
	 * @param $id
	 *
	 * @return boolean
	 */
	static function entryExists( $id ) {
		$id = (int) $id;
		$class = get_called_class();
		$c = new $class( $id );

		return $c->isLoaded();
	}

	public static function selectOne( $where = null, $values = null, $debug = false, $lazy = false ) {
		$selected = static::select( $where, $values, $debug, $lazy );
		if ( count( $selected ) == 0 ) {
			return null;
		}

		return $selected->current();
	}

	public static function select( $where = null, $values = null, $debug = false, $lazy = false ) {

		// select all in case no where filter is specified
		if ( $where == null ) {
			$where = '1';
		}
		$useBind = false;


		if ( $values === true ) {
			$lazy = $debug;
			$debug = true;
		}

		if ( ( $values === null ) && ( $debug === false ) ) {
			$useBind = false;
		} else if ( is_object( $values ) ) {
			$values = get_object_vars( $values );
		}

		// if we got an array of values then we should use a prepared statement
		if ( is_array( $values ) ) {
			$useBind = true;
			static::replaceModelsWithIDs( $values );
		}


		$class = get_called_class();
		$table = $class::getTableName();

		if ( $useBind ) { // use a prepared statement and bind params

			$s = TableRow::$db->stmt_init();
			$q = "select id from $table where " . $where;

			$prepareResult = $s->prepare( $q );

			if ( ! $prepareResult ) {
				if ( $debug ) {
					TableRow::toLog( 'Syntax Error. Could not prepare statement for ' . $q . PHP_EOL . TableRow::$db->error);
				}
			} else {
				$types = TableRow::getMYSQLiValueTypes( $values );


				$bindResult = call_user_func_array( [
					$s,
					'bind_param'
				], TableRow::refValues( array_merge( [ $types ], $values ) ) );

				if ( $bindResult ) {

					if ( $s->execute() ) {
						$r = $s->get_result();
					} else {
						if ( $debug ) {
							TableRow::toLog( 'Error Executing query:' . $q . ' types:' . $types . ' values:' . print_r( $values, true ));
						}
					}

				} else {
					if ( $debug ) {
						TableRow::toLog( 'Error Binding query:' . $q . ' types:' . $types . ' values:' . print_r( $values, true ) );

					}
				}
			}
		} else { // run string query the old fashioned way
			$r = TableRow::query( "select id from $table where $where", $debug );
		}
		$out = [ ];
		if ( ! is_object( $r ) ) {
			throw new \Exception( 'Invalid Query ' . static::$db->error );
		}
		if ( get_class( $r ) == 'mysqli_result' ) {

			$tr = new TableRowIterator( $r, $class );

			return $tr;


			/*
						foreach ( $r as $d ) {
							$out[ $d['id'] ] = new $class( $d['id'], true );
						}

			*/
		}

		return $out;
	}

	static function query( $q, $debug = false ) {
		$db = TableRow::$db;

		$res = $db->query( $q, MYSQLI_STORE_RESULT );
		if ( $debug ) {
			TableRow::toLog($q);
			if ( $db->errno != 0 ) {
				TableRow::toLog(print_r( $db->error_list ,true));
			} else {
				TableRow::toLog('[SUCCESS]');
			}
		}

		return $res;
	}

	static function preSelect( $fieldName, $id, $where = null, $values = null, $debug = false ) {
		if ( strlen( trim( $where ) ) > 0 ) {
			$add = 'and ' . $where;
		} else {
			$add = '';
		}

		if ( ! ( is_array( $values ) || is_object( $values ) ) ) {

			$where = '`' . $fieldName . '` = ' . $id . ' ' . $add;

		} else {

			$where = '`' . $fieldName . '` = ? ' . $add;
			$values = array_merge( [ (int) $id ], $values );
		}


		return static::select( $where, $values, $debug );

	}

	/**
	 * @param $_this
	 * @param $query
	 * @param $values
	 * @param $classA
	 * @param $classB
	 * @param $intClass
	 * @param $IDofAinIntermediate
	 * @param $IDofBinIntermediate
	 * @param $debug
	 *
	 * @return \Brainvial\TableRow\TableRowIterator
	 */

	public static function selectIntermediate( $_this, $query, $values, $classA, $classB, $intClass, $IDofAinIntermediate, $IDofBinIntermediate, $debug = false ) {
		$class = new \ReflectionClass( $classA );
		$tableA = $class->getStaticPropertyValue( '_table' );

		$class = new \ReflectionClass( $classB );
		$tableB = $class->getStaticPropertyValue( '_table' );

		$class = new \ReflectionClass( $intClass );
		$intTable = $class->getStaticPropertyValue( '_table' );


		if ( is_null( $query ) ) {
			$q = "select `$IDofBinIntermediate` from `$intTable` where `$IDofAinIntermediate` = ?";
			$values = [ $_this->id ];
		} else {
			$q = "select `$intTable`.`$IDofBinIntermediate` from `$intTable`,`$tableA`,`$tableB` where `$tableB`.id = `$intTable`.`$IDofBinIntermediate` and `$tableA`.id = `$intTable`.`$IDofAinIntermediate` and `$tableA`.id = ? and " . $query;
			if ( is_array( $values ) ) {
				$values = array_merge( [ $_this->id ], $values );
			} else {
				$values = [ $_this->id ];
			}
		}


		$r = TableRow::preparedQuery( $q, $values );

		if ( $debug ) {
			TableRow::toLog($q);
			TableRow::toLog(print_r($values,true));
			TableRow::toLog(TableRow::$db->error);
		}

		return new TableRowIterator( $r, $classB );
	}

	static function getTableRowList( $where, $class = null, $debug = false ) {
		if ( $class == null ) {
			$class = get_called_class();
		}
		$c = new $class();
		$table = $c->getTableName();


		$r = TableRow::query( "select id from $table where $where", $debug );

		$c = mysql_num_rows( $r );

		$out = array();
		for ( $i = 0; $i < $c; $i ++ ) {
			$d = mysql_fetch_row( $r );
			$out[ $d[0] ] = new $class( $d[0] );
		}

		return $out;
	}

	/**
	 * @param string $query The query to call (must select ID only)
	 * @param array $values Values to feed in the prepared query
	 * @param null $resultClass The class the selected id will instantiate
	 * @param bool $debug
	 *
	 * @return array|TableRowIterator
	 */
	public static function queryTableRowList( $query = '', $values = [ ], $resultClass = null, $debug = false ) {


		$useBind = true;
		$lazy = true;

		if ( is_object( $values ) ) {
			$values = get_object_vars( $values );
		}


		$s = TableRow::$db->stmt_init();

		$prepareResult = $s->prepare( $query );

		if ( ! $prepareResult ) {
			if ( $debug ) {
				TableRow::toLog('Syntax Error. Could not prepare statement for ' . $query . PHP_EOL . TableRow::$db->error);
			}
		} else {
			$types = TableRow::getMYSQLiValueTypes( $values );

			$bindResult = call_user_func_array( [
				$s,
				'bind_param'
			], TableRow::refValues( array_merge( [ $types ], $values ) ) );

			if ( $bindResult ) {

				if ( $s->execute() ) {
					$r = $s->get_result();
				} else {
					TableRow::toLog( 'Error Executing query:' . $query . ' types:' . $types . ' values:' . print_r( $values, true ) );
					if ( $debug ) {
						TableRow::toLog('Error Executing query:' . $query . ' types:' . $types . ' values:' . print_r( $values, true ));
					}
				}

			} else {
				TableRow::toLog( 'Error Binding query:' . $query . ' types:' . $types . ' values:' . print_r( $values, true ) );
				if ( $debug ) {
					TableRow::toLog('Error Binding query:' . $query . ' types:' . $types . ' values:' . print_r( $values, true ));
				}
			}
		}

		$out = [ ];

		if ( get_class( $r ) == 'mysqli_result' ) {

			$tr = new TableRowIterator( $r, $resultClass );

			return $tr;
		}

		return $out;
	}

	function __get( $name ) {
		if ( isset( $this->_properties[ $name ] ) ) {
			$this->lazyLoad();

			return $this->_properties[ $name ]['value'];
		} else {
			throw new \Exception( 'Invalid model property "' . $name . '"' );
		}
	}

	function __set( $name, $val ) {
		if ( isset( $this->_properties[ $name ] ) ) {
			$this->lazyLoad();

			if ( ( $this->_properties[ $name ]['hasRelation'] ) && ( ! is_object( $val ) ) && ( (int) $val > 0 ) ) {
				$class = $this->_properties[ $name ]['relatedClass'];
				$this->_properties[ $name ]['value'] = new $class( $val, true );
			} else {
				$this->_properties[ $name ]['value'] = $val;
			}
			$this->_properties[ $name ]['updated'] = true;
		} else {
			$this->$name = $val;
		}

	}

	protected function lazyLoad() {
		if ( ( $this->lazy ) && ( $this->id != null ) ) {
			$this->loadObject( $this->id );
			$this->lazy = false;
		}
	}

	function __toString() {
		$out = [ ];
		$this->lazyLoad();
		foreach ( $this->_properties as $k => $p ) {
			$out[ $k ] = TableRow::TR_getValue( $p );
		}

		return print_r( $out, true );

	}

	protected static function TR_getValue( $prop ) {
		if ( $prop['value'] === null ) {
			return $prop['default'];
		} else {
			if ( $prop['hasRelation'] ) { // in case of a related object
				if ( is_object( $prop['value'] ) ) {
					return $prop['value']->id; // return its id
				} else {
					return $prop['value'];
				}
			} else if ( $prop['type'] == 'DateTime' ) {
				return $prop['value']->format( 'Y-m-d H:i:s' );
			} else if ( ( $prop['type'] == 'Polygon' ) || ( $prop['type'] == 'Point' ) ) {
				return $prop['value']->__toString();
			} else {
				return $prop['value'];
			}

		}
	}

	function isLoaded() {
		$this->lazyLoad();

		return ( $this->loaded );
	}

	function deleteRow() {
		if ( $this->id != null ) {
			$r = TableRow::query( "delete from `" . static::$_table . "` where id = '$this->id'", false );
		}
	}

	/**
	 * Creates a stdClass object with all the instance variables of this model entry.
	 * Use it to return Models to client via JSON etc
	 *
	 * @param bool $includeID Will include the instance ID in the properties of the static
	 * @param bool $fromDB Will reload object from DB before creating static
	 *
	 * @return \stdClass
	 */
	function getStaticObject( $includeID = false, $fromDB = false ) {

		$s = new \stdClass();
		if ( $includeID ) {
			$s->id = $this->id;
		}

		if ( $fromDB ) {
			if ( $this->id != null ) {
				$class = get_class( $this );
				$obj = new $class( $this->id );
				$ref = new \ReflectionClass( $obj );
				$properties = $ref->getProperty( '_properties' );
			} else {
				throw new \Exception( 'ID is null' );
			}
		} else {
			$properties = $this->_properties;
		}


		foreach ( $properties as $k => $prop ) {

			$s->$k = static::TR_getValue( $prop );
		}

		return $s;
	}

	function save( $debug = false ) {
		if ( ! $this->lazy ) {
			$s = TableRow::$db->stmt_init();
			if ( $this->id == null ) {

				$q = 'INSERT INTO ' . static::$_table . ' ' . $this->buildInsertFields() . ' VALUES (';

//				$q .= str_repeat( '?, ', count( $this->_properties ) );

				foreach ( $this->_properties as $prop ) {
					$type = $prop['type'];
					if ( ( $type === 'Point' ) || ( $type === 'Polygon' ) ) {
						$q .= 'GeomFromText(?), ';
					} else {
						$q .= '?, ';
					}
				}

				$q = substr( $q, 0, strlen( $q ) - 2 ) . ');';


				if ( $debug ) {
					TableRow::toLog($q);
				}
				if ( $s->prepare( $q ) ) {

					$types = '';
					$values = [ ];

					foreach ( $this->_properties as $field => $prop ) {
						if ( $prop['hasRelation'] ) {
							$types .= 'i';
						} else {
							$types .= TableRow::$_types[ $prop['type'] ];
						}
						$values[] = TableRow::TR_getValue( $prop );
					}

					if ( $debug ) {
						TableRow::toLog( ' types:' . $types . ' values:' . print_r( $values, true ) );
					}
					call_user_func_array( [
						$s,
						'bind_param'
					], TableRow::refValues( array_merge( [ $types ], $values ) ) );

					$res = $s->execute();
					if ( $res ) {
						$this->id = TableRow::$db->insert_id;
					}
					if ( $debug ) {
						TableRow::toLog( TableRow::$db->error . ' ' . PHP_EOL . TableRow::$db->error);
					}

					return $res;
				} else {
					if ( $debug ) {
						TableRow::toLog('Unable to prepare INSERT query. ' . PHP_EOL . $q . PHP_EOL . TableRow::$db->error);
					}
					// @TODO log error with query here
				}

			} else {

				$parts = $this->buildUpdateQuery();

				if ( $parts['count'] != 0 ) {
					$q = "UPDATE `" . static::$_table . "` set " . $parts['sets'] . " WHERE id = '" . $this->id . "';";

					if ( $s->prepare( $q ) ) {

						if ( call_user_func_array( [
							$s,
							'bind_param'
						], TableRow::refValues( array_merge( [ $parts['types'] ], $parts['values'] ) ) )
						) {
							$res = $s->execute();

							if ( $debug ) {
								TableRow::toLog( TableRow::$db->error );
							}

							return $res;
						} else {
							//  @TODO log error with binding here
						}

					} else {
						//  @TODO log error with query here

						return false;
					}
				}

			}
		}
	}

	protected function buildInsertFields() {
		$quoted = array_map(
			function ( $col ) {
				return '`' . $col . '`';
			}, array_keys( $this->_properties )
		);

		return '(' . implode( ', ', $quoted ) . ')';
	}

	protected function buildUpdateQuery() {
		$s = [ ];
		$values = [ ];
		$count = 0;
		$types = '';
		foreach ( $this->_properties as $k => $p ) {
			if ( $p['updated'] ) {
				$count ++;

				if ( $p['type'] == 'Point' ) {
					$s[] = '`' . $k . '` = GeomFromText(?)';
				} else {
					$s[] = '`' . $k . '` = ?';
				}

				if ( $p['hasRelation'] ) {
					$types .= 'i'; // if its a relation then its type int (for the ID)
				} else { // if not then infer the type.
					$types .= TableRow::$_types[ $p['type'] ];
				}

				$values[ $k ] = TableRow::TR_getValue( $p );
			}
		}
		$s = implode( ', ', $s );

		return [ 'sets' => $s, 'values' => $values, 'count' => $count, 'types' => $types ];
	}

}