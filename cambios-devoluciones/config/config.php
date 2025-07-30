<?php
/**
 * Configuración General del Sistema
 * Sistema de Cambios y Devoluciones
 */

class Config {
    
    private static $config = [
        // Configuración de la aplicación
        'app' => [
            'name' => 'Sistema de Cambios y Devoluciones',
            'version' => '1.0.0',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'environment' => 'production', // development, testing, production
            'debug' => false
        ],
        
        // Configuración de base de datos
        'database' => [
            'host' => 'SERVIDOR',
            'name' => 'Empresa_Ejemplo',
            'user' => 'sa',
            'password' => 'Axoft1988',
            'charset' => 'UTF-8',
            'port' => 1433
        ],
        
        // Reglas de negocio según Ley 24.240
        'business_rules' => [
            'devolucion_online_dias' => 10,      // Días para devolución online
            'cambio_online_dias' => 30,          // Días para cambio online
            'cambio_presencial_dias' => 15,      // Días para cambio presencial
            'garantia_falla_dias' => 365,        // Días de garantía por falla
            'require_tags_for_return' => true,   // Requiere etiquetas para devolución
            'require_unused_for_return' => true  // Requiere producto no usado
        ],
        
        // Configuración de archivos y uploads
        'files' => [
            'max_upload_size' => '5MB',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
            'upload_path' => 'uploads/solicitudes/'
        ],
        
        // Configuración de logging
        'logging' => [
            'enabled' => true,
            'level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
            'file' => 'logs/app.log',
            'max_size' => '10MB'
        ],
        
        // Configuración de API
        'api' => [
            'rate_limit' => 100, // requests per minute
            'timeout' => 30,     // seconds
            'version' => 'v1'
        ]
    ];
    
    /**
     * Obtener valor de configuración
     */
    public static function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Establecer valor de configuración
     */
    public static function set($key, $value) {
        $keys = explode('.', $key);
        $config = &self::$config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    /**
     * Cargar configuración desde archivo
     */
    public static function loadFromFile($filePath) {
        if (file_exists($filePath)) {
            $fileConfig = include $filePath;
            if (is_array($fileConfig)) {
                self::$config = array_merge_recursive(self::$config, $fileConfig);
            }
        }
    }
    
    /**
     * Obtener toda la configuración
     */
    public static function all() {
        return self::$config;
    }
    
    /**
     * Verificar si está en modo debug
     */
    public static function isDebug() {
        return self::get('app.debug', false) || self::get('app.environment') === 'development';
    }
    
    /**
     * Obtener configuración de entorno
     */
    public static function getEnvironment() {
        return self::get('app.environment', 'production');
    }
}

// Configurar zona horaria
date_default_timezone_set(Config::get('app.timezone'));

// Configurar manejo de errores según el entorno
if (Config::isDebug()) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../' . Config::get('logging.file'));
}

// Configurar límites de memoria y tiempo de ejecución
ini_set('memory_limit', '256M');
ini_set('max_execution_time', Config::get('api.timeout', 30));
?>