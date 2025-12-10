<?php
error_reporting(0);
session_start();
header('Content-Type: application/json');

include '../functions/ParamLibFnc.php';
require_once "../functions/PragRepFnc.php";

$response = ['success' => false, 'message' => '', 'progress' => 0];

try {
    $action = $_GET['action'] ?? 'start';
    $batch = (int)($_GET['batch'] ?? 0);
    
    switch ($action) {
        case 'save_session':
            // Guardar datos del formulario en sesión
            $_SESSION['db'] = clean_param($_POST['db'], PARAM_DATA);
            $_SESSION['data_choice'] = clean_param($_POST['data_choice'], PARAM_ALPHA);
            
            $response['success'] = true;
            $response['message'] = 'Session data saved';
            break;
            
        case 'check_and_prepare':
            $dbname = clean_param($_GET['db'], PARAM_DATA);
            $choice = clean_param($_GET['choice'], PARAM_ALPHA);
            
            $_SESSION['db'] = $dbname;
            
            $db = new mysqli($_SESSION['server'], $_SESSION['username'], $_SESSION['password'], '', $_SESSION['port']);
            
            if ($db->connect_errno != 0) {
                throw new Exception($db->error);
            }
            
            // Verificar si la BD existe
            $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=?";
            $stmt = $db->prepare($query);
            $stmt->bind_param('s', $dbname);
            $stmt->execute();
            $stmt->bind_result($data);
            $exists = $stmt->fetch();
            $stmt->close();
            
            if ($exists) {
                if ($choice == 'newdb') {
                    throw new Exception('Database exists. Choose "Remove data" option or enter a different name.');
                }
                
                // Purgar la base de datos
                $db->select_db($dbname);
                $result = $db->query("SHOW TABLES");
                
                while ($row = $result->fetch_row()) {
                    $db->query("DROP TABLE IF EXISTS `$row[0]`");
                    $db->query("DROP VIEW IF EXISTS `$row[0]`");
                }
                
                $response['message'] = 'Existing database purged successfully';
            } else {
                if ($choice == 'purgedb') {
                    throw new Exception('Database does not exist. Choose "Create new" option.');
                }
                
                // Crear nueva base de datos
                $sql = "CREATE DATABASE `$dbname` CHARACTER SET=utf8;";
                if (!$db->query($sql)) {
                    throw new Exception($db->error);
                }
                
                $response['message'] = 'New database created successfully';
            }
            
            $db->close();
            $response['success'] = true;
            $response['progress'] = 10;
            break;
            
        case 'execute_schema':
            $myFile = "OpensisSchemaMysqlInc.sql";
            $result = executeSQLInBatches($myFile, $batch);
            
            $response['success'] = true;
            $response['message'] = $result['message'];
            $response['progress'] = $result['progress'];
            $response['complete'] = $result['complete'];
            $response['total_batches'] = $result['total_batches'];
            break;
            
        case 'execute_procs':
            $myFile = "OpensisProcsMysqlInc.sql";
            $result = executeSQLInBatches($myFile, $batch);
            
            $response['success'] = true;
            $response['message'] = $result['message'];
            $response['progress'] = $result['progress'];
            $response['complete'] = $result['complete'];
            $response['total_batches'] = $result['total_batches'];
            break;
            
        case 'create_triggers':
            $dbconn = new mysqli($_SESSION['server'], $_SESSION['username'], $_SESSION['password'], $_SESSION['db'], $_SESSION['port']);
            
            if ($dbconn->connect_errno != 0) {
                throw new Exception($dbconn->error);
            }
            
            createUpdatedByTriggers($dbconn);
            $dbconn->close();
            
            $response['success'] = true;
            $response['message'] = 'Triggers created successfully';
            $response['progress'] = 95;
            break;
            
        case 'finalize':
            $response['success'] = true;
            $response['message'] = 'Database installation complete!';
            $response['progress'] = 100;
            break;
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

function executeSQLInBatches($myFile, $batch) {
    if (!file_exists($myFile)) {
        throw new Exception("File not found: $myFile");
    }
    
    $dbconn = new mysqli($_SESSION['server'], $_SESSION['username'], $_SESSION['password'], $_SESSION['db'], $_SESSION['port']);
    
    if ($dbconn->connect_errno != 0) {
        throw new Exception($dbconn->error);
    }
    
    $sql = file_get_contents($myFile);
    $sqllines = par_spt("/[\n]/", $sql);
    
    // Agrupar comandos SQL completos
    $commands = [];
    $cmd = '';
    $delim = false;
    
    foreach ($sqllines as $l) {
        if (par_rep_mt('/^\s*--/', $l) == 0) {
            if (par_rep_mt('/DELIMITER \$\$/', $l) != 0) {
                $delim = true;
            } else if (par_rep_mt('/DELIMITER ;/', $l) != 0) {
                $delim = false;
            } else if (par_rep_mt('/END\$\$/', $l) != 0) {
                $cmd .= ' END';
            } else {
                $cmd .= ' ' . $l . "\n";
            }
            
            if (par_rep_mt('/.+;/', $l) != 0 && !$delim) {
                if (trim($cmd)) {
                    $commands[] = $cmd;
                }
                $cmd = '';
            }
        }
    }
    
    // Procesar en batches de 5 comandos
    $batchSize = 5;
    $total = count($commands);
    $start = $batch * $batchSize;
    $end = min($start + $batchSize, $total);
    
    // Ejecutar el batch actual
    for ($i = $start; $i < $end; $i++) {
        $result = $dbconn->query($commands[$i]);
        if (!$result && $dbconn->errno != 0) {
            error_log("SQL Error: " . $dbconn->error . " | Query: " . substr($commands[$i], 0, 100));
        }
    }
    
    $dbconn->close();
    
    $progress = ($end / $total) * 100;
    $complete = ($end >= $total);
    
    return [
        'progress' => $progress,
        'complete' => $complete,
        'message' => "Processed $end of $total SQL commands",
        'total_batches' => ceil($total / $batchSize)
    ];
}

function createUpdatedByTriggers($dbconn) {
    if ($result = $dbconn->query("SELECT DISTINCT TABLE_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE COLUMN_NAME IN ('updated_by')
                    AND TABLE_SCHEMA='" . $_SESSION['db'] . "';")) {
        while ($row = $result->fetch_array()) {
            $tableName = $row['TABLE_NAME'];
            
            if ($tableName == 'login_records') {
                $newValue = 'new.staff_id';
            } else {
                $newValue = '@userId';
            }
            
            $dbconn->query("DROP TRIGGER IF EXISTS `" . $tableName . "_updated_by_before_insert`;");
            $dbconn->query("CREATE TRIGGER `" . $tableName . "_updated_by_before_insert` BEFORE INSERT ON `" . $tableName . "` FOR EACH ROW BEGIN SET new.updated_by=" . $newValue . "; END;");
            $dbconn->query("DROP TRIGGER IF EXISTS `" . $tableName . "_updated_by_before_update`;");
            $dbconn->query("CREATE TRIGGER `" . $tableName . "_updated_by_before_update` BEFORE UPDATE ON `" . $tableName . "` FOR EACH ROW BEGIN SET new.updated_by=" . $newValue . "; END;");
        }
        $result->free_result();
    }
    
    if ($result = $dbconn->query("SELECT DISTINCT TABLE_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE COLUMN_NAME IN ('last_updated')
                    AND TABLE_SCHEMA='" . $_SESSION['db'] . "';")) {
        while ($row = $result->fetch_array()) {
            $tableName = $row['TABLE_NAME'];
            $dbconn->query("ALTER TABLE `" . $tableName . "` CHANGE `last_updated` `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP; ");
        }
        $result->free_result();
    }
}
?>
