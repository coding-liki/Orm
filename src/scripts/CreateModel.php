<?php

use CodingLiki\Orm\Normalizer\SnakeCaseToCamelCaseNormalizer;

include_once __DIR__ . "/../../../OoAutoloader/Autoloader.php";
include_once __DIR__ . "/../../../../../config/db.php";

$app = new \CodingLiki\ShellApp\ShellApp([
    "t" => [
        "table",
        "required" => true
    ],
    "m" => [
        "modelClassWithNamespace",
        "required" => true
    ],
    "q" => [
        "queryClassWithNamespace"
    ],
    's' => [
        "sourceDirectory"
    ]
]);


$hasRequired = $app->checkRequiredParams();
if (!$hasRequired) {
    echo "Set Table name -t and directory -m\n";
    exit(1);
}

$tableName = $app->getParam('table');
$sourceDirectory = $app->getParam('sourceDirectory', 'src');
$modelFullClass = $app->getParam('modelClassWithNamespace');
$queryFullClass = $app->getParam('queryClassWithNamespace', $modelFullClass."Query");

print_r([
    '$modelFullClass' => $modelFullClass,
    '$queryFullClass' => $queryFullClass
]);
$tableScheme = getTableScheme($tableName);

$fieldsString = processFieldsTpl($tableScheme);
$setterAndGettersString = processSettersAndGettersTpl($tableScheme);
$modelString = processModelTpl($tableName, $fieldsString, $setterAndGettersString, normalizeFullClass($modelFullClass), normalizeFullClass($queryFullClass));
$queryString = processQueryTpl(normalizeFullClass($modelFullClass), normalizeFullClass($queryFullClass));


function saveClass(string $fullClass,string $classString)
{
    global $sourceDirectory;

    $classDirectory = str_replace('\\', '/', $fullClass);
    if (!str_starts_with($classDirectory, $sourceDirectory . '/')) {
        $classDirectory = $sourceDirectory . '/' . $classDirectory;
    }

    $classNamespaceData = explode('/', $classDirectory);
    $classFileName = array_pop($classNamespaceData) . '.php';
    $classDirectory = __DIR__ . '/../../../../../' . implode('//', $classNamespaceData);

    if (!is_dir($classDirectory)) {
        mkdir($classDirectory, recursive: true);
    }

    file_put_contents($classDirectory . "/$classFileName", $classString);
}

saveClass($modelFullClass, $modelString);

saveClass($queryFullClass, $queryString);


function normalizeFullClass(string $fullClass): string
{
    global $sourceDirectory;
    return str_replace([$sourceDirectory.'\\', $sourceDirectory.'/', '/'], ['', '', '\\'], $fullClass);
}

function processFieldsTpl(array $tableScheme): string
{
    $template = file_get_contents(__DIR__ . '/field.tpl');
    $result = '';
    foreach ($tableScheme['fields'] as $name => $field) {
        $result .= processTpl($template, [
            'name' => $name,
            'type' => $field['type'],
            'nameNormalized' => $field['nameNormalized'],
            'comment' => $field['comment'],
            'ignoreOnInsert' => $field['ignoreOnInsert'],
        ]);
    }

    return $result;
}

function processSettersAndGettersTpl(array $tableScheme)
{
    $template = file_get_contents(__DIR__ . '/getterAndSetter.tpl');
    $result = '';
    foreach ($tableScheme['fields'] as $name => $field) {
        $result .= processTpl($template, [
            'nameNormalizedUpper' => ucfirst($field['nameNormalized']),
            'type' => $field['type'],
            'nameNormalized' => $field['nameNormalized']
        ]);
    }

    return $result;
}
function processModelTpl(string $tableName, string $fieldsString, string $setterAndGettersString, string $modelFullClass, string $queryFullClass): string
{
    $template = file_get_contents(__DIR__ . '/model.tpl');
    $modelNamespaceData = explode('\\', $modelFullClass);
    $modelClass = array_pop($modelNamespaceData);
    $modelNamespace = implode('\\', $modelNamespaceData);
    $queryNamespaceData = explode('\\', $queryFullClass);
    $queryClass = array_pop($queryNamespaceData);
    $queryNamespace = implode('\\', $queryNamespaceData);

    return processTpl($template, [
        'namespace' => $modelNamespace,
        'otherUses' => $queryNamespace === $modelNamespace ? "" : "use $queryFullClass;\n",
        'class' => $modelClass,
        'queryClass' => $queryClass,
        'tableName' => $tableName,
        'fields' => $fieldsString,
        'gettersAndSetters' => $setterAndGettersString
    ]);
}

function processQueryTpl(string $modelFullClass, string $queryFullClass)
{
    $template = file_get_contents(__DIR__ . '/query.tpl');
    $modelNamespaceData = explode('\\', $modelFullClass);
    $modelClass = array_pop($modelNamespaceData);
    $modelNamespace = implode('\\', $modelNamespaceData);
    $queryNamespaceData = explode('\\', $queryFullClass);
    $queryClass = array_pop($queryNamespaceData);
    $queryNamespace = implode('\\', $queryNamespaceData);

    return processTpl($template, [
        'queryNamespace' => $queryNamespace,
        'otherUses' => $queryNamespace === $modelNamespace ? "" : "use $modelFullClass;\n",
        'queryClass' => $queryClass,
        'modelclass' => $modelClass
    ]);
}


function processTpl(string $template, array $params): string
{
    $keys = array_map(function (string $key) {
        return '{{' . $key . '}}';
    }, array_keys($params));

    return str_replace($keys, array_values($params), $template);
}

function getTableScheme($tableName): array
{
    $db = \CodingLiki\DbModule\DbContainer::get();

    $queryResult = $db->query("show create table $tableName");

    return parseCreateScheme($queryResult->getRow()["Create Table"], $tableName);
}

function parseCreateScheme(string $createScheme, string $tableName): array
{
    preg_match_all('/`(?<name>\w+)` (?<type>\w+)\s*(?<addition>.*),/', $createScheme, $fields);

    $scheme = [
        "name" => $tableName,
        "fields" => []
    ];


    $normalizer = new SnakeCaseToCamelCaseNormalizer();
    foreach ($fields['name'] as $fieldNumber => $fieldName) {
        $type = $fields['type'][$fieldNumber];
        if($type === 'FOREIGN'){
            continue;
        }
        $addition = $fields['addition'][$fieldNumber];
        $scheme['fields'][$fieldName] = [
            'nameNormalized' => lcfirst( $normalizer->normalize($fieldName)),
            'comment' => $fields[0][$fieldNumber],
            'type' => mapType($type, $addition),
            'ignoreOnInsert' => mapIgnoreOnInsert($type, $addition),
        ];
    }

    return $scheme;
}

function mapIgnoreOnInsert(mixed $type, mixed $addition): string
{
    return match ($type) {
        'timestamp' => 'true',
        default => 'false',
    };
}

function mapType(string $type, string $addition): string
{
    $internalType = match ($type){
        'int' => 'int',
        'text', 'enum', 'timestamp' => 'string',
        'float' => 'float',
        'bool', 'tinyint'  => 'bool'
    };

    if (!str_contains($addition, "NOT NULL")) {
        $internalType = '?' . $internalType;
    }

    return $internalType;
}
