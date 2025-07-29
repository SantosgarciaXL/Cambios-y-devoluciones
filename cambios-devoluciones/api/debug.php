<?php
/**
 * Script de depuración para verificar configuración
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $debug = [
        'php_version' => phpversion(),
        'extensions' => [
            'sqlsrv' => extension_loaded('sqlsrv'),
            'pdo_sqlsrv' => extension_loaded('pdo_sqlsrv'),
            'json' => extension_loaded('json')
        ],
        'paths' => [
            'current_dir' => __DIR__,
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'N/A'
        ],
        'files_exist' => [
            'config.php' => file_exists(__DIR__ . '/../config/config.php'),
            'database.php' => file_exists(__DIR__ . '/../config/database.php'),
            'Solicitud.php' => file_exists(__DIR__ . '/../backend/models/Solicitud.php'),
            'SolicitudController.php' => file_exists(__DIR__ . '/../backend/controllers/SolicitudController.php')
        ]
    ];

    echo json_encode([
        'success' => true,
        'debug_info' => $debug,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>