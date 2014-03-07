<?php
include "TableRow.php";
use Brainvial\BVFrame;

$db = new mysqli('localhost', 'root', '', 'tablerow_tests');


BVFrame\TableRow::$db = $db;

class U
{
    static function query($q, $debug = false)
    {
        $db = BVFrame\TableRow::$db;

        $res = $db->query($q, MYSQLI_STORE_RESULT);
        if ($debug) {
            echo $q;
            if ($db->errno != 0) print_r($db->error_list); else echo '<b>[SUCCESS]</b>';
        }
        return $res;
    }
}