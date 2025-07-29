<?php
/**
 * Controlador de Solicitudes
 * Maneja las operaciones principales del sistema
 */

require_once __DIR__ . '/../models/Solicitud.php';
require_once __DIR__ . '/../services/ValidacionService.php';

class SolicitudController {
    
    private $solicitudModel;
    private $validacionService;
    
    public function __construct() {
        $this->solicitudModel = new Solicitud();
        $this->validacionService = new ValidacionService();
    }
    
    /**
     * Crear nueva solicitud
     */
    public function crear($datos) {
        try {
            // Validar datos de entrada
            $errores = $this->validacionService->validarSolicitud($datos);
            if (!empty($errores)) {
                return $this->respuestaError('Datos inválidos', $errores, 400);
            }
            
            // Evaluar solicitud según reglas de negocio
            $evaluacion = $this->validacionService->evaluarSolicitud($datos);
            
            // Preparar datos para inserción
            $datosSolicitud = array_merge($datos, [
                'resultado_evaluacion' => $evaluacion['titulo'],
                'resultado_permitido' => $evaluacion['permitido'],
                'resultado_tipo' => $evaluacion['tipo'],
                'resultado_mensaje' => $evaluacion['mensaje'],
                'usuario_creacion' => $datos['usuario_creacion'] ?? 'Sistema'
            ]);
            
            // Crear solicitud en base de datos
            $solicitudId = $this->solicitudModel->crear($datosSolicitud);
            
            // Generar reporte de evaluación
            $reporte = $this->validacionService->generarReporteEvaluacion($datos, $evaluacion);
            
            return $this->respuestaExito('Solicitud creada exitosamente', [
                'solicitud_id' => $solicitudId,
                'evaluacion' => $evaluacion,
                'reporte' => $reporte
            ], 201);
            
        } catch (Exception $e) {
            return $this->respuestaError('Error interno del servidor', [$e->getMessage()], 500);
        }
    }
    
    /**
     * Obtener solicitud por ID
     */
    public function obtenerPorId($id) {
        try {
            $solicitud = $this->solicitudModel->obtenerPorId($id);
            
            if (!$solicitud) {
                return $this->respuestaError('Solicitud no encontrada', [], 404);
            }
            
            return $this->respuestaExito('Solicitud obtenida', $solicitud);
            
        } catch (Exception $e) {
            return $this->respuestaError('Error interno del servidor', [$e->getMessage()], 500);
        }
    }
    
    /**
     * Listar solicitudes con filtros y paginación
     */
    public function listar($filtros = [], $pagina = 1, $porPagina = 20) {
        try {
            $offset = ($pagina - 1) * $porPagina;
            $solicitudes = $this->solicitudModel->listar($filtros, $porPagina, $offset);
            
            // Obtener estadísticas para contexto
            $estadisticas = $this->solicitudModel->obtenerEstadisticas(
                $filtros['fecha_desde'] ?? null,
                $filtros['fecha_hasta'] ?? null
            );
            
            return $this->respuestaExito('Solicitudes obtenidas', [
                'solicitudes' => $solicitudes,
                'estadisticas' => $estadisticas,
                'paginacion' => [
                    'pagina_actual' => $pagina,
                    'por_pagina' => $porPagina,
                    'total_registros' => count($solicitudes)
                ]
            ]);
            
        } catch (Exception $e) {
            return $this->respuestaError('Error interno del servidor', [$e->getMessage()], 500);
        }
    }
    
    /**
     * Actualizar decisión final de solicitud
     */
    public function actualizarDecision($id, $decision, $usuario, $observaciones = null) {
        try {
            // Validar solicitud existe
            $solicitud = $this->solicitudModel->obtenerPorId($id);
            if (!$solicitud) {
                return $this->respuestaError('Solicitud no encontrada', [], 404);
            }
            
            // Validar decisión
            $decisionesValidas = ['Aprobada', 'Rechazada'];
            if (!in_array($decision, $decisionesValidas)) {
                return $this->respuestaError('Decisión inválida', [], 400);
            }
            
            // Actualizar decisión
            $this->solicitudModel->actualizarDecision($id, $decision, $usuario, $observaciones);
            
            // Obtener solicitud actualizada
            $solicitudActualizada = $this->solicitudModel->obtenerPorId($id);
            
            return $this->respuestaExito('Decisión actualizada exitosamente', $solicitudActualizada);
            
        } catch (Exception $e) {
            return $this->respuestaError('Error interno del servidor', [$e->getMessage()], 500);
        }
    }
    
    /**
     * Obtener estadísticas del sistema
     */
    public function obtenerEstadisticas($fechaDesde = null, $fechaHasta = null) {
        try {
            $estadisticas = $this->solicitudModel->obtenerEstadisticas($fechaDesde, $fechaHasta);
            $tendencias = $this->solicitudModel->obtenerTendencias(30);
            
            // Información adicional de reglas
            $informacionPlazos = $this->validacionService->obtenerInformacionPlazos();
            
            return $this->respuestaExito('Estadísticas obtenidas', [
                'estadisticas' => $estadisticas,
                'tendencias' => $tendencias,
                'plazos_vigentes' => $informacionPlazos,
                'fecha_consulta' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            return $this->respuestaError('Error interno del servidor', [$e->getMessage()], 500);
        }
    }
    
    /**
     * Evaluar solicitud sin guardar (preview)
     */
    public function evaluarPreview($datos) {
        try {
            // Validar datos básicos
            $errores = $this->validacionService->validarSolicitud($datos);
            if (!empty($errores)) {
                return $this->respuestaError('Datos inválidos', $errores, 400);
            }
            
            // Evaluar solicitud
            $evaluacion = $this->validacionService->evaluarSolicitud($datos);
            
            // Generar reporte detallado
            $reporte = $this->validacionService->generarReporteEvaluacion($datos, $evaluacion);
            
            return $this->respuestaExito('Evaluación completada', [
                'evaluacion' => $evaluacion,
                'reporte' => $reporte,
                'es_preview' => true
            ]);
            
        } catch (Exception $e) {
            return $this->respuestaError('Error interno del servidor', [$e->getMessage()], 500);
        }
    }
    
    /**
     * Exportar solicitudes a CSV
     */
    public function exportarCSV($filtros = []) {
        try {
            $csv = $this->solicitudModel->exportarCSV($filtros);
            
            return [
                'success' => true,
                'data' => $csv,
                'filename' => 'solicitudes_' . date('Y-m-d_H-i-s') . '.csv',
                'content_type' => 'text/csv',
                'headers' => [
                    'Content-Type' => 'text/csv; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="solicitudes_' . date('Y-m-d_H-i-s') . '.csv"'
                ]
            ];
            
        } catch (Exception $e) {
            return $this->respuestaError('Error al exportar datos', [$e->getMessage()], 500);
        }
    }
    
    /**
     * Obtener dashboard de métricas principales
     */
    public function obtenerDashboard() {
        try {
            $hoy = date('Y-m-d');
            $hace7Dias = date('Y-m-d', strtotime('-7 days'));
            $hace30Dias = date('Y-m-d', strtotime('-30 days'));
            
            // Estadísticas principales
            $estadisticasHoy = $this->solicitudModel->obtenerEstadisticas($hoy, $hoy);
            $estadisticas7Dias = $this->solicitudModel->obtenerEstadisticas($hace7Dias, $hoy);
            $estadisticas30Dias = $this->solicitudModel->obtenerEstadisticas($hace30Dias, $hoy);
            
            // Tendencias
            $tendencias = $this->solicitudModel->obtenerTendencias(30);
            
            return $this->respuestaExito('Dashboard obtenido', [
                'resumen' => [
                    'hoy' => $estadisticasHoy,
                    'ultimos_7_dias' => $estadisticas7Dias,
                    'ultimos_30_dias' => $estadisticas30Dias
                ],
                'tendencias' => $tendencias,
                'fecha_actualizacion' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            return $this->respuestaError('Error interno del servidor', [$e->getMessage()], 500);
        }
    }
    
    /**
     * Obtener información de configuración para el frontend
     */
    public function obtenerConfiguracion() {
        try {
            $plazos = $this->validacionService->obtenerInformacionPlazos();
            
            return $this->respuestaExito('Configuración obtenida', [
                'plazos' => $plazos,
                'opciones' => [
                    'medios_compra' => ['online', 'presencial'],
                    'motivos_solicitud' => ['cambio', 'devolucion', 'falla'],
                    'decisiones_finales' => ['Aprobada', 'Rechazada']
                ],
                'validaciones' => [
                    'fecha_maxima' => date('Y-m-d'),
                    'require_tags_for_return' => true,
                    'require_unused_for_return' => true
                ]
            ]);
            
        } catch (Exception $e) {
            return $this->respuestaError('Error interno del servidor', [$e->getMessage()], 500);
        }
    }
    
    /**
     * Procesar solicitud completa (evaluar + crear + decidir)
     */
    public function procesarCompleta($datos) {
        try {
            // Validar datos
            $errores = $this->validacionService->validarSolicitud($datos);
            if (!empty($errores)) {
                return $this->respuestaError('Datos inválidos', $errores, 400);
            }
            
            // Evaluar
            $evaluacion = $this->validacionService->evaluarSolicitud($datos);
            
            // Crear solicitud
            $datosSolicitud = array_merge($datos, [
                'resultado_evaluacion' => $evaluacion['titulo'],
                'resultado_permitido' => $evaluacion['permitido'],
                'resultado_tipo' => $evaluacion['tipo'],
                'resultado_mensaje' => $evaluacion['mensaje'],
                'decision_final' => $datos['decision_final'] ?? null,
                'decision_usuario' => $datos['decision_usuario'] ?? null,
                'usuario_creacion' => $datos['usuario_creacion'] ?? 'Sistema'
            ]);
            
            $solicitudId = $this->solicitudModel->crear($datosSolicitud);
            
            // Si se incluye decisión final, actualizarla
            if (!empty($datos['decision_final'])) {
                $this->solicitudModel->actualizarDecision(
                    $solicitudId,
                    $datos['decision_final'],
                    $datos['decision_usuario'] ?? 'Sistema',
                    $datos['decision_observaciones'] ?? null
                );
            }
            
            $solicitudFinal = $this->solicitudModel->obtenerPorId($solicitudId);
            
            return $this->respuestaExito('Solicitud procesada completamente', [
                'solicitud' => $solicitudFinal,
                'evaluacion' => $evaluacion
            ], 201);
            
        } catch (Exception $e) {
            return $this->respuestaError('Error interno del servidor', [$e->getMessage()], 500);
        }
    }
    
    /**
     * Generar respuesta de éxito estandarizada
     */
    private function respuestaExito($mensaje, $data = null, $codigo = 200) {
        $respuesta = [
            'success' => true,
            'message' => $mensaje,
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $codigo
        ];
        
        if ($data !== null) {
            $respuesta['data'] = $data;
        }
        
        return $respuesta;
    }
    
    /**
     * Generar respuesta de error estandarizada
     */
    private function respuestaError($mensaje, $errores = [], $codigo = 400) {
        return [
            'success' => false,
            'message' => $mensaje,
            'errors' => $errores,
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $codigo
        ];
    }
    
    /**
     * Validar parámetros de entrada comunes
     */
    private function validarParametros($parametros, $requeridos = []) {
        $errores = [];
        
        foreach ($requeridos as $campo) {
            if (!isset($parametros[$campo]) || empty($parametros[$campo])) {
                $errores[] = "El campo {$campo} es requerido";
            }
        }
        
        return $errores;
    }
    
    /**
     * Sanitizar datos de entrada
     */
    private function sanitizarDatos($datos) {
        $datosSanitizados = [];
        
        foreach ($datos as $clave => $valor) {
            if (is_string($valor)) {
                $datosSanitizados[$clave] = trim(strip_tags($valor));
            } else {
                $datosSanitizados[$clave] = $valor;
            }
        }
        
        return $datosSanitizados;
    }
}
?>