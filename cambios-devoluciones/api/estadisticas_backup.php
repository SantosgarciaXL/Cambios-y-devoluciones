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
        "resumen" => [
            "ultimos_30_dias" => [
                "total_solicitudes" => 15,
                "solicitudes_hoy" => 2,
                "aprobadas" => 10,
                "rechazadas" => 5,
                "pendientes" => 0
            ]
        ],
        "tendencias" => [
            ["fecha" => "2025-07-30", "cantidad" => 2],
            ["fecha" => "2025-07-29", "cantidad" => 1],
            ["fecha" => "2025-07-28", "cantidad" => 3]
        ]
    ],
    "message" => "Estadísticas simuladas (modo fallback)",
    "timestamp" => "2025-07-30 15:20:38"
]);
?>