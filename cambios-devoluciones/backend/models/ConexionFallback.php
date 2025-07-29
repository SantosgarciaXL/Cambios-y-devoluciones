<?php
/**
 * Clase de conexión fallback con datos simulados
 * Para usar cuando no está disponible SQL Server
 */

class ConexionFallback {
    
    private $datosSumulados = [];
    
    public function __construct() {
        $this->inicializarDatos();
    }
    
    private function inicializarDatos() {
        $this->datosSumulados = [
            'solicitudes' => [
                [
                    'id' => 1,
                    'fecha_solicitud' => date('Y-m-d H:i:s'),
                    'fecha_recepcion_producto' => date('Y-m-d', strtotime('-5 days')),
                    'dias_transcurridos' => 5,
                    'cliente_nombre' => 'Juan Pérez',
                    'cliente_email' => 'juan@email.com',
                    'cliente_telefono' => '+54 11 1234-5678',
                    'numero_pedido' => 'PED001234',
                    'numero_factura' => 'FAC001234',
                    'codigo_producto' => 'ART001',
                    'descripcion_producto' => 'Remera deportiva',
                    'precio_producto' => 2500.00,
                    'cantidad_producto' => 1,
                    'medio_compra' => 'online',
                    'motivo_solicitud' => 'cambio',
                    'producto_usado' => false,
                    'tiene_etiquetas' => true,
                    'observaciones' => 'No me gusta el color',
                    'resultado_evaluacion' => 'Cambio Permitido',
                    'resultado_permitido' => true,
                    'resultado_tipo' => 'cambio',
                    'resultado_mensaje' => 'El producto cumple las condiciones para cambio',
                    'decision_final' => 'Aprobada',
                    'decision_fecha' => date('Y-m-d H:i:s'),
                    'decision_usuario' => 'Admin',
                    'estado_solicitud' => 'Activa',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                [
                    'id' => 2,
                    'fecha_solicitud' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'fecha_recepcion_producto' => date('Y-m-d', strtotime('-15 days')),
                    'dias_transcurridos' => 15,
                    'cliente_nombre' => 'María García',
                    'cliente_email' => 'maria@email.com',
                    'cliente_telefono' => '+54 11 9876-5432',
                    'numero_pedido' => 'PED001235',
                    'numero_factura' => 'FAC001235',
                    'codigo_producto' => 'ART002',
                    'descripcion_producto' => 'Pantalón jean',
                    'precio_producto' => 4500.00,
                    'cantidad_producto' => 1,
                    'medio_compra' => 'online',
                    'motivo_solicitud' => 'devolucion',
                    'producto_usado' => false,
                    'tiene_etiquetas' => true,
                    'observaciones' => 'Fuera del plazo legal',
                    'resultado_evaluacion' => 'Devolución No Permitida',
                    'resultado_permitido' => false,
                    'resultado_tipo' => 'devolucion_fuera_plazo',
                    'resultado_mensaje' => 'Han transcurrido 15 días. El plazo legal para devolución es de 10 días',
                    'decision_final' => 'Rechazada',
                    'decision_fecha' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'decision_usuario' => 'Admin',
                    'estado_solicitud' => 'Activa',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
                ],
                [
                    'id' => 3,
                    'fecha_solicitud' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'fecha_recepcion_producto' => date('Y-m-d', strtotime('-3 days')),
                    'dias_transcurridos' => 3,
                    'cliente_nombre' => 'Carlos López',
                    'cliente_email' => 'carlos@email.com',
                    'cliente_telefono' => '+54 11 5555-1234',
                    'numero_pedido' => 'PED001236',
                    'numero_factura' => 'FAC001236',
                    'codigo_producto' => 'ART003',
                    'descripcion_producto' => 'Zapatillas running',
                    'precio_producto' => 8500.00,
                    'cantidad_producto' => 1,
                    'medio_compra' => 'online',
                    'motivo_solicitud' => 'falla',
                    'producto_usado' => true,
                    'tiene_etiquetas' => false,
                    'observaciones' => 'Defecto en la suela',
                    'resultado_evaluacion' => 'Garantía por Falla',
                    'resultado_permitido' => true,
                    'resultado_tipo' => 'garantia',
                    'resultado_mensaje' => 'Producto con falla. Aplica garantía legal',
                    'decision_final' => 'Aprobada',
                    'decision_fecha' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'decision_usuario' => 'Admin',
                    'estado_solicitud' => 'Activa',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
                ]
            ]
        ];
    }
    
    public function listar($filtros = [], $limite = 50, $offset = 0) {
        $solicitudes = $this->datosSumulados['solicitudes'];
        
        // Aplicar filtros básicos
        if (!empty($filtros['medio_compra'])) {
            $solicitudes = array_filter($solicitudes, function($s) use ($filtros) {
                return $s['medio_compra'] === $filtros['medio_compra'];
            });
        }
        
        if (!empty($filtros['motivo_solicitud'])) {
            $solicitudes = array_filter($solicitudes, function($s) use ($filtros) {
                return $s['motivo_solicitud'] === $filtros['motivo_solicitud'];
            });
        }
        
        if (!empty($filtros['decision_final'])) {
            $solicitudes = array_filter($solicitudes, function($s) use ($filtros) {
                return $s['decision_final'] === $filtros['decision_final'];
            });
        }
        
        // Aplicar paginación
        $solicitudes = array_slice($solicitudes, $offset, $limite);
        
        return array_values($solicitudes);
    }
    
    public function obtenerEstadisticas($fechaDesde = null, $fechaHasta = null) {
        $solicitudes = $this->datosSumulados['solicitudes'];
        
        return [
            'total_solicitudes' => count($solicitudes),
            'solicitudes_hoy' => 1,
            'aprobadas' => 2,
            'rechazadas' => 1,
            'pendientes' => 0,
            'devoluciones' => 1,
            'cambios' => 1,
            'fallas' => 1,
            'online' => 3,
            'presencial' => 0,
            'promedio_dias' => 7.7,
            'tasa_aprobacion' => 66.7
        ];
    }
    
    public function obtenerTendencias($dias = 30) {
        return [
            ['fecha' => date('Y-m-d'), 'cantidad' => 1, 'aprobadas' => 1, 'rechazadas' => 0],
            ['fecha' => date('Y-m-d', strtotime('-1 day')), 'cantidad' => 1, 'aprobadas' => 0, 'rechazadas' => 1],
            ['fecha' => date('Y-m-d', strtotime('-2 days')), 'cantidad' => 1, 'aprobadas' => 1, 'rechazadas' => 0]
        ];
    }
    
    public function crear($datos) {
        // Simular creación
        $nuevoId = count($this->datosSumulados['solicitudes']) + 1;
        
        $nuevaSolicitud = array_merge($datos, [
            'id' => $nuevoId,
            'fecha_solicitud' => date('Y-m-d H:i:s'),
            'dias_transcurridos' => $this->calcularDias($datos['fecha_recepcion_producto']),
            'estado_solicitud' => 'Activa',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->datosSumulados['solicitudes'][] = $nuevaSolicitud;
        
        return $nuevoId;
    }
    
    public function obtenerPorId($id) {
        foreach ($this->datosSumulados['solicitudes'] as $solicitud) {
            if ($solicitud['id'] == $id) {
                return $solicitud;
            }
        }
        return null;
    }
    
    public function actualizarDecision($id, $decision, $usuario, $observaciones = null) {
        foreach ($this->datosSumulados['solicitudes'] as &$solicitud) {
            if ($solicitud['id'] == $id) {
                $solicitud['decision_final'] = $decision;
                $solicitud['decision_fecha'] = date('Y-m-d H:i:s');
                $solicitud['decision_usuario'] = $usuario;
                $solicitud['decision_observaciones'] = $observaciones;
                $solicitud['updated_at'] = date('Y-m-d H:i:s');
                return true;
            }
        }
        return false;
    }
    
    public function exportarCSV($filtros = []) {
        $solicitudes = $this->listar($filtros, 10000, 0);
        
        $csv = "ID,Fecha,Cliente,Pedido,Medio,Motivo,Resultado,Decision\n";
        
        foreach ($solicitudes as $s) {
            $csv .= sprintf(
                "%d,%s,\"%s\",\"%s\",%s,%s,%s,%s\n",
                $s['id'],
                $s['fecha_solicitud'],
                $s['cliente_nombre'] ?? '',
                $s['numero_pedido'] ?? '',
                $s['medio_compra'],
                $s['motivo_solicitud'],
                $s['resultado_permitido'] ? 'Permitido' : 'No Permitido',
                $s['decision_final'] ?? ''
            );
        }
        
        return $csv;
    }
    
    private function calcularDias($fechaRecepcion) {
        $fecha = new DateTime($fechaRecepcion);
        $hoy = new DateTime();
        return $hoy->diff($fecha)->days;
    }
}
?>