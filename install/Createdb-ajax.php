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
    
    $dbconn = new mysqli(
        $_SESSION['server'],
        $_SESSION['username'],
        $_SESSION['password'],
        $_SESSION['db'],
        $_SESSION['port']
    );
    
    if ($dbconn->connect_errno != 0) {
        throw new Exception($dbconn->error);
    }
    
    switch ($action) {
        case 'create_db':
            // Crear base de datos si no existe
            $db_name = $_SESSION['db'];
            $dbconn_root = new mysqli(
                $_SESSION['server'],
                $_SESSION['username'],
                $_SESSION['password'],
                '',
                $_SESSION['port']
            );
            
            $sql = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET=utf8;";
            $dbconn_root->query($sql);
            $dbconn_root->close();
            
            $response['success'] = true;
            $response['message'] = 'Database created successfully';
            $response['progress'] = 5;
            break;
            
        case 'execute_schema':
            // Ejecutar schema en batches
            $myFile = "OpensisSchemaMysqlInc.sql";
            $result = executeSQLInBatches($dbconn, $myFile, $batch);
            
            $response['success'] = true;
            $response['message'] = $result['message'];
            $response['progress'] = 5 + ($result['progress'] * 0.4); // 5-45%
            $response['complete'] = $result['complete'];
            $response['total_batches'] = $result['total_batches'];
            break;
            
        case 'execute_procs':
            // Ejecutar procedimientos en batches
            $myFile = "OpensisProcsMysqlInc.sql";
            $result = executeSQLInBatches($dbconn, $myFile, $batch);
            
            $response['success'] = true;
            $response['message'] = $result['message'];
            $response['progress'] = 45 + ($result['progress'] * 0.4); // 45-85%
            $response['complete'] = $result['complete'];
            $response['total_batches'] = $result['total_batches'];
            break;
            
        case 'create_triggers':
            // Crear triggers
            createUpdatedByTriggers($dbconn);
            
            $response['success'] = true;
            $response['message'] = 'Triggers created successfully';
            $response['progress'] = 95;
            break;
            
        case 'finalize':
            // Finalizar
            $response['success'] = true;
            $response['message'] = 'Database installation complete!';
            $response['progress'] = 100;
            $response['redirect'] = 'Step3.php';
            break;
    }
    
    $dbconn->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

function executeSQLInBatches($dbconn, $myFile, $batch) {
    if (!file_exists($myFile)) {
        throw new Exception("File not found: $myFile");
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
        if (!$result) {
            error_log("SQL Error: " . $dbconn->error . " | Query: " . substr($commands[$i], 0, 100));
        }
    }
    
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
