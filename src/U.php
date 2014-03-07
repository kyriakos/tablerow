<?php
/**
 * Created by PhpStorm.
 * User: Kyriakos
 * Date: 07/03/14
 * Time: 11:23
 */
namespace Brainvial;
class U
{

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

}