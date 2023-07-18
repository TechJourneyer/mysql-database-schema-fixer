<?php
require_once 'functions.php';
require_once 'config.php';

$file_prefix = "database_schema";

$database = $devCredentials['database'] == $prodCredentials['database'] ? $devCredentials['database'] 
    : $devCredentials['database']."-".$prodCredentials['database'];

echo "<pre>";

// Get the list of tables from both databases
$devTables = getTables($devCredentials);
$prodTables = getTables($prodCredentials);


// Find missing tables in the dev database
$missingTables = array_diff($prodTables, $devTables);
$extraTables = array_diff($devTables, $prodTables);

$conn = new PDO(
    "mysql:host={$prodCredentials['host']};dbname={$prodCredentials['database']}",
    $prodCredentials['username'],
    $prodCredentials['password']
);

$missingTablesQueries = generateQueriesToAddMissingTables($missingTables,$conn);
$extraTablesQueries = generateQueriesToRemoveExtraTables($extraTables,$conn);

file_put_contents("sql/mismatch_tables_queries_{$database}.sql", $missingTablesQueries.PHP_EOL.$extraTablesQueries);
$export_file_name = "exports/mismatch_tables_schema_{$database}.json";
storeArrayAsJson([
    "missing" => $missingTables,
    "extra" => $extraTables,
], $export_file_name);
// Get table details for dev database
$conn = new PDO(
    "mysql:host={$devCredentials['host']};dbname={$devCredentials['database']}",
    $devCredentials['username'],
    $devCredentials['password']
);
$tableDetailsDev = getTableDetails($conn, $devCredentials['database']);
$tableDetails['dev'] = $tableDetailsDev;

// Get table details for prod database
$conn = new PDO(
    "mysql:host={$prodCredentials['host']};dbname={$prodCredentials['database']}",
    $prodCredentials['username'],
    $prodCredentials['password']
);
$tableDetailsProd = getTableDetails($conn, $prodCredentials['database']);
$tableDetails['prod'] = $tableDetailsProd;

$export_file_name = "exports/{$file_prefix}_{$database}.json";
storeArrayAsJson($tableDetails, $export_file_name);

$json = file_get_contents($export_file_name);
$data = json_decode($json, true);

$devTables = $data['dev'];
$prodTables = $data['prod'];

$mismatchEntities = getMissingEntities($prodTables,$devTables);

$filename = "sql/mistach_entities_queries_{$database}.sql";
$queries = "";
$export_file_name = "exports/mistach_entities_{$database}.json";
storeArrayAsJson($mismatchEntities, $export_file_name);
$queries = generateQueriesForMismatchEntities($mismatchEntities);

file_put_contents($filename, $queries);
echo "Queries have been generated and stored in $filename";
?>
