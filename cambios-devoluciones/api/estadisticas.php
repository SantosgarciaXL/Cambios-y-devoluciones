<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

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
            ['fecha' => date('Y-m-d', strtotime('-1 day')), 'cantidad' => 1]
        ]
    ]
], JSON_UNESCAPED_UNICODE);
?>