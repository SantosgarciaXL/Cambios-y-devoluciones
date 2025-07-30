<?php
/**
 * Configuración de Base de Datos Mejorada
 * Sistema de Cambios y Devoluciones
 * Reemplaza config/database.php con este contenido
 */

require_once __DIR__ . '/config.php';

class DatabaseConfig {
    
    private static $host;
    private static $database;
    private static $user;
    private static $password;
    private static $charset;
    private static $initialized = false;
    private static $connectionAvailable = null;
    
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        // Cargar variables de entorno si existe el archivo .env
        if (file_exists(__DIR__ . '/../.env')) {
            self::loadEnv();
        } else {
            // Configuración por defecto
            self::setDefaultConfig();
        }
        
        self::$initialized = true;
    }
    
    private static function loadEnv() {
        try {
            $env = [];
            $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
                    list($key, $value) = explode('=', $line, 2);
                    $env[trim($key)] = trim($value);
                }
            }
            
            self::$host = $env['HOST_CENTRAL'] ?? 'SERVIDOR';
            self::$database = $env['DATABASE_CENTRAL'] ?? 'Empresa_Ejemplo';
            self::$user = $env['USER'] ?? 'sa';
            self::$password = $env['PASS'] ?? 'Axoft1988';
            self::$charset = $env['CHARACTER'] ?? 'UTF-8';
            
        } catch (Exception $e) {
            error_log("Error loading .env file: " . $e->getMessage());
            self::setDefaultConfig();
        }
    }
    
    private static function setDefaultConfig() {
        self::$host = Config::get('database.host', 'localhost');
        self::$database = Config::get('database.name', 'ecommerce_cambios_devoluciones');
        self::$user = Config::get('database.user', 'sa');
        self::$password = Config::get('database.password', '');
        self::$charset = Config::get('database.charset', 'UTF-8');
    }
    
    /**
     * Verificar si la conexión está disponible
     */
    public static function isConnectionAvailable() {
        if (self::$connectionAvailable !== null) {
            return self::$connectionAvailable;
        }
        
        if (!extension_loaded('sqlsrv')) {
            self::$connectionAvailable = false;
            return false;
        }
        
        try {
            $connection = self::getConnection();
            if ($connection) {
                self::closeConnection($connection);
                self::$connectionAvailable = true;
                return true;
            }
        } catch (Exception $e) {
            self::$connectionAvailable = false;
        }
        
        return false;
    }
    
    /**
     * Obtener conexión SQL Server con múltiples intentos
     */
    public static function getConnection() {
        self::init();
        
        if (!extension_loaded('sqlsrv')) {
            throw new Exception("Extensión sqlsrv no está instalada");
        }
        
        // Configuraciones a probar en orden de prioridad
        $configurations = [
            // Configuración desde .env/config
            [
                'host' => self::$host,
                'user' => self::$user,
                'pass' => self::$password,
                'description' => 'Configuración personalizada'
            ],
            // Configuraciones comunes de desarrollo
            [
                'host' => 'localhost\\SQLEXPRESS',
                'user' => null, // Autenticación Windows
                'pass' => null,
                'description' => 'SQL Server Express local con Windows Auth'
            ],
            [
                'host' => 'localhost',
                'user' => null,
                'pass' => null,
                'description' => 'SQL Server local con Windows Auth'
            ],
            [
                'host' => '(local)',
                'user' => null,
                'pass' => null,
                'description' => 'SQL Server (local) con Windows Auth'
            ],
            [
                'host' => '127.0.0.1',
                'user' => 'sa',
                'pass' => '',
                'description' => 'SQL Server IP local con sa sin contraseña'
            ]
        ];
        
        $lastError = null;
        
        foreach ($configurations as $config) {
            try {
                $connection = self::attemptConnection($config);
                if ($connection) {
                    error_log("Conexión exitosa usando: " . $config['description']);
                    return $connection;
                }
            } catch (Exception $e) {
                $lastError = $e;
                error_log("Falló conexión con {$config['description']}: " . $e->getMessage());
            }
        }
        
        // Si llegamos aquí, todas las configuraciones fallaron
        $errorMessage = "No se pudo conectar a SQL Server con ninguna configuración.";
        if ($lastError) {
            $errorMessage .= " Último error: " . $lastError->getMessage();
        }
        
        throw new Exception($errorMessage);
    }
    
    /**
     * Intentar conexión con una configuración específica
     */
    private static function attemptConnection($config) {
        $params = [
            "Database" => self::$database,
            "CharacterSet" => self::$charset,
            "ReturnDatesAsStrings" => true,
            "TrustServerCertificate" => true, // Para SQL Server 2022+
            "Encrypt" => false // Para desarrollo local
        ];
        
        // Agregar credenciales si están especificadas
        if (!empty($config['user'])) {
            $params["UID"] = $config['user'];
            $params["PWD"] = $config['pass'];
        }
        
        $connection = sqlsrv_connect($config['host'], $params);
        
        if ($connection === false) {
            $errors = sqlsrv_errors();
            $errorMessage = "Error conectando a {$config['host']}: ";
            if ($errors) {
                $errorMessage .= $errors[0]['message'];
            }
            throw new Exception($errorMessage);
        }
        
        return $connection;
    }
    
    /**
     * Cerrar conexión
     */
    public static function closeConnection($connection) {
        if ($connection && is_resource($connection)) {
            sqlsrv_close($connection);
        }
    }
    
    /**
     * Ejecutar query con manejo de errores mejorado
     */
    public static function executeQuery($connection, $sql, $params = array()) {
        if (!$connection) {
            throw new Exception("Conexión no válida");
        }
        
        $result = sqlsrv_query($connection, $sql, $params);
        
        if ($result === false) {
            $errors = sqlsrv_errors();
            $errorMessage = "Error en la consulta SQL: ";
            if ($errors) {
                $errorMessage .= $errors[0]['message'];
                error_log("SQL Error: " . $errors[0]['message'] . " | Query: " . $sql);
            }
            throw new Exception($errorMessage);
        }
        
        return $result;
    }
    
    /**
     * Obtener configuración actual (sin contraseña)
     */
    public static function getConfig() {
        self::init();
        return [
            'host' => self::$host,
            'database' => self::$database,
            'user' => self::$user,
            'charset' => self::$charset,
            'extension_loaded' => extension_loaded('sqlsrv'),
            'connection_available' => self::isConnectionAvailable()
        ];
    }
    
    /**
     * Probar conexión y devolver información detallada
     */
    public static function testConnection() {
        $result = [
            'success' => false,
            'extension_loaded' => extension_loaded('sqlsrv'),
            'config' => self::getConfig(),
            'connection_info' => null,
            'server_info' => null,
            'error' => null
        ];
        
        if (!extension_loaded('sqlsrv')) {
            $result['error'] = 'Extensión sqlsrv no está instalada';
            return $result;
        }
        
        try {
            $connection = self::getConnection();
            
            if ($connection) {
                $result['success'] = true;
                $result['connection_info'] = sqlsrv_server_info($connection);
                
                // Probar consulta simple
                $queryResult = sqlsrv_query($connection, "SELECT @@VERSION as version, GETDATE() as current_time");
                if ($queryResult) {
                    $row = sqlsrv_fetch_array($queryResult, SQLSRV_FETCH_ASSOC);
                    $result['server_info'] = [
                        'version' => $row['version'],
                        'current_time' => $row['current_time']->format('Y-m-d H:i:s')
                    ];
                }
                
                self::closeConnection($connection);
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Crear base de datos si no existe
     */
    public static function createDatabaseIfNotExists() {
        if (!self::isConnectionAvailable()) {
            return false;
        }
        
        try {
            // Conectar a master para crear la base de datos
            $masterParams = [
                "Database" => "master",
                "UID" => self::$user,
                "PWD" => self::$password,
                "CharacterSet" => self::$charset,
                "ReturnDatesAsStrings" => true,
                "TrustServerCertificate" => true
            ];
            
            $connection = sqlsrv_connect(self::$host, $masterParams);
            
            if ($connection) {
                $sql = "IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = ?) 
                        BEGIN CREATE DATABASE [" . self::$database . "] END";
                
                $result = sqlsrv_query($connection, $sql, [self::$database]);
                
                self::closeConnection($connection);
                
                return $result !== false;
            }
            
        } catch (Exception $e) {
            error_log("Error creando base de datos: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Generar archivo .env de ejemplo
     */
    public static function generateEnvExample() {
        $content = "# Configuración de Base de Datos\n";
        $content .= "# Sistema de Cambios y Devoluciones\n\n";
        $content .= "# Configuración para SQL Server local\n";
        $content .= "HOST_CENTRAL=localhost\\SQLEXPRESS\n";
        $content .= "DATABASE_CENTRAL=ecommerce_cambios_devoluciones\n";
        $content .= "USER=sa\n";
        $content .= "PASS=tu_contraseña_aqui\n";
        $content .= "CHARACTER=UTF-8\n\n";
        $content .= "# Ejemplos de otras configuraciones:\n";
        $content .= "# Para SQL Server sin instancia: HOST_CENTRAL=localhost\n";
        $content .= "# Para IP específica: HOST_CENTRAL=192.168.1.100\n";
        $content .= "# Para autenticación Windows: deja USER y PASS vacíos\n";
        
        return $content;
    }
}

// Inicializar configuración al cargar el archivo
DatabaseConfig::init();
?>