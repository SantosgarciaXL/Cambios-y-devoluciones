<?php
/**
 * Configuración de Base de Datos
 * Sistema de Cambios y Devoluciones
 */

require_once __DIR__ . '/config.php';

class DatabaseConfig {
    
    // Configuración para SQL Server (basado en tu código existente)
    private static $host;
    private static $database;
    private static $user;
    private static $password;
    private static $charset;
    
    public static function init() {
        // Cargar variables de entorno si existe el archivo .env
        if (file_exists(__DIR__ . '/../.env')) {
            self::loadEnv();
        } else {
            // Configuración por defecto
            self::setDefaultConfig();
        }
    }
    
    private static function loadEnv() {
        $env = parse_ini_file(__DIR__ . '/../.env');
        self::$host = $env['HOST_CENTRAL'] ?? 'localhost';
        self::$database = $env['DATABASE_CENTRAL'] ?? 'ecommerce_db';
        self::$user = $env['USER'] ?? 'sa';
        self::$password = $env['PASS'] ?? '';
        self::$charset = $env['CHARACTER'] ?? 'UTF-8';
    }
    
    private static function setDefaultConfig() {
        self::$host = Config::get('database.host', 'localhost');
        self::$database = Config::get('database.name', 'ecommerce_db');
        self::$user = Config::get('database.user', 'sa');
        self::$password = Config::get('database.password', '');
        self::$charset = Config::get('database.charset', 'UTF-8');
    }
    
    /**
     * Obtener conexión SQL Server
     */
    public static function getConnection() {
        try {
            $params = array(
                "Database" => self::$database,
                "UID" => self::$user,
                "PWD" => self::$password,
                "CharacterSet" => self::$charset,
                "ReturnDatesAsStrings" => true
            );
            
            $connection = sqlsrv_connect(self::$host, $params);
            
            if ($connection === false) {
                $errors = sqlsrv_errors();
                throw new Exception("Error de conexión: " . $errors[0]['message']);
            }
            
            return $connection;
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("No se pudo conectar a la base de datos");
        }
    }
    
    /**
     * Cerrar conexión
     */
    public static function closeConnection($connection) {
        if ($connection) {
            sqlsrv_close($connection);
        }
    }
    
    /**
     * Ejecutar query con manejo de errores
     */
    public static function executeQuery($connection, $sql, $params = array()) {
        $result = sqlsrv_query($connection, $sql, $params);
        
        if ($result === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error en la consulta: " . $errors[0]['message']);
        }
        
        return $result;
    }
    
    /**
     * Obtener configuración actual
     */
    public static function getConfig() {
        return [
            'host' => self::$host,
            'database' => self::$database,
            'user' => self::$user,
            'charset' => self::$charset
        ];
    }
}

// Inicializar configuración al cargar el archivo
DatabaseConfig::init();
?>