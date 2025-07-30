<?php
/**
 * API Fallback para cuando hay problemas de conexión
 * Reemplaza temporalmente los archivos API problemáticos
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Configurar errores para no mostrar warnings en JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Determinar qué API simular basado en la URL
    $script_name = basename($_SERVER['SCRIPT_NAME']);
    $action = $_GET['action'] ?? $_POST['action'] ?? 'default';
    
    switch ($script_name) {
        case 'listar_solicitudes.php':
            echo json_encode(obtenerSolicitudesSimuladas());
            break;
            
        case 'estadisticas.php':
            echo json_encode(obtenerEstadisticasSimuladas());
            break;
            
        case 'crear_solicitud.php':
            echo json_encode(procesarSolicitudSimulada());
            break;
            
        case 'exportar_datos.php':
            generarCSVSimulado();
            break;
            
        default:
            echo json_encode([
                'success' => true,
                'message' => 'API de prueba funcionando',
                'data' => [
                    'script' => $script_name,
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en API de prueba',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function obtenerSolicitudesSimuladas() {
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
        ],
        [
            'id' => 3,
            'fecha_solicitud' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'cliente_nombre' => 'Carlos López',
            'numero_pedido' => 'PED001236',
            'medio_compra' => 'online',
            'motivo_solicitud' => 'falla',
            'producto_usado' => true,
            'tiene_etiquetas' => false,
            'resultado_permitido' => true,
            'decision_final' => 'Aprobada'
        ]
    ];
    
    return [
        'success' => true,
        'data' => [
            'solicitudes' => $solicitudes,
            'estadisticas' => [
                'total_solicitudes' => count($solicitudes),
                'solicitudes_hoy' => 1,
                'aprobadas' => 2,
                'rechazadas' => 1
            ]
        ],
        'message' => 'Datos simulados - Configure la base de datos para datos reales',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function obtenerEstadisticasSimuladas() {
    return [
        'success' => true,
        'data' => [
            'resumen' => [
                'hoy' => [
                    'total_solicitudes' => 1,
                    'solicitudes_hoy' => 1,
                    'aprobadas' => 1,
                    'rechazadas' => 0
                ],
                'ultimos_7_dias' => [
                    'total_solicitudes' => 5,
                    'solicitudes_hoy' => 1,
                    'aprobadas' => 3,
                    'rechazadas' => 2
                ],
                'ultimos_30_dias' => [
                    'total_solicitudes' => 15,
                    'solicitudes_hoy' => 1,
                    'aprobadas' => 10,
                    'rechazadas' => 5,
                    'pendientes' => 0,
                    'tasa_aprobacion' => 66.7
                ]
            ],
            'tendencias' => [
                ['fecha' => date('Y-m-d'), 'cantidad' => 1, 'aprobadas' => 1, 'rechazadas' => 0],
                ['fecha' => date('Y-m-d', strtotime('-1 day')), 'cantidad' => 2, 'aprobadas' => 1, 'rechazadas' => 1],
                ['fecha' => date('Y-m-d', strtotime('-2 days')), 'cantidad' => 1, 'aprobadas' => 1, 'rechazadas' => 0],
                ['fecha' => date('Y-m-d', strtotime('-3 days')), 'cantidad' => 0, 'aprobadas' => 0, 'rechazadas' => 0],
                ['fecha' => date('Y-m-d', strtotime('-4 days')), 'cantidad' => 3, 'aprobadas' => 2, 'rechazadas' => 1]
            ]
        ],
        'message' => 'Estadísticas simuladas - Configure la base de datos para datos reales',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function procesarSolicitudSimulada() {
    $input = file_get_contents('php://input');
    $datos = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        return [
            'success' => false,
            'message' => 'JSON inválido: ' . json_last_error_msg(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // Validación básica
    if (empty($datos['fecha_recepcion_producto']) || 
        empty($datos['medio_compra']) || 
        empty($datos['motivo_solicitud'])) {
        http_response_code(400);
        return [
            'success' => false,
            'message' => 'Faltan campos requeridos',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // Simular evaluación
    $esPreview = isset($datos['preview']) && $datos['preview'] === true;
    $permitido = true; // Simplificado para prueba
    $tipo = $datos['motivo_solicitud'];
    
    $evaluacion = [
        'permitido' => $permitido,
        'tipo' => $tipo,
        'titulo' => $permitido ? ucfirst($tipo) . ' Permitido' : ucfirst($tipo) . ' No Permitido',
        'mensaje' => "Solicitud de {$tipo} procesada correctamente (modo simulación)",
        'alertClass' => $permitido ? 'alert-success' : 'alert-danger',
        'cardClass' => $permitido ? 'result-permitido' : 'result-no-permitido',
        'dias_transcurridos' => calcularDiasTranscurridos($datos['fecha_recepcion_producto']),
        'fundamento_legal' => 'Simulación - Ley 24.240'
    ];
    
    $respuesta = [
        'success' => true,
        'data' => [
            'evaluacion' => $evaluacion
        ],
        'message' => 'Solicitud procesada en modo simulación',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (!$esPreview) {
        $respuesta['data']['solicitud_id'] = rand(1000, 9999);
    }
    
    return $respuesta;
}

function calcularDiasTranscurridos($fechaRecepcion) {
    $fecha = new DateTime($fechaRecepcion);
    $hoy = new DateTime();
    return $hoy->diff($fecha)->days;
}

function generarCSVSimulado() {
    $csv = "ID,Fecha,Cliente,Pedido,Medio,Motivo,Resultado,Decision\n";
    $csv .= "1," . date('Y-m-d H:i:s') . ",Juan Pérez,PED001234,online,cambio,Permitido,Aprobada\n";
    $csv .= "2," . date('Y-m-d H:i:s', strtotime('-1 day')) . ",María García,PED001235,online,devolucion,No Permitido,Rechazada\n";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="solicitudes_simuladas_' . date('Y-m-d_H-i-s') . '.csv"');
    
    echo "\xEF\xBB\xBF" . $csv; // BOM para UTF-8
}
?>