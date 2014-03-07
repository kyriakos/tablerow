<?php
namespace Brainvial;

class TableRow
{

    protected $_skip = array('id'), $loaded = false, $_properties = [], $lazy = false;


    /**
     * @var mysqli|null
     */
    static $_table = null, $db = null;
    static $_types = ['int' => 'i', 'float' => 'd', 'string' => 's', 'blob' => 'b', 'Date' => 's'];

    static function query($q, $debug = false)
    {
        $db = TableRow::$db;

        $res = $db->query($q, MYSQLI_STORE_RESULT);
        if ($debug) {
            echo $q;
            if ($db->errno != 0) print_r($db->error_list); else echo '<b>[SUCCESS]</b>';
        }
        return $res;
    }


    static function connectDB($config) {
        $db = new \mysqli($config['host'], $config['user'], $config['pass'], $config['name']);
        static::$db = $db;
    }

    static function getTableName()
    {
        return static::$_table;
    }


    static function entryExists($id)
    {
        $id = (int)$id;
        $class = get_called_class();
        $c = new $class($id);
        return $c->isLoaded();
    }


    private static function getMYSQLiValueTypes($values)
    {
        $s = '';
        foreach ($values as $v) {
            if (is_int($v)) $s .= 'i';
            if (is_string($v)) $s .= 's';
            if ((is_double($v)) || (is_float($v))) $s .= 'f';
            if (is_object($v)) {
                if (get_class($v) == 'DateTime') {
                    $s .= 's';
                } else {
                    echo 'getMYSQLiValueTypes:unknown type';
                }
            }
        }

        return $s;
    }


    static function preSelect($fieldName, $id, $where = null, $values = null, $debug = false)
    {
        if (strlen(trim($where)) > 0) {
            $add = 'and ' . $where;
        } else {
            $add = '';
        }

        if (!(is_array($values) || is_object($values))) {

            $where = '`' . $fieldName . '` = ' . $id . ' ' . $add;

        } else {

            $where = '`' . $fieldName . '` = ? ' . $add;
            $values = array_merge([(int)$id], $values);
        }

        var_dump($where);
        return static::select($where, $values, $debug);

    }

    public static function select($where = null, $values = null, $debug = false)
    {

        // select all in case no where filter is specified
        if ($where == null) $where = '1';
        $useBind = false;


        if ($values === true) $debug = true;
        if (($values === null) && ($debug === false)) {
            $useBind = false;
        } else
            if (is_object($values)) {
                $values = get_object_vars($values);
            }

        // if we got an array of values then we should use a prepared statement
        if (is_array($values)) {
            $useBind = true;
        }


        $class = get_called_class();
        $table = $class::getTableName();

        if ($useBind) { // use a prepared statement and bind params

            $s = TableRow::$db->stmt_init();
            $q = "select id from $table where " . $where;
            $prepareResult = $s->prepare($q);

            if (!$prepareResult) {
                if ($debug) echo 'Syntax Error. Could not prepare statement for ' . $q;
            } else {
                $types = TableRow::getMYSQLiValueTypes($values);


                $bindResult = call_user_func_array([$s, 'bind_param'], TableRow::refValues(array_merge([$types], $values)));

                if ($bindResult) {

                    if ($s->execute()) {
                        $r = $s->get_result();
                    } else {
                        if ($debug) echo 'Error Executing query:' . $q . ' types:' . $types . ' values:' . print_r($values, true);
                    }

                } else {
                    if ($debug) echo 'Error Binding query:' . $q . ' types:' . $types . ' values:' . print_r($values, true);
                }
            }
        } else { // run string query the old fashioned way
            $r = TableRow::query("select id from $table where $where", $debug);
        }
        $out = [];

        if (get_class($r) == 'mysqli_result') {
            foreach ($r as $d) {
                $out[$d['id']] = new $class($d['id'], true);
            }
        }

        return $out;
    }


    static function getTableRowList($where, $class = null, $debug = false)
    {
        if ($class == null) $class = get_called_class();
        $c = new $class();
        $table = $c->getTableName();


        $r = TableRow::query("select id from $table where $where", $debug);

        $c = mysql_num_rows($r);

        $out = array();
        for ($i = 0; $i < $c; $i++) {
            $d = mysql_fetch_row($r);
            $out[$d[0]] = new $class($d[0]);
        }
        return $out;
    }


    function __set($name, $val)
    {
        if (isset($this->_properties[$name])) {
            $this->lazyLoad();

            if (($this->_properties[$name]['hasRelation']) && (!is_object($val)) && ((int)$val > 0)) {
                $class = $this->_properties[$name]['relatedClass'];
                $this->_properties[$name]['value'] = new $class($val, true);
            } else {
                $this->_properties[$name]['value'] = $val;
            }
            $this->_properties[$name]['updated'] = true;
        } else $this->$name = $val;

    }

    function __get($name)
    {
        if (isset($this->_properties[$name])) {
            $this->lazyLoad();
            return $this->_properties[$name]['value'];
        } else return null;
    }

    protected function loadObject($id)
    {
        $r = TableRow::$db->query("select * from `" . static::$_table . "` where id = '" . $id . "'", MYSQLI_STORE_RESULT);

        if ($r) {
            $d = $r->fetch_assoc();
            if ($d != null) {
                foreach ($this->_properties as $field => $pro)
                    $this->_properties[$field] = TableRow::TR_setValue($pro, $d[$field]);
                $this->loaded = true;
                $this->id = $id;
            }
        }

    }

    protected function lazyLoad()
    {
        if (($this->lazy) && ($this->id != null)) {
            $this->loadObject($this->id);
            $this->lazy = false;
        }
    }

    function __construct($id = null, $lazy = false)
    {
        if (($id != null) && ((int)$id != 0)) {
            $id = ( int )$id;
            $this->loaded = false;

            if ($lazy) {
                $this->lazy = true;
                $this->id = $id;
                $this->loaded = true;
            } else {
                $this->loadObject($id);
            }
        }
    }

    function __toString()
    {
        $out = [];
        $this->lazyLoad();
        foreach ($this->_properties as $k => $p) $out[$k] = TableRow::TR_getValue($p);

        return print_r($out, true);

    }


    function isLoaded()
    {
        return ($this->loaded);
    }


    function deleteRow()
    {
        if ($this->id != null) {
            $r = TableRow::query("delete from `$this->_table` where id = '$this->id'", false);
        }
    }

    protected function buildInsertFields()
    {
        return '(' . implode(', ', array_keys($this->_properties)) . ')';
    }

    protected function buildUpdateQuery()
    {
        $s = [];
        $values = [];
        $count = 0;
        $types = '';
        foreach ($this->_properties as $k => $p) {
            if ($p['updated']) {
                $count++;
                $s[] = '`' . $k . '` = ?';
                $types .= TableRow::$_types[$p['type']];
                $values[$k] = TableRow::TR_getValue($p);
            }
        }
        $s = implode(', ', $s);
        return ['sets' => $s, 'values' => $values, 'count' => $count, 'types' => $types];
    }


    protected static function TR_getValue($prop)
    {
        if ($prop['value'] == null) return $prop['default']; else {
            if ($prop['hasRelation']) { // in case of a related object
                if (is_object($prop['value'])) {
                    return $prop['value']->id; // return its id
                } else return $prop['value'];
            } else
                if ($prop['type'] == 'Date') return $prop['value']->format('Y-m-d H:i:s'); else {
                    return $prop['value'];
                }

        }
    }

    protected static function TR_setValue($prop, $value)
    {
        $type = $prop['type'];
        if (($type == 'int') || ($type == 'float') || ($type == 'string')) {
            $prop['value'] = $value;
        } else
            if ($type == 'Date') {
                $prop['value'] = new \DateTime($value);
            } else {
                if ($prop['hasRelation']) {
                    $class = $prop['relatedClass'];

                    if ((is_string($value)) && ((int)$value > 0)) {
                        $value = new $class($value, true);
                    }
                    $prop['value'] = $value;
                } else {
                    $prop['value'] = $value;
                }

            }

        return $prop;

    }


    protected static function refValues($arr)
    {
        $refs = array();
        foreach ($arr as $key => $value)
            $refs[$key] = & $arr[$key];
        return $refs;
    }

    function save($debug = false)
    {
        if (!$this->lazy) {

            $s = TableRow::$db->stmt_init();

            if ($this->id == null) {


                $q = 'INSERT INTO ' . static::$_table . ' ' . $this->buildInsertFields() . ' VALUES (';
                $q .= str_repeat('?, ', count($this->_properties));
                $q = substr($q, 0, strlen($q) - 2) . ');';
                if ($debug) echo '<br><b>' . $q . '</b>';
                if ($s->prepare($q)) {

                    $types = '';
                    $values = [];

                    foreach ($this->_properties as $field => $prop) {
                        if ($prop['hasRelation']) $types .= 'i';
                        else $types .= TableRow::$_types[$prop['type']];
                        $values[] = TableRow::TR_getValue($prop);
                    }

                    call_user_func_array([$s, 'bind_param'], TableRow::refValues(array_merge([$types], $values)));

                    $res = $s->execute();

                    if ($res) $this->id = TableRow::$db->insert_id;
                    if ($debug) echo '<br><em>' . TableRow::$db->error . '</em>';
                    return $res;
                } else {
                    // @TODO log error with query here
                }

            } else {

                $parts = $this->buildUpdateQuery();

                if ($parts['count'] != 0) {
                    $q = "UPDATE `" . static::$_table . "` set " . $parts['sets'] . " WHERE id = '" . $this->id . "';";

                    if ($s->prepare($q)) {
                        var_dump($parts);
                        if (call_user_func_array([$s, 'bind_param'], TableRow::refValues(array_merge([$parts['types']], $parts['values'])))) {
                            $res = $s->execute();

                            if ($debug) echo '<br><em>' . TableRow::$db->error . '</em>';
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



}

