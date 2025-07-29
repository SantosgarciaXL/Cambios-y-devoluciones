<?php
/**
 * API Endpoint: Exportar Datos
 * GET /api/exportar_datos.php
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Use GET.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

try {
    // Incluir dependencias
    require_once __DIR__ . '/../backend/controllers/SolicitudController.php';
    
    // Obtener filtros para exportación
    $filtros = [];
    
    if (!empty($_GET['fecha_desde'])) {
        $filtros['fecha_desde'] = $_GET['fecha_desde'];
    }
    
    if (!empty($_GET['fecha_hasta'])) {
        $filtros['fecha_hasta'] = $_GET['fecha_hasta'];
    }
    
    if (!empty($_GET['medio_compra'])) {
        $filtros['medio_compra'] = $_GET['medio_compra'];
    }
    
    if (!empty($_GET['motivo_solicitud'])) {
        $filtros['motivo_solicitud'] = $_GET['motivo_solicitud'];
    }
    
    if (!empty($_GET['decision_final'])) {
        $filtros['decision_final'] = $_GET['decision_final'];
    }
    
    // Crear controlador y exportar
    $controller = new SolicitudController();
    $respuesta = $controller->exportarCSV($filtros);
    
    if ($respuesta['success']) {
        // Configurar headers para descarga de archivo
        foreach ($respuesta['headers'] as $header => $value) {
            header("$header: $value");
        }
        
        // Agregar BOM para UTF-8 (para Excel)
        echo "\xEF\xBB\xBF" . $respuesta['data'];
        
    } else {
        // Error en la exportación
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($respuesta['status_code']);
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en exportar_datos.php: " . $e->getMessage());
    
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error_code' => 'INTERNAL_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>