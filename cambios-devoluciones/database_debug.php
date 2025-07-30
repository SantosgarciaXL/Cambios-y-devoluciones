<?php
/**
 * Script de diagnóstico completo para conexión a SQL Server
 * Guarda como database_debug.php en la raíz del proyecto
 */

header('Content-Type: application/json; charset=utf-8');

// Mostrar errores para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$diagnostico = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_info' => [],
    'sqlsrv_info' => [],
    'connection_tests' => [],
    'config_analysis' => [],
    'recommendations' => []
];

try {
    // 1. Información básica de PHP
    $diagnostico['php_info'] = [
        'version' => phpversion(),
        'architecture' => php_uname('m'),
        'os' => PHP_OS,
        'sapi' => php_sapi_name(),
        'thread_safe' => ZEND_THREAD_SAFE ? 'Yes' : 'No'
    ];

    // 2. Verificar extensiones SQL Server
    $diagnostico['sqlsrv_info'] = [
        'sqlsrv_loaded' => extension_loaded('sqlsrv'),
        'pdo_sqlsrv_loaded' => extension_loaded('pdo_sqlsrv'),
        'available_drivers' => []
    ];

    if (extension_loaded('sqlsrv')) {
        $diagnostico['sqlsrv_info']['sqlsrv_version'] = phpversion('sqlsrv');
        
        // Obtener información de la extensión
        if (function_exists('sqlsrv_client_info')) {
            try {
                $clientInfo = sqlsrv_client_info();
                $diagnostico['sqlsrv_info']['client_info'] = $clientInfo;
            } catch (Exception $e) {
                $diagnostico['sqlsrv_info']['client_info_error'] = $e->getMessage();
            }
        }
    }

    if (extension_loaded('pdo')) {
        $diagnostico['sqlsrv_info']['available_drivers'] = PDO::getAvailableDrivers();
    }

    // 3. Analizar configuración
    $env_file = __DIR__ . '/.env';
    $config_file = __DIR__ . '/config/database.php';

    if (file_exists($env_file)) {
        $env_content = file_get_contents($env_file);
        $env_lines = explode("\n", $env_content);
        $env_config = [];
        
        foreach ($env_lines as $line) {
            if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
                list($key, $value) = explode('=', $line, 2);
                $env_config[trim($key)] = trim($value);
            }
        }
        
        $diagnostico['config_analysis']['env_file'] = [
            'exists' => true,
            'config' => [
                'HOST_CENTRAL' => $env_config['HOST_CENTRAL'] ?? 'NO_SET',
                'DATABASE_CENTRAL' => $env_config['DATABASE_CENTRAL'] ?? 'NO_SET',
                'USER' => $env_config['USER'] ?? 'NO_SET',
                'PASS' => !empty($env_config['PASS']) ? '***SET***' : 'EMPTY',
                'CHARACTER' => $env_config['CHARACTER'] ?? 'NO_SET'
            ]
        ];
    } else {
        $diagnostico['config_analysis']['env_file'] = [
            'exists' => false,
            'message' => 'Archivo .env no encontrado'
        ];
    }

    // 4. Probar diferentes configuraciones de conexión
    $connection_configs = [
        [
            'name' => 'Localhost con autenticación Windows',
            'host' => 'localhost',
            'params' => ['TrustServerCertificate' => true]
        ],
        [
            'name' => 'Localhost\\SQLEXPRESS con autenticación Windows',
            'host' => 'localhost\\SQLEXPRESS',
            'params' => ['TrustServerCertificate' => true]
        ],
        [
            'name' => '(local) con autenticación Windows',
            'host' => '(local)',
            'params' => ['TrustServerCertificate' => true]
        ],
        [
            'name' => '127.0.0.1 con autenticación Windows',
            'host' => '127.0.0.1',
            'params' => ['TrustServerCertificate' => true]
        ]
    ];

    // Agregar configuración desde .env si existe
    if (isset($env_config['HOST_CENTRAL'])) {
        $connection_configs[] = [
            'name' => 'Configuración desde .env',
            'host' => $env_config['HOST_CENTRAL'],
            'user' => $env_config['USER'] ?? null,
            'pass' => $env_config['PASS'] ?? null,
            'params' => ['TrustServerCertificate' => true]
        ];
    }

    foreach ($connection_configs as $config) {
        $test_result = [
            'config_name' => $config['name'],
            'host' => $config['host'],
            'status' => 'TESTING'
        ];

        if (extension_loaded('sqlsrv')) {
            try {
                $params = $config['params'] ?? [];
                
                // Agregar usuario y contraseña si están definidos
                if (isset($config['user']) && !empty($config['user'])) {
                    $params['UID'] = $config['user'];
                    $params['PWD'] = $config['pass'] ?? '';
                }
                
                $params['CharacterSet'] = 'UTF-8';
                $params['ReturnDatesAsStrings'] = true;

                $connection = sqlsrv_connect($config['host'], $params);

                if ($connection) {
                    $test_result['status'] = 'SUCCESS';
                    $test_result['connection_info'] = sqlsrv_server_info($connection);
                    
                    // Probar una consulta simple
                    $query_result = sqlsrv_query($connection, "SELECT @@VERSION as version, GETDATE() as current_time");
                    if ($query_result) {
                        $row = sqlsrv_fetch_array($query_result, SQLSRV_FETCH_ASSOC);
                        $test_result['server_info'] = [
                            'version' => $row['version'],
                            'current_time' => $row['current_time']->format('Y-m-d H:i:s')
                        ];
                    }
                    
                    sqlsrv_close($connection);
                } else {
                    $test_result['status'] = 'FAILED';
                    $errors = sqlsrv_errors();
                    $test_result['errors'] = $errors;
                }
            } catch (Exception $e) {
                $test_result['status'] = 'EXCEPTION';
                $test_result['error'] = $e->getMessage();
            }
        } else {
            $test_result['status'] = 'SQLSRV_NOT_LOADED';
        }

        $diagnostico['connection_tests'][] = $test_result;
    }

    // 5. Generar recomendaciones
    $recommendations = [];

    if (!extension_loaded('sqlsrv')) {
        $recommendations[] = [
            'priority' => 'HIGH',
            'issue' => 'Extensión sqlsrv no instalada',
            'solution' => 'Instalar Microsoft Drivers for PHP for SQL Server',
            'steps' => [
                '1. Descargar desde: https://docs.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server',
                '2. Copiar archivos .dll a la carpeta ext/ de PHP',
                '3. Agregar extension=sqlsrv y extension=pdo_sqlsrv en php.ini',
                '4. Reiniciar servidor web'
            ]
        ];
    }

    // Verificar si alguna conexión fue exitosa
    $successful_connections = array_filter($diagnostico['connection_tests'], function($test) {
        return $test['status'] === 'SUCCESS';
    });

    if (empty($successful_connections) && extension_loaded('sqlsrv')) {
        $recommendations[] = [
            'priority' => 'HIGH',
            'issue' => 'No se pudo conectar con ninguna configuración',
            'solution' => 'Verificar que SQL Server esté ejecutándose',
            'steps' => [
                '1. Verificar que SQL Server esté instalado y ejecutándose',
                '2. Verificar el nombre de la instancia (MSSQLSERVER, SQLEXPRESS, etc.)',
                '3. Habilitar TCP/IP en SQL Server Configuration Manager',
                '4. Verificar que el puerto 1433 esté abierto',
                '5. Verificar autenticación (Windows o Mixed Mode)'
            ]
        ];
    }

    if (!file_exists($env_file)) {
        $recommendations[] = [
            'priority' => 'MEDIUM',
            'issue' => 'Archivo .env no configurado',
            'solution' => 'Crear archivo .env con configuración de base de datos',
            'example' => [
                'HOST_CENTRAL=localhost\\SQLEXPRESS',
                'DATABASE_CENTRAL=ecommerce_cambios_devoluciones',
                'USER=sa',
                'PASS=tu_contraseña',
                'CHARACTER=UTF-8'
            ]
        ];
    }

    $recommendations[] = [
        'priority' => 'INFO',
        'issue' => 'Modo fallback disponible',
        'solution' => 'El sistema puede funcionar sin base de datos usando datos simulados',
        'note' => 'Si no puedes configurar SQL Server, el sistema funcionará completamente con datos de prueba'
    ];

    $diagnostico['recommendations'] = $recommendations;

    // Resultado final
    echo json_encode([
        'success' => true,
        'diagnostico' => $diagnostico,
        'summary' => [
            'sqlsrv_available' => extension_loaded('sqlsrv'),
            'successful_connections' => count($successful_connections),
            'total_tests' => count($diagnostico['connection_tests']),
            'can_connect' => !empty($successful_connections)
        ]
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