<?php 


// Function to get a list of tables from a database
function getTables($credentials)
{
    $conn = new PDO(
        "mysql:host={$credentials['host']};dbname={$credentials['database']}",
        $credentials['username'],
        $credentials['password']
    );

    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return $tables;
}

// Function to execute a query and fetch results as an associative array
function executeQuery($conn, $query)
{
    $stmt = $conn->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get table details (columns, triggers, indexes) from a database
function getTableDetails($conn, $database)
{
    $details = [];

    // Retrieve indexes
    $indexesQuery = "SELECT
        TABLE_NAME AS `Table`,
        NON_UNIQUE AS `Non_unique`,
        INDEX_NAME AS `Key_name`,
        SEQ_IN_INDEX AS `Seq_in_index`,
        GROUP_CONCAT(column_name ORDER BY seq_in_index) AS `Column_name`,
        COLLATION AS `Collation`,
        CARDINALITY AS `Cardinality`,
        SUB_PART AS `Sub_part`,
        PACKED AS `Packed`,
        CASE WHEN NULLABLE = 'YES' THEN 'YES' ELSE '' END AS `Null`,
        INDEX_TYPE AS `Index_type`,
        INDEX_COMMENT AS `Index_comment`
    FROM
        INFORMATION_SCHEMA.STATISTICS
    WHERE
        TABLE_SCHEMA = '$database'
    GROUP BY table_name, index_name";

    $indexes = executeQuery($conn, $indexesQuery);

    // Retrieve columns
    $columnsQuery = "SELECT
        TABLE_NAME AS `Table`,
        COLUMN_NAME AS `Field`,
        COLUMN_TYPE AS `Type`,
        IS_NULLABLE AS `Null`,
        COLUMN_KEY AS `Key`,
        COLUMN_DEFAULT AS `Default`,
        EXTRA AS `Extra`
    FROM
        INFORMATION_SCHEMA.COLUMNS
    WHERE
        TABLE_SCHEMA = '$database'";

    $columns = executeQuery($conn, $columnsQuery);

    // Retrieve triggers
    $triggersQuery = "SELECT
        EVENT_OBJECT_TABLE AS `Table`,
        TRIGGER_NAME AS `Trigger`,
        EVENT_MANIPULATION AS `Event`,
        ACTION_STATEMENT AS `Statement`,
        ACTION_TIMING AS `Timing`,
        CREATED AS `Created`,
        SQL_MODE AS `sql_mode`,
        DEFINER AS `Definer`,
        CHARACTER_SET_CLIENT AS `character_set_client`,
        COLLATION_CONNECTION AS `collation_connection`,
        DATABASE_COLLATION AS `Database Collation`
    FROM
        INFORMATION_SCHEMA.TRIGGERS
    WHERE
        TRIGGER_SCHEMA = '$database'";

    $triggers = executeQuery($conn, $triggersQuery);

    // Classify data table-wise
    foreach ($indexes as $index) {
        $tableName = $index['Table'];
        $details[$tableName]['indexes'][] = $index;
    }

    foreach ($columns as $column) {
        $tableName = $column['Table'];
        $details[$tableName]['columns'][] = $column;
        if (!isset($details[$tableName]['indexes'])) {
            $details[$tableName]['indexes'] = [];
        }
        if (!isset($details[$tableName]['triggers'])) {
            $details[$tableName]['triggers'] = [];
        }
    }

    foreach ($triggers as $trigger) {
        $tableName = $trigger['Table'];
        $details[$tableName]['triggers'][] = $trigger;
    }

    return $details;
}

function storeArrayAsJson($array, $filename)
{
    $json = json_encode($array, JSON_PRETTY_PRINT);
    file_put_contents($filename, $json);
}

function generateQueriesToAddMissingTables($missingTables,$conn){
    $missingTablesQueries = "-- ADD MISSING TABLES".PHP_EOL;
    foreach($missingTables as $tableName){
        try {
            $stmt = $conn->query("SHOW CREATE TABLE $tableName");
            if($stmt){
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                // The CREATE TABLE statement is stored in the 'Create Table' key of the result array
                if(isset($result['Create Table'])){
                    $createTableStatement = $result['Create Table'];
                }
                if(isset($result['Create View'])){
                    $createTableStatement = $result['Create View'];
                }
            }
            else{
                echo "Error: " . $conn->errorInfo()[2];
            }
            
        } catch(PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
        $missingTablesQueries .= PHP_EOL . $createTableStatement . ";";
    }
    return $missingTablesQueries;
}

function generateQueriesToRemoveExtraTables($extraTables,$conn){
    $removeExtraTablesQueries = "-- REMOVE EXTRA TABLES".PHP_EOL;
    foreach($extraTables as $tableName){
        $removeExtraTablesQueries .= PHP_EOL . "DROP TABLE $tableName;";
    }
    return $removeExtraTablesQueries;
}

function getMissingEntities($prodTables, $devTables) {
    $missingEntities = [];

    foreach ($prodTables as $tableName => $prodTableData) {
        if (!isset($devTables[$tableName])) {
            continue;
        }

        $devTableData = $devTables[$tableName];

        $missingColumns = array_udiff($prodTableData['columns'], $devTableData['columns'], function($a, $b) {
            return strcmp($a['Field'], $b['Field']);
        });

        $extraColumns = array_udiff($devTableData['columns'], $prodTableData['columns'], function($a, $b) {
            return strcmp($a['Field'], $b['Field']);
        });

        $modifiedColumns = array_filter($missingColumns, function($expectedColumn) use ($devTableData) {
            $devColumns = $devTableData['columns'];
            $devColumn = array_filter($devColumns, function($devColumn) use ($expectedColumn) {
                return $devColumn['Field'] === $expectedColumn['Field'];
            });
            return !empty($devColumn) && !(
                $expectedColumn['Type'] == $devColumn[0]['Type'] &&
                $expectedColumn['Null'] == $devColumn[0]['Null'] &&
                $expectedColumn['Key'] == $devColumn[0]['Key'] &&
                $expectedColumn['Default'] == $devColumn[0]['Default'] &&
                $expectedColumn['Extra'] == $devColumn[0]['Extra']
            );
        });

        $missingTriggers = array_udiff($prodTableData['triggers'], $devTableData['triggers'], function($a, $b) {
            return strcmp($a['Trigger'], $b['Trigger']);
        });

        $extraTriggers = array_udiff($devTableData['triggers'], $prodTableData['triggers'], function($a, $b) {
            return strcmp($a['Trigger'], $b['Trigger']);
        });

        $missingIndexes = array_udiff($prodTableData['indexes'], $devTableData['indexes'], function($a, $b) {
            return strcmp($a['Key_name'], $b['Key_name']);
        });

        $extraIndexes = array_udiff($devTableData['indexes'], $prodTableData['indexes'], function($a, $b) {
            return strcmp($a['Key_name'], $b['Key_name']);
        });

        if (!empty($missingColumns) || !empty($missingTriggers) || !empty($missingIndexes) || !empty($extraColumns) || !empty($extraTriggers) || !empty($extraIndexes)) {
            $missingEntities[$tableName] = [
                "missing_columns" => $missingColumns,
                "missing_triggers" => $missingTriggers,
                "missing_indexes" => $missingIndexes,
                "modified_columns" => $modifiedColumns,
                "extra_columns" => $extraColumns,
                "extra_triggers" => $extraTriggers,
                "extra_indexes" => $extraIndexes,
            ];
        }
    }

    return $missingEntities;
}

function generateQueriesForMismatchEntities($missingEntities){
    $queries = "";
    foreach ($missingEntities as $tableName => $missingData) {
        $missingColumns = $missingData['missing_columns'];
        $missingTriggers = $missingData['missing_triggers'];
        $missingIndexes = $missingData['missing_indexes'];
        $modifiedColumns = $missingData['modified_columns'];
        $extraColumns = $missingData['extra_columns'];
        $extraTriggers = $missingData['extra_triggers'];
        $extraIndexes = $missingData['extra_indexes'];

        $columnQueries = [];
        foreach ($missingColumns as $column) {
            $columnName = $column['Field'];
            $columnType = $column['Type'];
            $columnNull = strtolower($column['Null']) == 'yes' ? "NULL" : "NOT NULL";
            $columnDefault = empty($column['Default']) ? "" : "DEFAULT '" . $column['Default']."'";
    
            $columnQueries[] = PHP_EOL . "  ADD COLUMN `$columnName` $columnType $columnNull $columnDefault";
        }
        foreach ($modifiedColumns as $column) {
            $columnName = $column['Field'];
            $columnType = $column['Type'];
            $columnNull = strtolower($column['Null']) == 'yes' ? "NULL" : "NOT NULL";
            $columnDefault = empty($column['Default']) ? "" : "DEFAULT " . $column['Default'];
            $columnQueries[] = PHP_EOL . "  MODIFY COLUMN `$columnName` $columnType $columnNull $columnDefault";
        }
        $extraColumnQueries = [];
        foreach ($extraColumns as $column) {
            $columnName = $column['Field'];
            $columnType = $column['Type'];
            $columnNull = strtolower($column['Null']) == 'yes' ? "NULL" : "NOT NULL";
            $columnDefault = empty($column['Default']) ? "" : "DEFAULT '" . $column['Default'] . "'";

            $extraColumnQueries[] = PHP_EOL . "  DROP COLUMN `$columnName`";
        }
    
        $indexQueries = [];
        foreach ($missingIndexes as $index) {
            $indexName = $index["Key_name"];
            $columnName = $index["Column_name"];
            $columnNameArray = explode(",",$columnName);
            $columnName = "`" . implode("`,`",$columnNameArray) . "`";
            $indexQueries[] = PHP_EOL . "   ADD INDEX $indexName ($columnName)";
        }
        
        $extraIndexQueries = [];
        foreach ($extraIndexes as $index) {
            $indexName = $index["Key_name"];
            $extraIndexQueries[] = PHP_EOL . "  DROP INDEX $indexName";
        }

        $triggerQueries = [];
        foreach ($missingTriggers as $trigger) {
            $triggerName = $trigger["Trigger"];
            $statement = $trigger["Statement"];
            $triggerQueries[] = PHP_EOL ."CREATE TRIGGER $triggerName AFTER INSERT ON $tableName FOR EACH ROW $statement";
        }
        
        $extraTriggerQueries = [];
        foreach ($extraTriggers as $trigger) {
            $triggerName = $trigger["Trigger"];
            $extraTriggerQueries[] = PHP_EOL . "  DROP TRIGGER $triggerName";
        }
        
        if (!empty($columnQueries) ||  !empty($extraColumnQueries) || !empty($indexQueries) || !empty($extraIndexQueries)) {
            $alterTableQuery = "ALTER TABLE $tableName";
            $queryParts = [];

            if (!empty($columnQueries) ||  !empty($extraColumnQueries)) {
                $queryParts[] = implode(", ", array_merge($columnQueries, $extraColumnQueries));
            }

            if (!empty($indexQueries) || !empty($extraIndexQueries)) {
                $queryParts[] = implode(", ", array_merge($indexQueries, $extraIndexQueries));
            }

            $alterTableQuery .= " " . implode(", ", $queryParts) . ";";
            $queries .= $alterTableQuery . PHP_EOL;
        }

        $queries .= implode(";" . PHP_EOL, array_merge($triggerQueries, $extraTriggerQueries));
        if (!empty($triggerQueries) || !empty($extraTriggerQueries)) {
            $queries .= ";" . PHP_EOL;
        }
    }
    return $queries;
}