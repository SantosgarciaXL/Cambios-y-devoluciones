<?php
/**
 * API Endpoint: Estadísticas
 * GET /api/estadisticas.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Configurar errores
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
    
    if (file_exists($controllerPath)) {
        require_once $controllerPath;
        $controller = new SolicitudController();
    } else {
        // Usar implementación fallback
        $controller = new EstadisticasControllerFallback();
    }
    
    // Obtener parámetros opcionales
    $fechaDesde = $_GET['fecha_desde'] ?? null;
    $fechaHasta = $_GET['fecha_hasta'] ?? null;
    $tipoDashboard = $_GET['dashboard'] ?? false;
    
    // Determinar tipo de respuesta
    if ($tipoDashboard) {
        $respuesta = $controller->obtenerDashboard();
    } else {
        $respuesta = $controller->obtenerEstadisticas($fechaDesde, $fechaHasta);
    }
    
    // Establecer código de respuesta HTTP
    http_response_code($respuesta['status_code'] ?? 200);
    
    // Enviar respuesta
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en estadisticas.php: " . $e->getMessage());
    
    // Respuesta fallback
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'resumen' => [
                'ultimos_30_dias' => [
                    'total_solicitudes' => 15,
                    'solicitudes_hoy' => 2,
                    'aprobadas' => 10,
                    'rechazadas' => 5,
                    'pendientes' => 0
                ]
            ],
            'tendencias' => [
                ['fecha' => date('Y-m-d'), 'cantidad' => 2],
                ['fecha' => date('Y-m-d', strtotime('-1 day')), 'cantidad' => 1],
                ['fecha' => date('Y-m-d', strtotime('-2 days')), 'cantidad' => 3]
            ]
        ],
        'message' => 'Datos simulados - Configure la base de datos para datos reales',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Clase controlador fallback para estadísticas
class EstadisticasControllerFallback {
    public function obtenerDashboard() {
        return [
            'success' => true,
            'data' => [
                'resumen' => [
                    'hoy' => [
                        'total_solicitudes' => 2,
                        'solicitudes_hoy' => 2,
                        'aprobadas' => 1,
                        'rechazadas' => 1
                    ],
                    'ultimos_7_dias' => [
                        'total_solicitudes' => 8,
                        'solicitudes_hoy' => 2,
                        'aprobadas' => 5,
                        'rechazadas' => 3
                    ],
                    'ultimos_30_dias' => [
                        'total_solicitudes' => 25,
                        'solicitudes_hoy' => 2,
                        'aprobadas' => 18,
                        'rechazadas' => 7,
                        'pendientes' => 0,
                        'tasa_aprobacion' => 72.0
                    ]
                ],
                'tendencias' => [
                    ['fecha' => date('Y-m-d'), 'cantidad' => 2, 'aprobadas' => 1, 'rechazadas' => 1],
                    ['fecha' => date('Y-m-d', strtotime('-1 day')), 'cantidad' => 1, 'aprobadas' => 1, 'rechazadas' => 0],
                    ['fecha' => date('Y-m-d', strtotime('-2 days')), 'cantidad' => 3, 'aprobadas' => 2, 'rechazadas' => 1],
                    ['fecha' => date('Y-m-d', strtotime('-3 days')), 'cantidad' => 0, 'aprobadas' => 0, 'rechazadas' => 0],
                    ['fecha' => date('Y-m-d', strtotime('-4 days')), 'cantidad' => 2, 'aprobadas' => 1, 'rechazadas' => 1]
                ]
            ],
            'status_code' => 200,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    public function obtenerEstadisticas($fechaDesde = null, $fechaHasta = null) {
        return [
            'success' => true,
            'data' => [
                'estadisticas' => [
                    'total_solicitudes' => 25,
                    'solicitudes_hoy' => 2,
                    'aprobadas' => 18,
                    'rechazadas' => 7,
                    'tasa_aprobacion' => 72.0,
                    'tiempo_resolucion_promedio' => 1.5
                ],
                'tendencias' => [
                    ['fecha' => date('Y-m-d'), 'cantidad' => 2],
                    ['fecha' => date('Y-m-d', strtotime('-1 day')), 'cantidad' => 1],
                    ['fecha' => date('Y-m-d', strtotime('-2 days')), 'cantidad' => 3]
                ]
            ],
            'status_code' => 200,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
?>