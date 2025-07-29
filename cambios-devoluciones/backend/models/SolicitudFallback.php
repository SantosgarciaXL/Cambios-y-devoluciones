<?php
/**
 * Modelo Solicitud con Fallback
 * Funciona con datos simulados si no hay conexión a BD
 */

require_once __DIR__ . '/ConexionFallback.php';

class SolicitudFallback {
    
    private $fallbackData;
    private $usarFallback = false;
    
    public function __construct() {
        // Intentar usar la conexión real, si falla usar fallback
        try {
            if (class_exists('DatabaseConfig')) {
                require_once __DIR__ . '/../../config/database.php';
                $this->connection = DatabaseConfig::getConnection();
            } else {
                throw new Exception('DatabaseConfig no disponible');
            }
        } catch (Exception $e) {
            $this->usarFallback = true;
            $this->fallbackData = new ConexionFallback();
            error_log('Usando datos simulados: ' . $e->getMessage());
        }
    }
    
    public function crear($datos) {
        if ($this->usarFallback) {
            return $this->fallbackData->crear($datos);
        }
        
        // Código original de creación...
        try {
            $sql = "
                INSERT INTO solicitudes_cambios_devoluciones (
                    fecha_recepcion_producto, cliente_nombre, cliente_email, 
                    numero_pedido, medio_compra, motivo_solicitud,
                    producto_usado, tiene_etiquetas, observaciones,
                    resultado_evaluacion, resultado_permitido, resultado_tipo,
                    resultado_mensaje, usuario_creacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $params = [
                $datos['fecha_recepcion_producto'],
                $datos['cliente_nombre'] ?? null,
                $datos['cliente_email'] ?? null,
                $datos['numero_pedido'] ?? null,
                $datos['medio_compra'],
                $datos['motivo_solicitud'],
                $datos['producto_usado'] ? 1 : 0,
                $datos['tiene_etiquetas'] ? 1 : 0,
                $datos['observaciones'] ?? null,
                $datos['resultado_evaluacion'] ?? null,
                $datos['resultado_permitido'] ? 1 : 0,
                $datos['resultado_tipo'] ?? null,
                $datos['resultado_mensaje'] ?? null,
                $datos['usuario_creacion'] ?? 'Sistema'
            ];
            
            $result = sqlsrv_query($this->connection, $sql, $params);
            
            if ($result === false) {
                throw new Exception('Error en la consulta SQL');
            }
            
            // Obtener ID
            $sqlId = "SELECT SCOPE_IDENTITY() AS id";
            $resultId = sqlsrv_query($this->connection, $sqlId);
            $row = sqlsrv_fetch_array($resultId, SQLSRV_FETCH_ASSOC);
            
            return $row['id'];
            
        } catch (Exception $e) {
            error_log('Error en crear(): ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function listar($filtros = [], $limite = 50, $offset = 0) {
        if ($this->usarFallback) {
            return $this->fallbackData->listar($filtros, $limite, $offset);
        }
        
        // Código original de listado...
        try {
            $whereConditions = ["estado_solicitud = 'Activa'"];
            $params = [];
            
            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "CAST(fecha_solicitud AS DATE) >= ?";
                $params[] = $filtros['fecha_desde'];
            }
            
            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "CAST(fecha_solicitud AS DATE) <= ?";
                $params[] = $filtros['fecha_hasta'];
            }
            
            if (!empty($filtros['medio_compra'])) {
                $whereConditions[] = "medio_compra = ?";
                $params[] = $filtros['medio_compra'];
            }
            
            if (!empty($filtros['motivo_solicitud'])) {
                $whereConditions[] = "motivo_solicitud = ?";
                $params[] = $filtros['motivo_solicitud'];
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $sql = "
                SELECT * FROM solicitudes_cambios_devoluciones 
                WHERE {$whereClause}
                ORDER BY fecha_solicitud DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
            ";
            
            $params[] = $offset;
            $params[] = $limite;
            
            $result = sqlsrv_query($this->connection, $sql, $params);
            
            if ($result === false) {
                throw new Exception('Error en la consulta');
            }
            
            $solicitudes = [];
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $solicitudes[] = $this->formatearSolicitud($row);
            }
            
            return $solicitudes;
            
        } catch (Exception $e) {
            error_log('Error en listar(): ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function obtenerEstadisticas($fechaDesde = null, $fechaHasta = null) {
        if ($this->usarFallback) {
            return $this->fallbackData->obtenerEstadisticas($fechaDesde, $fechaHasta);
        }
        
        // Código original de estadísticas...
        try {
            $whereClause = "WHERE estado_solicitud = 'Activa'";
            $params = [];
            
            if ($fechaDesde) {
                $whereClause .= " AND CAST(fecha_solicitud AS DATE) >= ?";
                $params[] = $fechaDesde;
            }
            
            if ($fechaHasta) {
                $whereClause .= " AND CAST(fecha_solicitud AS DATE) <= ?";
                $params[] = $fechaHasta;
            }
            
            $sql = "
                SELECT 
                    COUNT(*) AS total_solicitudes,
                    COUNT(CASE WHEN CAST(fecha_solicitud AS DATE) = CAST(GETDATE() AS DATE) THEN 1 END) AS solicitudes_hoy,
                    COUNT(CASE WHEN decision_final = 'Aprobada' THEN 1 END) AS aprobadas,
                    COUNT(CASE WHEN decision_final = 'Rechazada' THEN 1 END) AS rechazadas,
                    COUNT(CASE WHEN decision_final IS NULL THEN 1 END) AS pendientes
                FROM solicitudes_cambios_devoluciones 
                {$whereClause}
            ";
            
            $result = sqlsrv_query($this->connection, $sql, $params);
            $estadisticas = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            
            return $estadisticas;
            
        } catch (Exception $e) {
            error_log('Error en obtenerEstadisticas(): ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function obtenerTendencias($dias = 30) {
        if ($this->usarFallback) {
            return $this->fallbackData->obtenerTendencias($dias);
        }
        
        // Código original de tendencias...
        try {
            $sql = "
                SELECT 
                    CAST(fecha_solicitud AS DATE) AS fecha,
                    COUNT(*) AS cantidad,
                    COUNT(CASE WHEN decision_final = 'Aprobada' THEN 1 END) AS aprobadas,
                    COUNT(CASE WHEN decision_final = 'Rechazada' THEN 1 END) AS rechazadas
                FROM solicitudes_cambios_devoluciones
                WHERE fecha_solicitud >= DATEADD(DAY, -?, GETDATE())
                AND estado_solicitud = 'Activa'
                GROUP BY CAST(fecha_solicitud AS DATE)
                ORDER BY fecha DESC
            ";
            
            $result = sqlsrv_query($this->connection, $sql, [$dias]);
            
            $tendencias = [];
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $tendencias[] = [
                    'fecha' => $row['fecha']->format('Y-m-d'),
                    'cantidad' => (int)$row['cantidad'],
                    'aprobadas' => (int)$row['aprobadas'],
                    'rechazadas' => (int)$row['rechazadas']
                ];
            }
            
            return $tendencias;
            
        } catch (Exception $e) {
            error_log('Error en obtenerTendencias(): ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function obtenerPorId($id) {
        if ($this->usarFallback) {
            return $this->fallbackData->obtenerPorId($id);
        }
        
        // Código original...
        try {
            $sql = "SELECT * FROM solicitudes_cambios_devoluciones WHERE id = ?";
            $result = sqlsrv_query($this->connection, $sql, [$id]);
            
            if ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                return $this->formatearSolicitud($row);
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log('Error en obtenerPorId(): ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function actualizarDecision($id, $decision, $usuario, $observaciones = null) {
        if ($this->usarFallback) {
            return $this->fallbackData->actualizarDecision($id, $decision, $usuario, $observaciones);
        }
        
        try {
            $sql = "
                UPDATE solicitudes_cambios_devoluciones 
                SET decision_final = ?, 
                    decision_fecha = GETDATE(), 
                    decision_usuario = ?, 
                    decision_observaciones = ?,
                    updated_at = GETDATE()
                WHERE id = ?
            ";
            
            $params = [$decision, $usuario, $observaciones, $id];
            $result = sqlsrv_query($this->connection, $sql, $params);
            
            if ($result === false) {
                throw new Exception('Error al actualizar decisión');
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error en actualizarDecision(): ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function exportarCSV($filtros = []) {
        if ($this->usarFallback) {
            return $this->fallbackData->exportarCSV($filtros);
        }
        
        try {
            $solicitudes = $this->listar($filtros, 10000, 0);
            
            $csv = "ID,Fecha Solicitud,Fecha Recepción,Cliente,Email,Teléfono,Número Pedido,Medio Compra,Motivo,Producto Usado,Tiene Etiquetas,Resultado Permitido,Tipo Resultado,Decisión Final,Usuario Decisión,Observaciones\n";
            
            foreach ($solicitudes as $solicitud) {
                $csv .= sprintf(
                    "%d,%s,%s,\"%s\",\"%s\",\"%s\",\"%s\",%s,%s,%s,%s,%s,%s,%s,\"%s\",\"%s\"\n",
                    $solicitud['id'],
                    $solicitud['fecha_solicitud'],
                    $solicitud['fecha_recepcion_producto'],
                    $solicitud['cliente_nombre'] ?? '',
                    $solicitud['cliente_email'] ?? '',
                    $solicitud['cliente_telefono'] ?? '',
                    $solicitud['numero_pedido'] ?? '',
                    $solicitud['medio_compra'],
                    $solicitud['motivo_solicitud'],
                    $solicitud['producto_usado'] ? 'Sí' : 'No',
                    $solicitud['tiene_etiquetas'] ? 'Sí' : 'No',
                    $solicitud['resultado_permitido'] ? 'Sí' : 'No',
                    $solicitud['resultado_tipo'] ?? '',
                    $solicitud['decision_final'] ?? '',
                    $solicitud['decision_usuario'] ?? '',
                    str_replace('"', '""', $solicitud['observaciones'] ?? '')
                );
            }
            
            return $csv;
            
        } catch (Exception $e) {
            error_log('Error en exportarCSV(): ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function validarDatos($datos) {
        $errores = [];
        
        // Campos requeridos
        $camposRequeridos = ['fecha_recepcion_producto', 'medio_compra', 'motivo_solicitud'];
        foreach ($camposRequeridos as $campo) {
            if (empty($datos[$campo])) {
                $errores[] = "El campo {$campo} es requerido";
            }
        }
        
        // Validar fecha de recepción
        if (!empty($datos['fecha_recepcion_producto'])) {
            $fecha = DateTime::createFromFormat('Y-m-d', $datos['fecha_recepcion_producto']);
            if (!$fecha || $fecha->format('Y-m-d') !== $datos['fecha_recepcion_producto']) {
                $errores[] = "Formato de fecha inválido en fecha_recepcion_producto";
            } elseif ($fecha > new DateTime()) {
                $errores[] = "La fecha de recepción no puede ser futura";
            }
        }
        
        // Validar enums
        $mediosValidos = ['online', 'presencial'];
        if (!empty($datos['medio_compra']) && !in_array($datos['medio_compra'], $mediosValidos)) {
            $errores[] = "Medio de compra inválido";
        }
        
        $motivosValidos = ['cambio', 'devolucion', 'falla'];
        if (!empty($datos['motivo_solicitud']) && !in_array($datos['motivo_solicitud'], $motivosValidos)) {
            $errores[] = "Motivo de solicitud inválido";
        }
        
        // Validar email si está presente
        if (!empty($datos['cliente_email']) && !filter_var($datos['cliente_email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = "Email inválido";
        }
        
        return $errores;
    }
    
    private function formatearSolicitud($row) {
        if ($this->usarFallback) {
            return $row; // Los datos simulados ya están formateados
        }
        
        return [
            'id' => (int)$row['id'],
            'fecha_solicitud' => $row['fecha_solicitud'] instanceof DateTime ? 
                $row['fecha_solicitud']->format('Y-m-d H:i:s') : $row['fecha_solicitud'],
            'fecha_recepcion_producto' => $row['fecha_recepcion_producto'] instanceof DateTime ? 
                $row['fecha_recepcion_producto']->format('Y-m-d') : $row['fecha_recepcion_producto'],
            'dias_transcurridos' => (int)$row['dias_transcurridos'],
            'cliente_nombre' => $row['cliente_nombre'],
            'cliente_email' => $row['cliente_email'],
            'cliente_telefono' => $row['cliente_telefono'],
            'numero_pedido' => $row['numero_pedido'],
            'numero_factura' => $row['numero_factura'],
            'codigo_producto' => $row['codigo_producto'],
            'descripcion_producto' => $row['descripcion_producto'],
            'precio_producto' => $row['precio_producto'] ? (float)$row['precio_producto'] : null,
            'cantidad_producto' => (int)$row['cantidad_producto'],
            'medio_compra' => $row['medio_compra'],
            'motivo_solicitud' => $row['motivo_solicitud'],
            'producto_usado' => (bool)$row['producto_usado'],
            'tiene_etiquetas' => (bool)$row['tiene_etiquetas'],
            'observaciones' => $row['observaciones'],
            'resultado_evaluacion' => $row['resultado_evaluacion'],
            'resultado_permitido' => (bool)$row['resultado_permitido'],
            'resultado_tipo' => $row['resultado_tipo'],
            'resultado_mensaje' => $row['resultado_mensaje'],
            'decision_final' => $row['decision_final'],
            'decision_fecha' => $row['decision_fecha'] instanceof DateTime ? 
                $row['decision_fecha']->format('Y-m-d H:i:s') : $row['decision_fecha'],
            'decision_usuario' => $row['decision_usuario'],
            'decision_observaciones' => $row['decision_observaciones'],
            'estado_solicitud' => $row['estado_solicitud'],
            'created_at' => $row['created_at'] instanceof DateTime ? 
                $row['created_at']->format('Y-m-d H:i:s') : $row['created_at'],
            'updated_at' => $row['updated_at'] instanceof DateTime ? 
                $row['updated_at']->format('Y-m-d H:i:s') : $row['updated_at']
        ];
    }
    
    public function __destruct() {
        if (!$this->usarFallback && $this->connection) {
            sqlsrv_close($this->connection);
        }
    }
}
?>