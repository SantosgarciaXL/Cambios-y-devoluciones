<?php
/**
 * Script de diagnóstico para verificar la conexión y configuración
 * Guarda este archivo como test_connection.php en la raíz del proyecto
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Mostrar errores para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$diagnostico = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_info' => [],
    'extensions' => [],
    'files' => [],
    'database' => [],
    'api_tests' => []
];

try {
    // 1. Información básica de PHP
    $diagnostico['php_info'] = [
        'version' => phpversion(),
        'os' => PHP_OS,
        'sapi' => php_sapi_name(),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize')
    ];

    // 2. Verificar extensiones requeridas
    $extensiones_requeridas = ['sqlsrv', 'pdo_sqlsrv', 'json', 'curl'];
    foreach ($extensiones_requeridas as $ext) {
        $diagnostico['extensions'][$ext] = extension_loaded($ext);
    }

    // 3. Verificar archivos críticos
    $archivos_criticos = [
        'config/config.php' => file_exists(__DIR__ . '/config/config.php'),
        'config/database.php' => file_exists(__DIR__ . '/config/database.php'),
        'backend/models/Solicitud.php' => file_exists(__DIR__ . '/backend/models/Solicitud.php'),
        'backend/controllers/SolicitudController.php' => file_exists(__DIR__ . '/backend/controllers/SolicitudController.php'),
        'backend/services/ValidacionService.php' => file_exists(__DIR__ . '/backend/services/ValidacionService.php'),
        'api/listar_solicitudes.php' => file_exists(__DIR__ . '/api/listar_solicitudes.php'),
        'api/estadisticas.php' => file_exists(__DIR__ . '/api/estadisticas.php')
    ];

    foreach ($archivos_criticos as $archivo => $existe) {
        $diagnostico['files'][$archivo] = $existe;
    }

    // 4. Probar carga de configuración
    try {
        if (file_exists(__DIR__ . '/config/config.php')) {
            require_once __DIR__ . '/config/config.php';
            $diagnostico['config_loaded'] = true;
            
            if (class_exists('Config')) {
                $diagnostico['config_class'] = true;
                $diagnostico['config_environment'] = Config::getEnvironment();
                $diagnostico['config_debug'] = Config::isDebug();
            } else {
                $diagnostico['config_class'] = false;
            }
        } else {
            $diagnostico['config_loaded'] = false;
        }
    } catch (Exception $e) {
        $diagnostico['config_error'] = $e->getMessage();
    }

    // 5. Probar conexión a base de datos
    try {
        if (file_exists(__DIR__ . '/config/database.php')) {
            require_once __DIR__ . '/config/database.php';
            $diagnostico['database']['config_loaded'] = true;
            
            if (class_exists('DatabaseConfig')) {
                $diagnostico['database']['class_exists'] = true;
                
                // Intentar obtener configuración
                try {
                    $config = DatabaseConfig::getConfig();
                    $diagnostico['database']['config'] = [
                        'host' => $config['host'],
                        'database' => $config['database'],
                        'user' => $config['user'],
                        'charset' => $config['charset']
                    ];
                } catch (Exception $e) {
                    $diagnostico['database']['config_error'] = $e->getMessage();
                }
                
                // Intentar conexión real
                if (extension_loaded('sqlsrv')) {
                    try {
                        $connection = DatabaseConfig::getConnection();
                        if ($connection) {
                            $diagnostico['database']['connection'] = 'SUCCESS';
                            
                            // Probar query simple
                            $result = sqlsrv_query($connection, "SELECT GETDATE() AS fecha_actual");
                            if ($result) {
                                $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                                $diagnostico['database']['test_query'] = 'SUCCESS';
                                $diagnostico['database']['server_time'] = $row['fecha_actual']->format('Y-m-d H:i:s');
                            } else {
                                $diagnostico['database']['test_query'] = 'FAILED';
                                $diagnostico['database']['query_error'] = sqlsrv_errors();
                            }
                            
                            DatabaseConfig::closeConnection($connection);
                        } else {
                            $diagnostico['database']['connection'] = 'FAILED';
                            $diagnostico['database']['connection_error'] = sqlsrv_errors();
                        }
                    } catch (Exception $e) {
                        $diagnostico['database']['connection'] = 'EXCEPTION';
                        $diagnostico['database']['connection_error'] = $e->getMessage();
                    }
                } else {
                    $diagnostico['database']['sqlsrv_missing'] = true;
                }
            } else {
                $diagnostico['database']['class_exists'] = false;
            }
        } else {
            $diagnostico['database']['config_loaded'] = false;
        }
    } catch (Exception $e) {
        $diagnostico['database']['error'] = $e->getMessage();
    }

    // 6. Probar APIs específicas
    $apis_test = [
        'api/debug.php',
        'api/test_simple.php',
        'api/listar_solicitudes.php',
        'api/estadisticas.php'
    ];

    foreach ($apis_test as $api) {
        if (file_exists(__DIR__ . '/' . $api)) {
            try {
                // Simular llamada interna
                ob_start();
                $_SERVER['REQUEST_METHOD'] = 'GET';
                $_GET['action'] = 'test';
                
                include __DIR__ . '/' . $api;
                $output = ob_get_clean();
                
                $json_output = json_decode($output, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $diagnostico['api_tests'][$api] = 'JSON_VALID';
                } else {
                    $diagnostico['api_tests'][$api] = 'JSON_INVALID';
                    $diagnostico['api_tests'][$api . '_output'] = substr($output, 0, 500);
                }
            } catch (Exception $e) {
                $diagnostico['api_tests'][$api] = 'ERROR: ' . $e->getMessage();
            }
        } else {
            $diagnostico['api_tests'][$api] = 'FILE_NOT_FOUND';
        }
    }

    // 7. Verificar permisos de archivos
    $diagnostico['permissions'] = [
        'current_dir_writable' => is_writable(__DIR__),
        'logs_dir_exists' => is_dir(__DIR__ . '/logs'),
        'logs_dir_writable' => is_dir(__DIR__ . '/logs') ? is_writable(__DIR__ . '/logs') : false
    ];

    echo json_encode([
        'success' => true,
        'diagnostico' => $diagnostico
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'diagnostico_parcial' => $diagnostico
    ], JSON_PRETTY_PRINT);
}
?>