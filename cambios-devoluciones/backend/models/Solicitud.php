<?php
/**
 * Modelo Solicitud
 * Maneja las operaciones CRUD para solicitudes de cambios y devoluciones
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

class Solicitud {
    
    private $connection;
    
    public function __construct() {
        $this->connection = DatabaseConfig::getConnection();
    }
    
    public function __destruct() {
        DatabaseConfig::closeConnection($this->connection);
    }
    
    /**
     * Crear nueva solicitud
     */
    public function crear($datos) {
        try {
            $sql = "
                INSERT INTO solicitudes_cambios_devoluciones (
                    fecha_recepcion_producto,
                    cliente_nombre,
                    cliente_email,
                    cliente_telefono,
                    numero_pedido,
                    numero_factura,
                    codigo_producto,
                    descripcion_producto,
                    precio_producto,
                    cantidad_producto,
                    medio_compra,
                    motivo_solicitud,
                    producto_usado,
                    tiene_etiquetas,
                    observaciones,
                    resultado_evaluacion,
                    resultado_permitido,
                    resultado_tipo,
                    resultado_mensaje,
                    decision_final,
                    decision_fecha,
                    decision_usuario,
                    usuario_creacion
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?
                )
            ";
            
            $params = [
                $datos['fecha_recepcion_producto'],
                $datos['cliente_nombre'] ?? null,
                $datos['cliente_email'] ?? null,
                $datos['cliente_telefono'] ?? null,
                $datos['numero_pedido'] ?? null,
                $datos['numero_factura'] ?? null,
                $datos['codigo_producto'] ?? null,
                $datos['descripcion_producto'] ?? null,
                $datos['precio_producto'] ?? null,
                $datos['cantidad_producto'] ?? 1,
                $datos['medio_compra'],
                $datos['motivo_solicitud'],
                $datos['producto_usado'] ? 1 : 0,
                $datos['tiene_etiquetas'] ? 1 : 0,
                $datos['observaciones'] ?? null,
                $datos['resultado_evaluacion'] ?? null,
                $datos['resultado_permitido'] ? 1 : 0,
                $datos['resultado_tipo'] ?? null,
                $datos['resultado_mensaje'] ?? null,
                $datos['decision_final'] ?? null,
                $datos['decision_fecha'] ?? null,
                $datos['decision_usuario'] ?? null,
                $datos['usuario_creacion'] ?? null
            ];
            
            $result = DatabaseConfig::executeQuery($this->connection, $sql, $params);
            
            // Obtener el ID de la solicitud creada
            $sqlId = "SELECT SCOPE_IDENTITY() AS id";
            $resultId = DatabaseConfig::executeQuery($this->connection, $sqlId);
            $row = sqlsrv_fetch_array($resultId, SQLSRV_FETCH_ASSOC);
            
            $this->registrarSeguimiento($row['id'], null, 'Activa', 
                $datos['usuario_creacion'] ?? 'Sistema', 'Solicitud creada');
            
            return $row['id'];
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error al crear solicitud: ' . $e->getMessage(), $datos);
            throw $e;
        }
    }
    
    /**
     * Obtener solicitud por ID
     */
    public function obtenerPorId($id) {
        try {
            $sql = "SELECT * FROM solicitudes_cambios_devoluciones WHERE id = ? AND estado_solicitud = 'Activa'";
            $result = DatabaseConfig::executeQuery($this->connection, $sql, [$id]);
            
            if ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                return $this->formatearSolicitud($row);
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error al obtener solicitud: ' . $e->getMessage(), ['id' => $id]);
            throw $e;
        }
    }
    
    /**
     * Listar solicitudes con filtros
     */
    public function listar($filtros = [], $limite = 50, $offset = 0) {
        try {
            $whereConditions = ["estado_solicitud = 'Activa'"];
            $params = [];
            
            // Aplicar filtros
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
            
            if (!empty($filtros['decision_final'])) {
                $whereConditions[] = "decision_final = ?";
                $params[] = $filtros['decision_final'];
            }
            
            if (!empty($filtros['numero_pedido'])) {
                $whereConditions[] = "numero_pedido LIKE ?";
                $params[] = '%' . $filtros['numero_pedido'] . '%';
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
            
            $result = DatabaseConfig::executeQuery($this->connection, $sql, $params);
            
            $solicitudes = [];
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $solicitudes[] = $this->formatearSolicitud($row);
            }
            
            return $solicitudes;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error al listar solicitudes: ' . $e->getMessage(), $filtros);
            throw $e;
        }
    }
    
    /**
     * Actualizar decisión final de la solicitud
     */
    public function actualizarDecision($id, $decision, $usuario, $observaciones = null) {
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
            DatabaseConfig::executeQuery($this->connection, $sql, $params);
            
            $this->registrarSeguimiento($id, 'Pendiente', $decision, $usuario, $observaciones);
            
            return true;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error al actualizar decisión: ' . $e->getMessage(), 
                ['id' => $id, 'decision' => $decision]);
            throw $e;
        }
    }
    
    /**
     * Obtener estadísticas generales
     */
    public function obtenerEstadisticas($fechaDesde = null, $fechaHasta = null) {
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
                    COUNT(CASE WHEN decision_final IS NULL OR decision_final = 'Pendiente' THEN 1 END) AS pendientes,
                    COUNT(CASE WHEN motivo_solicitud = 'devolucion' THEN 1 END) AS devoluciones,
                    COUNT(CASE WHEN motivo_solicitud = 'cambio' THEN 1 END) AS cambios,
                    COUNT(CASE WHEN motivo_solicitud = 'falla' THEN 1 END) AS fallas,
                    COUNT(CASE WHEN medio_compra = 'online' THEN 1 END) AS online,
                    COUNT(CASE WHEN medio_compra = 'presencial' THEN 1 END) AS presencial,
                    AVG(CAST(dias_transcurridos AS FLOAT)) AS promedio_dias,
                    CASE 
                        WHEN COUNT(*) > 0 
                        THEN ROUND(COUNT(CASE WHEN decision_final = 'Aprobada' THEN 1 END) * 100.0 / COUNT(*), 2)
                        ELSE 0 
                    END AS tasa_aprobacion
                FROM solicitudes_cambios_devoluciones 
                {$whereClause}
            ";
            
            $result = DatabaseConfig::executeQuery($this->connection, $sql, $params);
            $estadisticas = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            
            return $estadisticas;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error al obtener estadísticas: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener tendencias por día para gráficos
     */
    public function obtenerTendencias($dias = 30) {
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
            
            $result = DatabaseConfig::executeQuery($this->connection, $sql, [$dias]);
            
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
            $this->log('ERROR', 'Error al obtener tendencias: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Registrar seguimiento de cambios
     */
    private function registrarSeguimiento($solicitudId, $estadoAnterior, $estadoNuevo, $usuario, $observaciones) {
        try {
            $sql = "
                INSERT INTO solicitudes_seguimiento (
                    solicitud_id, estado_anterior, estado_nuevo, usuario, observaciones
                ) VALUES (?, ?, ?, ?, ?)
            ";
            
            $params = [$solicitudId, $estadoAnterior, $estadoNuevo, $usuario, $observaciones];
            DatabaseConfig::executeQuery($this->connection, $sql, $params);
            
        } catch (Exception $e) {
            // No lanzar excepción para no interrumpir el flujo principal
            $this->log('WARNING', 'Error al registrar seguimiento: ' . $e->getMessage());
        }
    }
    
    /**
     * Formatear datos de solicitud para respuesta
     */
    private function formatearSolicitud($row) {
        return [
            'id' => (int)$row['id'],
            'fecha_solicitud' => $row['fecha_solicitud']->format('Y-m-d H:i:s'),
            'fecha_recepcion_producto' => $row['fecha_recepcion_producto']->format('Y-m-d'),
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
            'decision_fecha' => $row['decision_fecha'] ? $row['decision_fecha']->format('Y-m-d H:i:s') : null,
            'decision_usuario' => $row['decision_usuario'],
            'decision_observaciones' => $row['decision_observaciones'],
            'estado_solicitud' => $row['estado_solicitud'],
            'usuario_creacion' => $row['usuario_creacion'],
            'created_at' => $row['created_at']->format('Y-m-d H:i:s'),
            'updated_at' => $row['updated_at']->format('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Logging de errores y eventos
     */
    private function log($nivel, $mensaje, $contexto = []) {
        try {
            $sql = "
                INSERT INTO sistema_logs (nivel, mensaje, contexto, ip_address, user_agent, url)
                VALUES (?, ?, ?, ?, ?, ?)
            ";
            
            $params = [
                $nivel,
                $mensaje,
                json_encode($contexto),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $_SERVER['REQUEST_URI'] ?? null
            ];
            
            DatabaseConfig::executeQuery($this->connection, $sql, $params);
            
        } catch (Exception $e) {
            // Fallback a error_log si no se puede escribir en BD
            error_log("[$nivel] $mensaje - Context: " . json_encode($contexto));
        }
    }
    
    /**
     * Exportar solicitudes a CSV
     */
    public function exportarCSV($filtros = []) {
        try {
            $solicitudes = $this->listar($filtros, 10000, 0); // Máximo 10k registros
            
            $csv = "ID,Fecha Solicitud,Fecha Recepción,Días Transcurridos,Cliente,Email,Teléfono,Número Pedido,Medio Compra,Motivo,Producto Usado,Tiene Etiquetas,Resultado Permitido,Tipo Resultado,Decisión Final,Usuario Decisión,Observaciones\n";
            
            foreach ($solicitudes as $solicitud) {
                $csv .= sprintf(
                    "%d,%s,%s,%d,\"%s\",\"%s\",\"%s\",\"%s\",%s,%s,%s,%s,%s,%s,%s,\"%s\",\"%s\"\n",
                    $solicitud['id'],
                    $solicitud['fecha_solicitud'],
                    $solicitud['fecha_recepcion_producto'],
                    $solicitud['dias_transcurridos'],
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
            $this->log('ERROR', 'Error al exportar CSV: ' . $e->getMessage(), $filtros);
            throw $e;
        }
    }
    
    /**
     * Validar estructura de datos de entrada
     */
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
}
?>