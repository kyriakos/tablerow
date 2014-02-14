<?php

class TableRow {
	
	protected $_table, $_skip = array ('id' ), $loaded = false, $loadJoins = false;
	public $id;
	protected $_foreignTables = array (), $_containing = array ();

	function getTableName() {
		return $this->_table;
	}

    static function getTableRow($where, $class = null, $debug = false)
    {
        if ($class == null) $class = get_called_class();
        $c = new $class();
        $table = $c->getTableName();

        $r = U::query("select id from $table where $where", $debug);

        $out = null;
        if (mysql_num_rows($r) == 1) {
            $d = mysql_fetch_row($r);
            $out = new $class($d[0]);
        }

        return $out;

    }


    static function entryExists($id) {
        $id = (int)$id;
        $class = get_called_class();
        $c = new $class($id);
        return $c->isLoaded();
    }

    static function getTableRowList($where, $class = null, $debug = false)
    {
        if ($class == null) $class = get_called_class();
        $c = new $class();
        $table = $c->getTableName();


        $r = U::query("select id from $table where $where", $debug);

        $c = mysql_num_rows($r);

        $out = array();
        for ($i = 0; $i < $c; $i++) {
            $d = mysql_fetch_row($r);
            $out[$d[0]] = new $class($d[0]);
        }
        return $out;
    }


    function __construct($id = null, $loadJoins = false) {
		if ($id != null) {
			$this->id = ( int ) $id;
			$r = U::query ( "select * from `$this->_table` where id = '$this->id'", false );
			if (mysql_num_rows ( $r ) == 1) {
				U::assignClassVariables ( mysql_fetch_assoc ( $r ), $this );
				$this->loaded = true;
			} else {
				$this->loaded = false;
			}
		
		}
	
	}

	function propagate() {
		// if ( ($this->loaded) && ($this->loadJoins) ) $this->loadJoins();
		if (($this->loaded)) {
			$this->loadJoins ();
			$this->loadContainers ();
		}
	}

	function isLoaded() {
		return ($this->loaded);
	}

	function contains($class, $where) {
		$this->_containing [$class] = $where;
	}

	/**
	 *
	 * @param string $foreignkey
	 *        	The name of the column in this table that will matched with
	 *        	the foreign table's primary key
	 * @param string $class
	 *        	The class that represents the foreign table row
	 */
	
	function joinTable($class, $foreignkey, $where = '') {
		$this->_foreignTables [$class] = array ($class, $foreignkey, $where );
	}

	private function joinTableQuery($id, $table, $key, $where) {
		$q = "select " . $this->_table . ".id from " . $this->_table . ',' . $table . ' where 1 ';
		if ($where != '')
			$q .= ' and ' . $where;
		if ($key != '') {
			$q .= ' and ' . $this->_table . '.' . $key . ' = ' . $id;
			$q .= ' and ' . $this->_table . '.' . $key . ' = ' . $table . '.id';
		}
		return U::query ( $q, false );
	}

	private function containsQuery($where) {
		$q = "select * from $this->_table where " . $where;
		return U::query ( $q, false );
	}

	function loadContainers() {
		// echo 'loading containers for '.get_class($this).'<br>';
		foreach ( $this->_containing as $class => $where ) {
			$o = new $class ();
			$r = $o->containsQuery ( $where );
			
			$c = mysql_num_rows ( $r );
			$ids = array ();
			for($i = 0; $i < $c; $i ++) {
				$d = mysql_fetch_row ( $r );
				$ids [] = $d [0];
			}
			
			if (strpos($class,'\\')===FALSE) $classname = $class; else $classname = end(explode('\\',$class));
			$this->$classname = new TableRowList ( $class, $ids );
			$this->_skip [] = $classname;
		}
	
	}

	function loadJoins() {
		// echo 'loading joins for '.get_class($this).'<br>';
		foreach ( $this->_foreignTables as $table ) {
			$class = $table [0];
			$o = new $class ();
			$r = $o->joinTableQuery ( $this->id, $this->_table, $table [1], $table [2] );
			$c = mysql_num_rows ( $r );
			$ids = array ();
			for($i = 0; $i < $c; $i ++) {
				$d = mysql_fetch_row ( $r );
				$ids [] = $d [0];
			}
			
			if (strpos($class,'\\')===FALSE) $classname = $class; else $classname = end(explode('\\',$class));
			$this->$classname = new TableRowList ( $class, $ids );
			$this->_skip [] = $classname;
		}
	}

	function deleteRow() {
		if ($this->id != null) {
			$r = U::query ( "delete from `$this->_table` where id = '$this->id'", false );
		}
	}

	function save($debug=false) {
		if ($this->id == null) {
			$insert = U::buildInsertFields ( $this, $this->_skip );
			$values = U::buildValueFields ( $this, $this->_skip );
			$q = "INSERT INTO `$this->_table` (" . $insert . ") values (" . $values . ")";
			
			$r = U::query ( $q, $debug);
			$error = mysql_errno ();
			if ($error == 1062) {
				return false;
			} else {
				$this->id = mysql_insert_id ();
				return true;
			}
		
		} else {
			
			$q = 'update `' . $this->_table . '` set ' . U::buildUpdateSets ( $this, $this->_skip ) . ' where id = ' . $this->id;
			$r = U::query ( $q, $debug );
			return true;
		
		}
	}

	function getFieldNames() {
		$r = U::query ( "SELECT * FROM  " . $this->_table . " LIMIT 0 , 1" );
		if (mysql_num_rows ( $r ) > 0) {
			$l = mysql_fetch_assoc ( $r );
			$arr = array ();
			foreach ( $l as $k => $v ) {
				$arr [] = $k;
			}
			return $arr;
		} else
			return false;
	}

	function selectAll() {
		$r = U::query ( 'SELECT id from ' . $this->_table . ' WHERE 1' );
		$ret = array ();
		$class = get_class ( $this );
		while ( $l = mysql_fetch_assoc ( $r ) ) {
			$ar = new $class ( $l ['id'] );
			$ret [$l ['id'] ] = $ar;
		}
		return $ret;
	}

}

// --------------------------------------------------------------------------------------------
class TableRowList implements ArrayAccess, Iterator,Countable {
	private $arr = array (), $class = '';
	private $position = 0;

	function __construct($class, $ids) {
		$this->class = $class;
		$this->arr = $ids;
		$this->position = 0;
	}

	// ---- COUNTABLE -----------------------
	public function count() {
		return count($this->arr);
	}
	
	// ---- ARRAY ACCESS -----------------------
	
	public function offsetExists($offset) {
		return (isset ( $this->arr [$offset] ));
	}

	public function offsetGet($offset) {
		
		if (! is_object ( $this->arr [$offset] )) { // load from database
			$this->arr [$offset] = new $this->class ( $this->arr [$offset] );
		}
		return $this->arr [$offset];
	
	}

	public function offsetSet($offset, $value) {
		$this->arr [$offset] = $value;
	}

	public function offsetUnset($offset) {
		unset ( $this->arr [$offset] );
	}
	
	// ---- ITERATOR --------------------------
	
	function rewind() {
		$this->position = 0;
	}

	function current() {
		return $this->offsetGet ( $this->position );
	}

	function key() {
		return $this->position;
	}

	function next() {
		++ $this->position;
	}

	function valid() {
		return isset ( $this->arr [$this->position] );
	}
}

// --------------------------------------------------------------------------------------------
class TableList {
	protected $_table, $_className;

	function __construct($table, $class) {
		$this->_table = $table;
		$this->_className = $class;
	}

	function selectAll() {
		$r = U::query ( 'SELECT * from ' . $this->_table . ' WHERE 1' );
		return $r;
	}


}

?>