<?php
// Script config
$dbname = 'vozao_premiavel';
$username = 'admin';
$passwd = 'admin';
$host = 'localhost';
$extends = 'Entity';
$namespace = 'App\\Config';

function toCamelCase ($string) {
    $camelCaseAttrName = ucfirst(strtolower($string));
    $underscorePositions = [];
    $len = strlen($camelCaseAttrName);
    for ($i = 0; $i < $len; $i++) {
        if ($camelCaseAttrName[$i] === '_') {
            $underscorePositions[] = $i;
        }
    }
    foreach ($underscorePositions as $underscorePos) {
        $camelCaseAttrName[$underscorePos + 1] = strtoupper($camelCaseAttrName[$underscorePos + 1]);
    }
    $camelCaseAttrName = str_replace('_', '', $camelCaseAttrName);
    return $camelCaseAttrName;
}

$version = date('Y_m_d.h_I_s');

// Connects to database in order to get tables pattern
$charset = 'utf8';
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$descriptionColumnArr = [];
try {
    $connection = new PDO($dsn, $username, $passwd);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $connection->query('SHOW TABLES');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_map(fn ($obj) => $obj["Tables_in_$dbname"], $columns);
    foreach ($columns as $column) {
        $stmt = $connection->query("DESCRIBE $column");
        $descriptionColumn = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $descriptionColumnArr[$column] = $descriptionColumn;
    }
} catch (PDOException $PDOException) {
    echo $PDOException->getMessage();
    exit;
}
$connection = null;

// Creates entities folder
$folder = "entities_$version";
mkdir($folder);
$namefile = "$folder/description_columns.json";
$content = json_encode($descriptionColumnArr);
$file = fopen($namefile, "w");
if (!$descriptionColumnArr || !$file || !fwrite($file, $content)) {
    die ('Error on writing description_columns.json');
}
fclose($file);

// Sets the mysql types mapper to php types
$types = [
    '/int/' => 'int',
    '/varchar/' => 'string',
    '/decimal/' => 'float',
    '/float/' => 'float',
    '/char/' => 'float',
    '/bool/' => 'bool',
    '/blob/' => 'string',
    '/text/' => 'string',
    '/date/' => 'string',
    '/time/' => 'string',
    '/year/' => 'string',
    '/double/' => 'float',
];

$tab = chr(9);
// Creates class content
foreach ($descriptionColumnArr as $classname => $description) {
    $camelCaseClassName = toCamelCase($classname);
    $phpContent = "<?php" . PHP_EOL;
    if ($namespace) {
        $phpContent .= PHP_EOL . "namespace $namespace;" . PHP_EOL . PHP_EOL ;
    }
    $phpContent .= PHP_EOL . "class $camelCaseClassName";
    if ($extends) {
        $phpContent .= " extends $extends";
    }
    $phpContent .= PHP_EOL . '{';

    $attributes = '';
    $getters = '';
    $setters = '';

    foreach ($description as $field) {
        foreach ($types as $regex => $type) {
            if (preg_match($regex, $field['Type'])) {
                $null = ['YES' => '?', 'NO' => ''][$field['Null']];
                $camelCaseAttrName = toCamelCase($field['Field']);

                // Sets attributes
                $attributes .= PHP_EOL . $tab . "protected $null$type \${$field['Field']};";

                // Sets getters
                $getters .= PHP_EOL . $tab . "public function get$camelCaseAttrName(): $null$type" . PHP_EOL;
                $getters .= $tab . '{' . PHP_EOL;
                $getters .= $tab . $tab . "return \$this->{$field['Field']};" . PHP_EOL;
                $getters .= $tab . '}' . PHP_EOL;

                // Sets setters
                $setters .= PHP_EOL . $tab . "public function set$camelCaseAttrName($null$type \${$field['Field']}): void" . PHP_EOL;
                $setters .= $tab . '{'  . PHP_EOL;
                $setters .= $tab . $tab . "\$this->{$field['Field']} = \${$field['Field']};" . PHP_EOL;
                $setters .= $tab . '}' . PHP_EOL;
                break;
            }
        }
    }
    $phpContent .= $attributes . PHP_EOL . $getters . $setters . '}' . PHP_EOL;
    $file = fopen("$folder/$camelCaseClassName.php", 'w');
    if ($file) {
        fwrite($file, $phpContent);
    }
    fclose($file);
}

