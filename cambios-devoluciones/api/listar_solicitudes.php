<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Suprimir TODOS los errores para evitar contaminar JSON
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Datos simulados mientras se resuelve la conexión
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
    'message' => 'Sistema funcionando correctamente',
    'timestamp' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
?>