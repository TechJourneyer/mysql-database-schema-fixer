<?php
require_once 'functions.php';
require_once 'config.php';

if (
    isset($_POST['sourceHost']) && isset($_POST['sourceUsername']) && isset($_POST['sourcePassword']) && isset($_POST['sourceDatabase']) &&
    isset($_POST['targetHost']) && isset($_POST['targetUsername']) && isset($_POST['targetPassword']) && isset($_POST['targetDatabase'])
) {
    $sourceDatabaseCreds = [
        'host' => trim($_POST['sourceHost']),
        'username' => trim($_POST['sourceUsername']),
        'password' => trim($_POST['sourcePassword']),
        'database' => trim($_POST['sourceDatabase'])
    ];
    
    $targetDatabaseCreds = [
        'host' => trim($_POST['targetHost']),
        'username' => trim($_POST['targetUsername']),
        'password' => trim($_POST['targetPassword']),
        'database' => trim($_POST['targetDatabase'])
    ];

    $database = ($sourceDatabaseCreds['database'] != $targetDatabaseCreds['database']) ? $sourceDatabaseCreds['database'] . "_" . $targetDatabaseCreds['database'] : $targetDatabaseCreds['database'];
    
} else {
   response(false,"Missing one or more database credentials. Please provide all required information.");
}

try{
    $source_conn = get_db_connection($sourceDatabaseCreds);
}
catch(Exception $e){
    response(false, "Source database connection failed : " . $e->getMessage());
}

try{
    $target_conn = get_db_connection($targetDatabaseCreds);
}
catch(Exception $e){
    response(false, "Target database connection failed : " . $e->getMessage());
}

$file_prefix = "database_schema";

// Get the list of tables from both databases
$source_db_tables = getTables($source_conn);
$target_db_tables =  getTables($target_conn);


// Find missing tables in the dev database
$missingTables = array_diff($target_db_tables, $source_db_tables);
$extraTables = array_diff($source_db_tables, $target_db_tables);


$missingTablesQueries = generateQueriesToAddMissingTables($missingTables,$target_conn);
$extraTablesQueries = generateQueriesToRemoveExtraTables($extraTables,$target_conn);

$mismatch_tables_sql_file  = "sql/mismatch_tables_queries_{$database}.sql";
file_put_contents($mismatch_tables_sql_file, $missingTablesQueries.PHP_EOL.$extraTablesQueries);

$mismatch_tables_schema_export_file = "exports/mismatch_tables_schema_{$database}.json";

storeArrayAsJson([
    "missing" => $missingTables,
    "extra" => $extraTables,
], $mismatch_tables_schema_export_file);

$sourceDbTableDetails = getTableDetails($source_conn, $devCredentials['database']);
$tableDetails['source'] = $sourceDbTableDetails;

$targetDbTableDetails = getTableDetails($target_conn, $prodCredentials['database']);
$tableDetails['target'] = $targetDbTableDetails;

$complete_schema_export_file = "exports/{$file_prefix}_{$database}.json";
storeArrayAsJson($tableDetails, $complete_schema_export_file);


$mismatchEntities = getMissingEntities($targetDbTableDetails,$sourceDbTableDetails);

$mistach_entities_sql_file = "sql/mistach_entities_queries_{$database}.sql";
$queries = "";
$mistach_entities_export_file = "exports/mistach_entities_{$database}.json";
storeArrayAsJson($mismatchEntities, $mistach_entities_export_file);
$queries = generateQueriesForMismatchEntities($mismatchEntities);

file_put_contents($mistach_entities_sql_file, $queries);
$msg =  "Queries have been generated. Please download required files";
response(true, $msg , [
    "mistach_tables_sql" => $mismatch_tables_sql_file,
    "mistach_entities_sql" => $mistach_entities_sql_file,
    "complete_schema_export_file" => $complete_schema_export_file,
    "mistach_entities_export_file" => $mistach_entities_export_file,
]);
