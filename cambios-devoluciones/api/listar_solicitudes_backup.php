<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

echo json_encode([
    "success" => true,
    "data" => [
        "solicitudes" => [
            [
                "id" => 1,
                "fecha_solicitud" => "2025-07-30 15:20:38",
                "cliente_nombre" => "Cliente Demo",
                "numero_pedido" => "DEMO001",
                "medio_compra" => "online",
                "motivo_solicitud" => "cambio",
                "producto_usado" => false,
                "tiene_etiquetas" => true,
                "resultado_permitido" => true,
                "decision_final" => "Aprobada"
            ]
        ],
        "estadisticas" => [
            "total_solicitudes" => 1,
            "solicitudes_hoy" => 1,
            "aprobadas" => 1,
            "rechazadas" => 0
        ]
    ],
    "message" => "Datos simulados (modo fallback)",
    "timestamp" => "2025-07-30 15:20:38"
]);
?>