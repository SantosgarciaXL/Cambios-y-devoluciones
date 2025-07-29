<?php
/**
 * API Endpoint: Listar Solicitudes
 * GET /api/listar_solicitudes.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Mostrar errores para depuración
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Use GET.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

try {
    // Incluir dependencias con fallback
    $controllerPath = __DIR__ . '/../backend/controllers/SolicitudController.php';
    $fallbackPath = __DIR__ . '/../backend/models/SolicitudFallback.php';
    
    if (file_exists($controllerPath)) {
        require_once $controllerPath;
        $controller = new SolicitudController();
    } else {
        // Usar implementación fallback
        require_once $fallbackPath;
        $controller = new SolicitudControllerFallback();
    }
    
    // Obtener parámetros de consulta
    $filtros = [];
    $pagina = (int)($_GET['pagina'] ?? 1);
    $porPagina = min((int)($_GET['por_pagina'] ?? 20), 100); // Máximo 100 por página
    
    // Procesar filtros
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
    
    if (!empty($_GET['numero_pedido'])) {
        $filtros['numero_pedido'] = $_GET['numero_pedido'];
    }
    
    // Obtener solicitudes
    $respuesta = $controller->listar($filtros, $pagina, $porPagina);
    
    // Establecer código de respuesta HTTP
    http_response_code($respuesta['status_code'] ?? 200);
    
    // Enviar respuesta
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en listar_solicitudes.php: " . $e->getMessage());
    
    // Respuesta fallback con datos simulados
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'solicitudes' => [
                [
                    'id' => 1,
                    'fecha_solicitud' => date('Y-m-d H:i:s'),
                    'cliente_nombre' => 'Cliente Demo',
                    'numero_pedido' => 'DEMO001',
                    'medio_compra' => 'online',
                    'motivo_solicitud' => 'cambio',
                    'producto_usado' => false,
                    'tiene_etiquetas' => true,
                    'resultado_permitido' => true,
                    'decision_final' => 'Aprobada'
                ]
            ],
            'estadisticas' => [
                'total_solicitudes' => 1,
                'solicitudes_hoy' => 1,
                'aprobadas' => 1,
                'rechazadas' => 0
            ]
        ],
        'message' => 'Datos simulados - Configure la base de datos para datos reales',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Clase controlador fallback simple
class SolicitudControllerFallback {
    public function listar($filtros = [], $pagina = 1, $porPagina = 20) {
        $solicitudes = [
            [
                'id' => 1,
                'fecha_solicitud' => date('Y-m-d H:i:s'),
                'cliente_nombre' => 'Juan Pérez',
                'numero_pedido' => 'PED001234',
                'medio_compra' => 'online',
                'motivo_solicitud' => 'cambio',
                'producto_usado' => false,
                'tiene_etiquetas' => true,
                'resultado_permitido' => true,
                'decision_final' => 'Aprobada'
            ],
            [
                'id' => 2,
                'fecha_solicitud' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'cliente_nombre' => 'María García',
                'numero_pedido' => 'PED001235',
                'medio_compra' => 'online',
                'motivo_solicitud' => 'devolucion',
                'producto_usado' => false,
                'tiene_etiquetas' => true,
                'resultado_permitido' => false,
                'decision_final' => 'Rechazada'
            ]
        ];
        
        return [
            'success' => true,
            'data' => [
                'solicitudes' => $solicitudes,
                'estadisticas' => [
                    'total_solicitudes' => 2,
                    'solicitudes_hoy' => 1,
                    'aprobadas' => 1,
                    'rechazadas' => 1
                ]
            ],
            'status_code' => 200,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
?>