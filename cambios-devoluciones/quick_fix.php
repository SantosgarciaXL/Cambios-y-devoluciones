<?php
/**
 * Script de diagnóstico y reparación rápida
 * Ejecuta este archivo para identificar y corregir problemas comunes
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Configurar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$fix_actions = [];
$issues_found = [];
$auto_fixes = [];

try {
    // 1. Verificar y crear directorios necesarios
    $directories = ['logs', 'temp', 'uploads'];
    foreach ($directories as $dir) {
        if (!is_dir(__DIR__ . '/' . $dir)) {
            mkdir(__DIR__ . '/' . $dir, 0755, true);
            $auto_fixes[] = "Creado directorio: $dir";
        }
    }
    
    // 2. Verificar archivos API críticos
    $api_files = [
        'api/listar_solicitudes.php',
        'api/estadisticas.php',
        'api/crear_solicitud.php'
    ];
    
    foreach ($api_files as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $issues_found[] = "Archivo faltante: $file";
            continue;
        }
        
        // Verificar si el archivo tiene errores de sintaxis
        $output = [];
        $return_var = 0;
        exec("php -l " . escapeshellarg(__DIR__ . '/' . $file) . " 2>&1", $output, $return_var);
        
        if ($return_var !== 0) {
            $issues_found[] = "Error de sintaxis en $file: " . implode(' ', $output);
        }
    }
    
    // 3. Crear archivo de configuración mínimo si no existe
    if (!file_exists(__DIR__ . '/.env')) {
        $env_content = "# Configuración de Base de Datos\n";
        $env_content .= "HOST_CENTRAL=SERVIDOR\n";
        $env_content .= "DATABASE_CENTRAL=Empresa_Ejemplo\n";
        $env_content .= "USER=sa\n";
        $env_content .= "PASS=Axoft1988\n";
        $env_content .= "CHARACTER=UTF-8\n";
        
        file_put_contents(__DIR__ . '/.env', $env_content);
        $auto_fixes[] = "Creado archivo .env con configuración por defecto";
    }
    
    // 4. Crear archivos API fallback si hay problemas
    if (!empty($issues_found)) {
        createFallbackAPIs();
        $auto_fixes[] = "Creados archivos API de respaldo";
    }
    
    // 5. Probar APIs
    $api_tests = [];
    
    // Test listar solicitudes
    try {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        include __DIR__ . '/api/listar_solicitudes.php';
        $output = ob_get_clean();
        
        $json = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $api_tests['listar_solicitudes'] = 'OK';
        } else {
            $api_tests['listar_solicitudes'] = 'JSON_ERROR: ' . substr($output, 0, 100);
            $issues_found[] = "API listar_solicitudes devuelve JSON inválido";
        }
    } catch (Exception $e) {
        $api_tests['listar_solicitudes'] = 'EXCEPTION: ' . $e->getMessage();
        $issues_found[] = "Error en API listar_solicitudes: " . $e->getMessage();
    }
    
    // Test estadísticas
    try {
        ob_start();
        include __DIR__ . '/api/estadisticas.php';
        $output = ob_get_clean();
        
        $json = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $api_tests['estadisticas'] = 'OK';
        } else {
            $api_tests['estadisticas'] = 'JSON_ERROR: ' . substr($output, 0, 100);
            $issues_found[] = "API estadísticas devuelve JSON inválido";
        }
    } catch (Exception $e) {
        $api_tests['estadisticas'] = 'EXCEPTION: ' . $e->getMessage();
        $issues_found[] = "Error en API estadísticas: " . $e->getMessage();
    }
    
    // 6. Verificar conexión a base de datos
    $db_status = 'NOT_TESTED';
    if (extension_loaded('sqlsrv')) {
        try {
            if (file_exists(__DIR__ . '/config/database.php')) {
                require_once __DIR__ . '/config/database.php';
                $connection = DatabaseConfig::getConnection();
                if ($connection) {
                    $db_status = 'CONNECTED';
                    DatabaseConfig::closeConnection($connection);
                } else {
                    $db_status = 'CONNECTION_FAILED';
                }
            } else {
                $db_status = 'CONFIG_NOT_FOUND';
            }
        } catch (Exception $e) {
            $db_status = 'ERROR: ' . $e->getMessage();
        }
    } else {
        $db_status = 'SQLSRV_NOT_LOADED';
    }
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'system_status' => [
            'php_version' => phpversion(),
            'extensions' => [
                'sqlsrv' => extension_loaded('sqlsrv'),
                'json' => extension_loaded('json'),
                'curl' => extension_loaded('curl')
            ],
            'database_status' => $db_status
        ],
        'issues_found' => $issues_found,
        'auto_fixes_applied' => $auto_fixes,
        'api_tests' => $api_tests,
        'recommendations' => getRecommendations($issues_found, $db_status)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}

function createFallbackAPIs() {
    // Crear API de listado fallback
    $listar_content = '<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

echo json_encode([
    "success" => true,
    "data" => [
        "solicitudes" => [
            [
                "id" => 1,
                "fecha_solicitud" => "' . date('Y-m-d H:i:s') . '",
                "cliente_nombre" => "Cliente Demo",
                "numero_pedido" => "DEMO001",
                "medio_compra" => "online",
                "motivo_solicitud" => "cambio",
                "producto_usado" => false,
                "tiene_etiquetas" => true,
                "resultado_permitido" => true,
                "decision_final" => "Aprobada"
            ]
        ],
        "estadisticas" => [
            "total_solicitudes" => 1,
            "solicitudes_hoy" => 1,
            "aprobadas" => 1,
            "rechazadas" => 0
        ]
    ],
    "message" => "Datos simulados (modo fallback)",
    "timestamp" => "' . date('Y-m-d H:i:s') . '"
]);
?>';
    
    file_put_contents(__DIR__ . '/api/listar_solicitudes_backup.php', $listar_content);
    
    // Crear API de estadísticas fallback
    $stats_content = '<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

echo json_encode([
    "success" => true,
    "data" => [
        "resumen" => [
            "ultimos_30_dias" => [
                "total_solicitudes" => 15,
                "solicitudes_hoy" => 2,
                "aprobadas" => 10,
                "rechazadas" => 5,
                "pendientes" => 0
            ]
        ],
        "tendencias" => [
            ["fecha" => "' . date('Y-m-d') . '", "cantidad" => 2],
            ["fecha" => "' . date('Y-m-d', strtotime('-1 day')) . '", "cantidad" => 1],
            ["fecha" => "' . date('Y-m-d', strtotime('-2 days')) . '", "cantidad" => 3]
        ]
    ],
    "message" => "Estadísticas simuladas (modo fallback)",
    "timestamp" => "' . date('Y-m-d H:i:s') . '"
]);
?>';
    
    file_put_contents(__DIR__ . '/api/estadisticas_backup.php', $stats_content);
}

function getRecommendations($issues, $db_status) {
    $recommendations = [];
    
    if (strpos($db_status, 'ERROR') !== false || strpos($db_status, 'FAILED') !== false) {
        $recommendations[] = "Verificar configuración de base de datos en .env";
        $recommendations[] = "Asegurar que SQL Server esté ejecutándose";
        $recommendations[] = "Verificar credenciales de conexión";
    }
    
    if (!empty($issues)) {
        $recommendations[] = "Revisar logs de PHP para errores detallados";
        $recommendations[] = "Verificar permisos de archivos";
    }
    
    if (strpos($db_status, 'SQLSRV_NOT_LOADED') !== false) {
        $recommendations[] = "El sistema funcionará en modo simulación sin SQL Server";
        $recommendations[] = "Para conexión real, instalar extensión sqlsrv de PHP";
    }
    
    $recommendations[] = "Usar archivos de respaldo (*_backup.php) si hay problemas con APIs principales";
    
    return $recommendations;
}
?>