<?
use kyriakos\TableRow\TableRow;

$dir = getcwd();
include $dir . '/site/config.php';
require "vendor/autoload.php";

$schema = file_get_contents( "schema.json" );

$schema = json_decode( $schema );

$config = $cfg[ $cfg['test'] ? 'testdb' : 'db' ];

if ( isset( $schema->modelPath ) ) {
	$modelPath = includeTrailingCharacter( $schema->modelPath, "/" );
} else {
	$modelPath = $dir . '/models/';
}

TableRow::connectDB( $config );


if ( ! isset( $schema->tables ) ) {
	echo '$tables undefined';
	die( 0 );
}


foreach ( $schema->tables as $className => $settings ) {
	makeClass( $settings, $className );
}

function processTable( $settings, $className ) {
	$table = $settings->table;
	global $db;
	$r = TableRow::query( "show full columns from $table", false );
	if ( TableRow::$db->errno == 0 ) {
		echo '<' . '? ' . PHP_EOL . 'use kyriakos\TableRow\TableRow;' . PHP_EOL . PHP_EOL;
		outputProperties( $r, $className );
		?>

		namespace App\Models;

		class <?= $className ?> extends TableRow {

		static $_table = '<?= $table; ?>';
		public $id = null;
		<?

		outputPropertyArray( $r );

		//outputSelect( $className );
		outputRelationMethods( $settings, $className );

		echo PHP_EOL . '/**[USER_CODE]*/' . PHP_EOL . PHP_EOL . '/**[/USER_CODE]*/' . PHP_EOL;

		echo PHP_EOL . '}';

		return true;
	} else {
		return false;
	}
}


function findBetween( $haystack, $before, $after ) {
	$startsAt = strpos( $haystack, $before ) + strlen( $before );
	$endsAt = strpos( $haystack, $after, $startsAt );

	return substr( $haystack, $startsAt, $endsAt - $startsAt );
}

function parseRelation( $rel ) {
	$out = new stdClass();
	$out->class = $rel['class'];
	$parts = explode( ':', $rel['relation'] );

	$out->multiplicity = $parts[1];
	$out->methodName = $parts[2];

	$parts = explode( '.', $parts[0] );

	$out->property = $parts[1];
	$out->foreignClass = $parts[0];

	return $out;
}

function outputRelationMethods( $settings, $className ) {

	if ( isset( $settings->relations ) ) {
		foreach ( $settings->relations as $rel ) {
			//$r = parseRelation( $rel );

			if ( isset( $rel->intermediate ) ) {
				// using intermediate table join
				?>
				/**
				* @param null|string $query
				* @param array $values
				*
				* @return <?= $rel->class ?>[]
				*/
				public function <?= $rel->method ?>( $query = null, $values = [ ],$debug = false ) {

				return TableRow::selectIntermediate( $this, $query, $values, "<?= $className ?>", "<?= $rel->class ?>", "<?= $rel->intermediate ?>", "<?= $rel->ownerKey ?>", "<?= $rel->foreignKey ?>", $debug );


				}
			<?
			} else {
				// using direct join
				?>

				public function <?= $rel->method ?> ($where = null,$values = null,$debug = null) {
				return <?= $rel->class ?>::preSelect('<?= $rel->foreignKey ?>',$this->id,$where,$values,$debug);
				}
			<?
			}
		}
	}

}

function outputSelect( $classname ) {
	?>

	/**
	* @param string $where
	* @param array|null $values
	* @param bool $debug
	* @return <?= $classname ?>[]
	*/
	public static function select($where = null, $values = null, $debug = false, $lazy = false)
	{
	return parent::select($where, $values, $debug, $lazy);
	}
<?
}

function outputPropertyArray( $r ) {

	echo PHP_EOL . "\t" . 'protected $_properties = [' . PHP_EOL;
	foreach ( $r as $d ) {
		if ( $d['Field'] != 'id' ) {
			echo "\t\t'" . $d['Field'] . "' => " . field( $d ) . ", " . PHP_EOL;
		}
	}
	echo "\t];" . PHP_EOL;
}

function getFieldCommentString( $out ) {
	$s = ', "hasRelation"=> ' . $out['hasRelation'];
	if ( $out['hasRelation'] == 'true' ) {
		$s .= ', "relatedClass" => "' . $out['relatedClass'] . '"';
		$s .= ', "relatedProperty" => "' . $out['relatedProperty'] . '"';
	}

	return $s;
}

function parseFieldComment( $c ) {
	$out = [ 'hasRelation' => 'false' ];
	$c = trim( $c );

	if ( strlen( trim( $c ) ) > 3 ) {
		if ( substr( $c, 0, 3 ) == 'fk:' ) {
			$class = explode( '.', substr( $c, 3 ) );
			$out['hasRelation'] = 'true';
			$out['relatedProperty'] = $class[1];
			$out['relatedClass'] = $class[0];
		}
	}

	return $out;
}

function field( $d ) {

	$type = getPHPType( $d['Type'] );
	$out = parseFieldComment( $d['Comment'] );

	$extras = getFieldCommentString( $out );

	if ( $out['hasRelation'] == 'true' ) {
		$type = $out['relatedClass'];
	}
	if ( $d['Default'] == null ) {

		switch ( $type ) {
			case 'int':
				$default = '0';
				break;
			case 'string':
				$default = "''";
				break;
			case 'Date':
				$default = "'0000-00-00 00:00:00'";
				break;
			default:
				$default = "''";
				break;
		}
	} else {
		$default = '"' . $d['Default'] . '"';
	}


	return '[' . '"value" => null, "updated" => false, "default" => ' . $default . ', "type"=>"' . $type . '"' . $extras . ']';
}

function outputProperties( $r, $classname ) {

	echo '/**' . PHP_EOL;
	foreach ( $r as $d ) {
		if ( $d['Field'] != 'id' ) {
			$fc = parseFieldComment( $d['Comment'] );
			$type = getPHPType( $d['Type'] );
			if ( $fc['hasRelation'] == 'true' ) {
				$type = $fc['relatedClass'];
			}
			echo '* @property ' . $type . ' $' . $d['Field'] . PHP_EOL;
		}
	}

	echo '* @method static ' . $classname . ' selectOne(string $where = null, array $values = null, bool $debug = false, bool $lazy = false) fetches just one ' . $classname . ' instance' . PHP_EOL;
	echo '* @method static ' . $classname . '[] select(string $where = null, array $values = null, bool $debug = false, bool $lazy = false) fetches a list of ' . $classname . ' instances' . PHP_EOL;
	echo '**/' . PHP_EOL . PHP_EOL;
}

function getPHPType( $mysqlType ) {
	if ( substr( $mysqlType, 0, 3 ) == 'int' ) {
		return 'int';
	}
	if ( substr( $mysqlType, 0, 6 ) == 'double' ) {
		return 'float';
	}
	if ( substr( $mysqlType, 0, 5 ) == 'float' ) {
		return 'float';
	}
	if ( substr( $mysqlType, 0, 7 ) == 'tinyint' ) {
		return 'int';
	}
	if ( substr( $mysqlType, 0, 7 ) == 'varchar' ) {
		return 'string';
	}
	if ( substr( $mysqlType, 0, 4 ) == 'text' ) {
		return 'string';
	}
	if ( substr( $mysqlType, 0, 4 ) == 'enum' ) {
		return 'string';
	}
	if ( substr( $mysqlType, 0, 4 ) == 'char' ) {
		return 'string';
	}
	if ( substr( $mysqlType, 0, 10 ) == 'mediumtext' ) {
		return 'string';
	}
	if ( substr( $mysqlType, 0, 8 ) == 'longtext' ) {
		return 'string';
	}
	if ( substr( $mysqlType, 0, 8 ) == 'datetime' ) {
		return 'DateTime';
	}
	if ( substr( $mysqlType, 0, 9 ) == 'timestamp' ) {
		return 'DateTime';
	}
}

function fetchCustomCode( $filename ) {
	$code = file_get_contents( $filename );
	$startTag = '/**[USER_CODE]*/';
	$endTag = '/**[/USER_CODE]*/';

	return findBetween( $code, $startTag, $endTag );
}

function makeClass( $settings, $classname = '' ) {


	echo 'Generating Class ' . $classname . PHP_EOL;

	ob_start();
	if ( processTable( $settings, $classname ) ) {

		global $modelPath;

		$filename = $modelPath . $classname . '.php';

		if ( file_exists( $filename ) ) {
			$customCode = fetchCustomCode( $filename );
		} else {
			$customCode = '';
		}

		$data = ob_get_clean();

		$data = str_replace( '/**[USER_CODE]*/', '/**[USER_CODE]*/' . $customCode, $data );

		if ( strlen( $customCode ) > 0 ) {
			$data = str_replace( PHP_EOL . '/**[USER_CODE]*/', '/**[USER_CODE]*/',$data );
		}

		file_put_contents( $filename, $data );
//	} else {
//		echo '<em>' . $modelPath . $classname . '.php already exists. Will not overwrite.</em><br><br>';
//	}
		// echo nl2br(htmlspecialchars($data));
	} else {
		echo "Table:" . $settings->table . " was not found. Halting process.";
		die( 0 );

	}

}

function includeTrailingCharacter( $string, $character ) {
	if ( strlen( $string ) > 0 ) {
		if ( substr( $string, - 1 ) !== $character ) {
			return $string . $character;
		} else {
			return $string;
		}
	} else {
		return $character;
	}
}