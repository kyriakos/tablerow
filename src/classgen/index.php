<?php
include "../connect.php";


if (!isset($modelPath)) {
    echo '$modelPath not set. Terminated';
    die(0);
}

//$modelPath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR;
$tables = array(
    'article' => 'Article',
    'categories' => 'Category',
);


/* Syntax for relations:
 * array('class'=>'[class-which-contains]', 'relation' => '[contained-class-name].[foreign-key-column-name]:[1 or * for quantity]:[method-name]');
*/
$relations = [
    ['class' => 'Category', 'relation' => 'Article.category:*:getArticles']
];


foreach ($tables as $table => $classname) makeClass($table, $classname);

function processTable($table, $className)
{
    global $db;
    $r = U::query("show full columns from $table", false);

    echo '<' . '?';
    outputProperties($r);
    ?>
    use Brainvial\TableRow;

    class <?= $className ?> extends TableRow {

    static $_table = '<?= $table; ?>';
    public $id = null;
    <?

    outputPropertyArray($r);

    outputSelect($className);
    outputRelationMethods($table, $className);
    echo PHP_EOL . '}';

}

function parseRelation($rel)
{
    $out = new stdClass();
    $out->class = $rel['class'];
    $parts = explode(':', $rel['relation']);

    $out->multiplicity = $parts[1];
    $out->methodName = $parts[2];

    $parts = explode('.', $parts[0]);

    $out->property = $parts[1];
    $out->foreignClass = $parts[0];

    return $out;
}

function outputRelationMethods($table, $className)
{

    global $relations;

    foreach ($relations as $rel) {
        if ($rel['class'] == $className) {
            $r = parseRelation($rel);

            ?>

    public function <?= $r->methodName ?> ($where = null,$values = null,$debug = null) {
        return <?=$r->foreignClass ?>::preSelect('<?=$r->property ?>',$this->id,$where,$values,$debug);
    }
        <?
        }
    }
}

function outputSelect($classname)
{
    ?>

    /**
    * @param string $where
    * @param bool $debug
    * @return <?= $classname ?>[]
    */
    public static function select($where = null, $values = null, $debug = false)
    {
        return parent::select($where,$values,$debug);
    }
<?
}

function outputPropertyArray($r)
{

    echo PHP_EOL . "\t" . 'protected $_properties = [' . PHP_EOL;
    foreach ($r as $d) {
        if ($d['Field'] != 'id')
            echo "\t\t'" . $d['Field'] . "' => " . field($d) . ", " . PHP_EOL;
    }
    echo "\t];" . PHP_EOL;
}

function getFieldCommentString($out)
{
    $s = ', "hasRelation"=> ' . $out['hasRelation'];
    if ($out['hasRelation'] == 'true') {
        $s .= ', "relatedClass" => "' . $out['relatedClass'] . '"';
        $s .= ', "relatedProperty" => "' . $out['relatedProperty'] . '"';
    }
    return $s;
}

function parseFieldComment($c)
{
    $out = ['hasRelation' => 'false'];
    $c = trim($c);

    if (strlen(trim($c)) > 3) {
        if (substr($c, 0, 3) == 'fk:') {
            $class = explode('.', substr($c, 3));
            $out['hasRelation'] = 'true';
            $out['relatedProperty'] = $class[1];
            $out['relatedClass'] = $class[0];
        }
    }

    return $out;
}

function field($d)
{

    $type = getPHPType($d['Type']);
    $out = parseFieldComment($d['Comment']);

    $extras = getFieldCommentString($out);

    if ($out['hasRelation'] == 'true') {
        $type = $out['relatedClass'];
    }
    if ($d['Default'] == null) {

        switch ($type) {
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
    } else $default = $d['Default'];


    return '[' . '"value" => null, "updated" => false, "default" => ' . $default . ', "type"=>"' . $type . '"' . $extras . ']';
}

function outputProperties($r)
{

    echo '/**' . PHP_EOL;
    foreach ($r as $d) {
        if ($d['Field'] != 'id') {
            $fc = parseFieldComment($d['Comment']);
            $type = getPHPType($d['Type']);
            if ($fc['hasRelation'] == 'true') {
                $type = $fc['relatedClass'];
            }
            echo '* @property ' . $type . ' $' . $d['Field'] . PHP_EOL;
        }
    }
    echo '**/' . PHP_EOL . PHP_EOL;
}

function getPHPType($mysqlType)
{
    if (substr($mysqlType, 0, 3) == 'int') return 'int';
    if (substr($mysqlType, 0, 6) == 'double') return 'float';
    if (substr($mysqlType, 0, 5) == 'float') return 'float';
    if (substr($mysqlType, 0, 7) == 'tinyint') return 'int';
    if (substr($mysqlType, 0, 7) == 'varchar') return 'string';
    if (substr($mysqlType, 0, 4) == 'text') return 'string';
    if (substr($mysqlType, 0, 10) == 'mediumtext') return 'string';
    if (substr($mysqlType, 0, 8) == 'datetime') return 'Date';
}

function makeClass($table, $classname = '')
{
    if ($classname == '') {
        $classname = ucfirst(substr($table, 0, strlen($table) - 1));
    }

    ob_start();
    processTable($table, $classname);
    $data = ob_get_clean();
    global $modelPath;
    file_put_contents($modelPath . $classname . '.php', $data);
   // echo nl2br(htmlspecialchars($data));
}