<?php
/**
 * Endpoint simple de prueba sin dependencias externas
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $action = $_GET['action'] ?? 'test';
    
    switch ($action) {
        case 'listar':
            // Datos simulados para testing
            $solicitudes = [
                [
                    'id' => 1,
                    'fecha_solicitud' => date('Y-m-d H:i:s'),
                    'cliente_nombre' => 'Cliente Prueba',
                    'numero_pedido' => 'TEST001',
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
                    'cliente_nombre' => 'Cliente Prueba 2',
                    'numero_pedido' => 'TEST002',
                    'medio_compra' => 'presencial',
                    'motivo_solicitud' => 'devolucion',
                    'producto_usado' => false,
                    'tiene_etiquetas' => true,
                    'resultado_permitido' => false,
                    'decision_final' => 'Rechazada'
                ]
            ];
            
            echo json_encode([
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
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'estadisticas':
            echo json_encode([
                'success' => true,
                'data' => [
                    'resumen' => [
                        'ultimos_30_dias' => [
                            'total_solicitudes' => 25,
                            'solicitudes_hoy' => 3,
                            'aprobadas' => 18,
                            'rechazadas' => 7,
                            'pendientes' => 0
                        ]
                    ],
                    'tendencias' => [
                        ['fecha' => date('Y-m-d'), 'cantidad' => 3],
                        ['fecha' => date('Y-m-d', strtotime('-1 day')), 'cantidad' => 2],
                        ['fecha' => date('Y-m-d', strtotime('-2 days')), 'cantidad' => 4]
                    ]
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'crear':
            $input = file_get_contents('php://input');
            $datos = json_decode($input, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON inválido');
            }
            
            // Validación básica
            if (empty($datos['fecha_recepcion_producto']) || 
                empty($datos['medio_compra']) || 
                empty($datos['motivo_solicitud'])) {
                throw new Exception('Faltan campos requeridos');
            }
            
            // Simular evaluación simple
            $permitido = true;
            $tipo = $datos['motivo_solicitud'];
            $mensaje = "Solicitud de {$tipo} procesada correctamente";
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'solicitud_id' => rand(1000, 9999),
                    'evaluacion' => [
                        'permitido' => $permitido,
                        'tipo' => $tipo,
                        'titulo' => $permitido ? 'Solicitud Aprobada' : 'Solicitud Rechazada',
                        'mensaje' => $mensaje,
                        'alertClass' => $permitido ? 'alert-success' : 'alert-danger',
                        'cardClass' => $permitido ? 'result-permitido' : 'result-no-permitido'
                    ]
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => true,
                'message' => 'API funcionando correctamente',
                'server_info' => [
                    'php_version' => phpversion(),
                    'server_time' => date('Y-m-d H:i:s'),
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'uri' => $_SERVER['REQUEST_URI']
                ]
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>