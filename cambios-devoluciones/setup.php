<?php
/**
 * Script de instalación y configuración
 * Sistema de Cambios y Devoluciones
 */

header('Content-Type: text/html; charset=utf-8');

// Configurar errores para mostrarlos durante setup
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Sistema de Cambios y Devoluciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-ok { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-warning { color: #ffc107; }
        .code-block { background: #f8f9fa; padding: 1rem; border-radius: 0.5rem; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-cog me-2"></i>Configuración del Sistema</h1>
                <p class="lead">Verificación y configuración inicial del sistema de cambios y devoluciones</p>
            </div>
        </div>

        <?php
        $checks = [];
        $warnings = [];
        $errors = [];
        $success = true;

        // 1. Verificar PHP y extensiones
        echo "<div class='card mb-4'>";
        echo "<div class='card-header'><h5>1. Verificación de PHP y Extensiones</h5></div>";
        echo "<div class='card-body'>";

        // PHP Version
        $php_version = phpversion();
        $php_ok = version_compare($php_version, '7.4.0', '>=');
        echo "<p><i class='fas fa-code me-2'></i>PHP Version: <strong>$php_version</strong> ";
        echo $php_ok ? "<span class='status-ok'><i class='fas fa-check'></i></span>" : "<span class='status-error'><i class='fas fa-times'></i></span>";
        echo "</p>";
        if (!$php_ok) $errors[] = "PHP 7.4+ requerido";

        // Extensiones
        $extensions = ['json' => 'JSON', 'curl' => 'cURL', 'sqlsrv' => 'SQL Server'];
        foreach ($extensions as $ext => $name) {
            $loaded = extension_loaded($ext);
            echo "<p><i class='fas fa-puzzle-piece me-2'></i>$name: ";
            echo $loaded ? "<span class='status-ok'><i class='fas fa-check'></i> Instalado</span>" : "<span class='status-error'><i class='fas fa-times'></i> No instalado</span>";
            echo "</p>";
            
            if ($ext === 'sqlsrv' && !$loaded) {
                $warnings[] = "SQL Server no disponible - se usarán datos simulados";
            } elseif (!$loaded && $ext !== 'sqlsrv') {
                $errors[] = "$name es requerido";
            }
        }

        echo "</div></div>";

        // 2. Verificar archivos del sistema
        echo "<div class='card mb-4'>";
        echo "<div class='card-header'><h5>2. Verificación de Archivos del Sistema</h5></div>";
        echo "<div class='card-body'>";

        $files = [
            'config/config.php' => 'Configuración principal',
            'config/database.php' => 'Configuración de base de datos',
            'backend/models/Solicitud.php' => 'Modelo de datos',
            'backend/controllers/SolicitudController.php' => 'Controlador principal',
            'backend/services/ValidacionService.php' => 'Servicio de validación',
            'assets/js/app.js' => 'JavaScript principal',
            'assets/css/styles.css' => 'Estilos CSS',
            'index.html' => 'Interfaz principal'
        ];

        foreach ($files as $file => $desc) {
            $exists = file_exists(__DIR__ . '/' . $file);
            echo "<p><i class='fas fa-file me-2'></i>$desc ($file): ";
            echo $exists ? "<span class='status-ok'><i class='fas fa-check'></i></span>" : "<span class='status-error'><i class='fas fa-times'></i> Faltante</span>";
            echo "</p>";
            if (!$exists) $errors[] = "Archivo faltante: $file";
        }

        echo "</div></div>";

        // 3. Verificar configuración de base de datos
        echo "<div class='card mb-4'>";
        echo "<div class='card-header'><h5>3. Configuración de Base de Datos</h5></div>";
        echo "<div class='card-body'>";

        $env_exists = file_exists(__DIR__ . '/.env');
        echo "<p><i class='fas fa-database me-2'></i>Archivo .env: ";
        echo $env_exists ? "<span class='status-ok'><i class='fas fa-check'></i> Encontrado</span>" : "<span class='status-warning'><i class='fas fa-exclamation-triangle'></i> No encontrado</span>";
        echo "</p>";

        if (!$env_exists) {
            $warnings[] = "Archivo .env no encontrado - usando configuración por defecto";
            echo "<div class='alert alert-warning'>";
            echo "<h6>Crear archivo .env</h6>";
            echo "<p>Crea un archivo llamado <code>.env</code> en la raíz del proyecto con el siguiente contenido:</p>";
            echo "<pre class='code-block'>";
            echo "HOST_CENTRAL=SERVIDOR\n";
            echo "DATABASE_CENTRAL=ecommerce_cambios_devoluciones\n";
            echo "USER=sa\n";
            echo "PASS=Axoft1988\n";
            echo "CHARACTER=UTF-8";
            echo "</pre>";
            echo "</div>";
        }

        // Intentar conexión a base de datos
        if (extension_loaded('sqlsrv') && file_exists(__DIR__ . '/config/database.php')) {
            try {
                require_once __DIR__ . '/config/database.php';
                $connection = DatabaseConfig::getConnection();
                
                if ($connection) {
                    echo "<p><i class='fas fa-plug me-2'></i>Conexión a base de datos: <span class='status-ok'><i class='fas fa-check'></i> Exitosa</span></p>";
                    
                    // Probar query
                    $result = sqlsrv_query($connection, "SELECT GETDATE() AS fecha");
                    if ($result) {
                        echo "<p><i class='fas fa-check-circle me-2'></i>Test de consulta: <span class='status-ok'><i class='fas fa-check'></i> OK</span></p>";
                    }
                    
                    DatabaseConfig::closeConnection($connection);
                } else {
                    echo "<p><i class='fas fa-plug me-2'></i>Conexión a base de datos: <span class='status-error'><i class='fas fa-times'></i> Falló</span></p>";
                    $warnings[] = "No se pudo conectar a la base de datos - se usarán datos simulados";
                }
            } catch (Exception $e) {
                echo "<p><i class='fas fa-plug me-2'></i>Conexión a base de datos: <span class='status-error'><i class='fas fa-times'></i> Error: " . htmlspecialchars($e->getMessage()) . "</span></p>";
                $warnings[] = "Error de conexión a BD: " . $e->getMessage();
            }
        } else {
            echo "<p><i class='fas fa-plug me-2'></i>Conexión a base de datos: <span class='status-warning'><i class='fas fa-exclamation-triangle'></i> No se puede probar</span></p>";
        }

        echo "</div></div>";

        // 4. Verificar permisos
        echo "<div class='card mb-4'>";
        echo "<div class='card-header'><h5>4. Verificación de Permisos</h5></div>";
        echo "<div class='card-body'>";

        $writable_dirs = ['logs', 'uploads', 'temp'];
        foreach ($writable_dirs as $dir) {
            $path = __DIR__ . '/' . $dir;
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            
            echo "<p><i class='fas fa-folder me-2'></i>Directorio $dir: ";
            if (!$exists) {
                echo "<span class='status-warning'><i class='fas fa-exclamation-triangle'></i> No existe</span>";
                $warnings[] = "Directorio $dir no existe - crearlo si es necesario";
            } elseif (!$writable) {
                echo "<span class='status-error'><i class='fas fa-times'></i> Sin permisos de escritura</span>";
                $errors[] = "Sin permisos de escritura en $dir";
            } else {
                echo "<span class='status-ok'><i class='fas fa-check'></i> OK</span>";
            }
            echo "</p>";
        }

        echo "</div></div>";

        // 5. Pruebas de API
        echo "<div class='card mb-4'>";
        echo "<div class='card-header'><h5>5. Pruebas de API</h5></div>";
        echo "<div class='card-body'>";

        $api_endpoints = [
            'api/debug.php' => 'Endpoint de debug',
            'api/test_simple.php' => 'API de prueba',
            'test_connection.php' => 'Test de conexión'
        ];

        foreach ($api_endpoints as $endpoint => $desc) {
            $file_exists = file_exists(__DIR__ . '/' . $endpoint);
            echo "<p><i class='fas fa-api me-2'></i>$desc ($endpoint): ";
            echo $file_exists ? "<span class='status-ok'><i class='fas fa-check'></i> Disponible</span>" : "<span class='status-error'><i class='fas fa-times'></i> No encontrado</span>";
            echo "</p>";
        }

        echo "<div class='mt-3'>";
        echo "<a href='api/debug.php' class='btn btn-sm btn-outline-primary me-2' target='_blank'>Probar Debug API</a>";
        echo "<a href='api/test_simple.php' class='btn btn-sm btn-outline-primary me-2' target='_blank'>Probar Simple API</a>";
        echo "<a href='test_connection.php' class='btn btn-sm btn-outline-primary' target='_blank'>Test Conexión</a>";
        echo "</div>";

        echo "</div></div>";

        // Resumen final
        echo "<div class='card mb-4'>";
        echo "<div class='card-header'><h5>Resumen de Configuración</h5></div>";
        echo "<div class='card-body'>";

        if (empty($errors)) {
            echo "<div class='alert alert-success'>";
            echo "<h6><i class='fas fa-check-circle me-2'></i>Sistema Listo</h6>";
            echo "<p>El sistema está configurado correctamente y listo para usar.</p>";
            if (!empty($warnings)) {
                echo "<p><strong>Advertencias:</strong></p><ul>";
                foreach ($warnings as $warning) {
                    echo "<li>$warning</li>";
                }
                echo "</ul>";
            }
            echo "<a href='index.html' class='btn btn-success'>Ir al Sistema</a>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-danger'>";
            echo "<h6><i class='fas fa-exclamation-circle me-2'></i>Errores Encontrados</h6>";
            echo "<p>Se encontraron los siguientes errores que deben corregirse:</p>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li>$error</li>";
            }
            echo "</ul>";
            echo "</div>";
        }

        if (!empty($warnings)) {
            echo "<div class='alert alert-warning'>";
            echo "<h6><i class='fas fa-exclamation-triangle me-2'></i>Advertencias</h6>";
            echo "<ul>";
            foreach ($warnings as $warning) {
                echo "<li>$warning</li>";
            }
            echo "</ul>";
            echo "</div>";
        }

        echo "</div></div>";

        // Instrucciones adicionales
        echo "<div class='card mb-4'>";
        echo "<div class='card-header'><h5>Pasos Siguientes</h5></div>";
        echo "<div class='card-body'>";
        echo "<ol>";
        echo "<li><strong>Configurar Base de Datos:</strong> Si aún no lo has hecho, ejecuta el script <code>sql/schema.sql</code> en tu servidor SQL Server.</li>";
        echo "<li><strong>Crear .env:</strong> Configura las credenciales de tu base de datos en el archivo .env.</li>";
        echo "<li><strong>Permisos:</strong> Asegúrate de que los directorios necesarios tengan permisos de escritura.</li>";
        echo "<li><strong>Probar APIs:</strong> Usa los enlaces de arriba para probar que las APIs respondan correctamente.</li>";
        echo "<li><strong>Modo Fallback:</strong> Si no tienes SQL Server, el sistema funcionará con datos simulados.</li>";
        echo "</ol>";
        echo "</div></div>";
        ?>

        <div class="text-center mb-4">
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-primary">
                <i class="fas fa-redo me-2"></i>Verificar Nuevamente
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>