<?php
/**
 * API Endpoint: Crear Solicitud
 * POST /api/crear_solicitud.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Use POST.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

try {
    // Incluir dependencias
    require_once __DIR__ . '/../backend/controllers/SolicitudController.php';
    
    // Obtener datos del request
    $input = file_get_contents('php://input');
    $datos = json_decode($input, true);
    
    // Validar JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'JSON inválido: ' . json_last_error_msg(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
    
    // Validar que se enviaron datos
    if (empty($datos)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No se enviaron datos',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
    
    // Agregar información de tracking
    $datos['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
    $datos['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $datos['usuario_creacion'] = $datos['usuario_creacion'] ?? 'API';
    
    // Crear controlador y procesar solicitud
    $controller = new SolicitudController();
    
    // Determinar si es evaluación preview o creación completa
    $esPreview = isset($datos['preview']) && $datos['preview'] === true;
    
    if ($esPreview) {
        $respuesta = $controller->evaluarPreview($datos);
    } else {
        $respuesta = $controller->crear($datos);
    }
    
    // Establecer código de respuesta HTTP
    http_response_code($respuesta['status_code']);
    
    // Enviar respuesta
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en crear_solicitud.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error_code' => 'INTERNAL_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>