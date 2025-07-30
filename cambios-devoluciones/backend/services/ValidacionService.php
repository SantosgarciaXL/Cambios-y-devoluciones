<?php
/**
 * Servicio de Validación de Solicitudes
 * Implementa las reglas de negocio según Ley 24.240 y políticas comerciales
 */

require_once __DIR__ . '/../../config/config.php';

class ValidacionService {
    
    private $reglasNegocio;
    
    public function __construct() {
        $this->cargarReglasNegocio();
    }
    
    /**
     * Validar datos de entrada de una solicitud
     */
    public function validarSolicitud($datos) {
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
    
    /**
     * Evaluar solicitud según las reglas de negocio
     */
    public function evaluarSolicitud($datos) {
        try {
            // Calcular días transcurridos
            $fechaRecepcion = new DateTime($datos['fecha_recepcion_producto']);
            $fechaActual = new DateTime();
            $diasTranscurridos = $fechaActual->diff($fechaRecepcion)->days;
            
            // Aplicar reglas según el motivo
            $resultado = $this->aplicarReglasPorMotivo(
                $datos['motivo_solicitud'],
                $datos['medio_compra'],
                $diasTranscurridos,
                $datos['producto_usado'] ?? false,
                $datos['tiene_etiquetas'] ?? true
            );
            
            // Agregar información adicional
            $resultado['dias_transcurridos'] = $diasTranscurridos;
            $resultado['fecha_evaluacion'] = $fechaActual->format('Y-m-d H:i:s');
            $resultado['reglas_aplicadas'] = $this->obtenerReglasAplicadas($datos['motivo_solicitud'], $datos['medio_compra']);
            
            return $resultado;
            
        } catch (Exception $e) {
            return [
                'permitido' => false,
                'tipo' => 'error',
                'titulo' => 'Error en Evaluación',
                'mensaje' => 'No se pudo evaluar la solicitud: ' . $e->getMessage(),
                'alertClass' => 'alert-danger',
                'cardClass' => 'result-no-permitido'
            ];
        }
    }
    
    /**
     * Aplicar reglas específicas según el motivo de la solicitud
     */
    private function aplicarReglasPorMotivo($motivo, $medioCompra, $diasTranscurridos, $productoUsado, $tieneEtiquetas) {
        switch ($motivo) {
            case 'falla':
                return $this->evaluarFalla($diasTranscurridos);
                
            case 'devolucion':
                return $this->evaluarDevolucion($medioCompra, $diasTranscurridos, $productoUsado, $tieneEtiquetas);
                
            case 'cambio':
                return $this->evaluarCambio($medioCompra, $diasTranscurridos, $productoUsado, $tieneEtiquetas);
                
            default:
                return $this->crearResultadoRechazado('Motivo de solicitud no válido');
        }
    }
    
    /**
     * Evaluar solicitud por falla o defecto
     * Artículo 11 Ley 24.240 - Garantía legal
     */
    private function evaluarFalla($diasTranscurridos) {
        $diasGarantia = $this->reglasNegocio['garantia_falla_dias'];
        
        if ($diasTranscurridos <= $diasGarantia) {
            return [
                'permitido' => true,
                'tipo' => 'garantia',
                'titulo' => 'Garantía por Falla',
                'mensaje' => "El producto presenta una falla o defecto. Corresponde aplicar la garantía legal según el Artículo 11 de la Ley de Defensa del Consumidor. Tiempo transcurrido: {$diasTranscurridos} días (límite: {$diasGarantia} días).",
                'alertClass' => 'alert-warning',
                'cardClass' => 'result-garantia',
                'fundamento_legal' => 'Artículo 11, Ley 24.240 - Garantía Legal',
                'accion_recomendada' => 'Proceder con reparación, cambio o devolución según corresponda'
            ];
        } else {
            return [
                'permitido' => false,
                'tipo' => 'garantia_vencida',
                'titulo' => 'Garantía Vencida',
                'mensaje' => "Han transcurrido {$diasTranscurridos} días desde la recepción del producto. El período de garantía legal es de {$diasGarantia} días.",
                'alertClass' => 'alert-danger',
                'cardClass' => 'result-no-permitido',
                'fundamento_legal' => 'Artículo 11, Ley 24.240 - Garantía Legal',
                'accion_recomendada' => 'Evaluar garantía extendida del fabricante'
            ];
        }
    }
    
    /**
     * Evaluar solicitud de devolución
     * Artículo 34 Ley 24.240 - Venta fuera del establecimiento comercial
     */
    private function evaluarDevolucion($medioCompra, $diasTranscurridos, $productoUsado, $tieneEtiquetas) {
        // Las devoluciones solo aplican para compras online (fuera del establecimiento)
        if ($medioCompra !== 'online') {
            return [
                'permitido' => false,
                'tipo' => 'devolucion_no_aplicable',
                'titulo' => 'Devolución No Aplicable',
                'mensaje' => 'Las devoluciones solo están permitidas para compras realizadas fuera del establecimiento comercial (online), conforme al Artículo 34 de la Ley de Defensa del Consumidor.',
                'alertClass' => 'alert-danger',
                'cardClass' => 'result-no-permitido',
                'fundamento_legal' => 'Artículo 34, Ley 24.240 - Venta fuera del establecimiento',
                'accion_recomendada' => 'Evaluar posibilidad de cambio según política comercial'
            ];
        }
        
        $diasLimite = $this->reglasNegocio['devolucion_online_dias'];
        
        // Verificar plazo legal
        if ($diasTranscurridos > $diasLimite) {
            return [
                'permitido' => false,
                'tipo' => 'devolucion_fuera_plazo',
                'titulo' => 'Devolución Fuera de Plazo',
                'mensaje' => "Han transcurrido {$diasTranscurridos} días desde la recepción. El plazo legal para devolución de compras online es de {$diasLimite} días corridos según el Artículo 34 de la Ley 24.240.",
                'alertClass' => 'alert-danger',
                'cardClass' => 'result-no-permitido',
                'fundamento_legal' => 'Artículo 34, Ley 24.240 - Plazo de reflexión',
                'accion_recomendada' => 'Plazo vencido - evaluar política comercial excepcional'
            ];
        }
        
        // Verificar estado del producto
        $estadoValido = $this->validarEstadoProducto($productoUsado, $tieneEtiquetas, 'devolucion');
        if (!$estadoValido['valido']) {
            return [
                'permitido' => false,
                'tipo' => 'devolucion_estado_invalido',
                'titulo' => 'Estado del Producto No Válido',
                'mensaje' => "No es posible proceder con la devolución. {$estadoValido['razon']} Para devoluciones, el producto debe estar en las mismas condiciones que al momento de la entrega.",
                'alertClass' => 'alert-danger',
                'cardClass' => 'result-no-permitido',
                'fundamento_legal' => 'Artículo 34, Ley 24.240 - Condiciones del producto',
                'accion_recomendada' => 'Producto no cumple condiciones para devolución'
            ];
        }
        
        // Devolución aprobada
        return [
            'permitido' => true,
            'tipo' => 'devolucion',
            'titulo' => 'Devolución Permitida',
            'mensaje' => "Compra online dentro del plazo legal de {$diasLimite} días corridos ({$diasTranscurridos} días transcurridos). El producto cumple las condiciones establecidas en el Artículo 34 de la Ley 24.240.",
            'alertClass' => 'alert-success',
            'cardClass' => 'result-permitido',
            'fundamento_legal' => 'Artículo 34, Ley 24.240 - Derecho de arrepentimiento',
            'accion_recomendada' => 'Proceder con la devolución del dinero'
        ];
    }
    
    /**
     * Evaluar solicitud de cambio
     * Política comercial de la empresa
     */
    private function evaluarCambio($medioCompra, $diasTranscurridos, $productoUsado, $tieneEtiquetas) {
        // Determinar plazo según medio de compra
        $diasLimite = ($medioCompra === 'online') 
            ? $this->reglasNegocio['cambio_online_dias']
            : $this->reglasNegocio['cambio_presencial_dias'];
        
        // Verificar plazo
        if ($diasTranscurridos > $diasLimite) {
            return [
                'permitido' => false,
                'tipo' => 'cambio_fuera_plazo',
                'titulo' => 'Cambio Fuera de Plazo',
                'mensaje' => "Han transcurrido {$diasTranscurridos} días desde la recepción. El plazo para cambios en compras {$medioCompra} es de {$diasLimite} días según nuestra política comercial.",
                'alertClass' => 'alert-danger',
                'cardClass' => 'result-no-permitido',
                'fundamento_legal' => 'Política comercial de la empresa',
                'accion_recomendada' => 'Plazo vencido para cambio'
            ];
        }
        
        // Verificar estado del producto (más estricto para cambios)
        $estadoValido = $this->validarEstadoProducto($productoUsado, $tieneEtiquetas, 'cambio');
        if (!$estadoValido['valido']) {
            return [
                'permitido' => false,
                'tipo' => 'cambio_estado_invalido',
                'titulo' => 'Estado del Producto No Válido',
                'mensaje' => "No es posible proceder con el cambio. {$estadoValido['razon']} Para cambios, el producto debe estar sin usar y con todas sus etiquetas originales.",
                'alertClass' => 'alert-danger',
                'cardClass' => 'result-no-permitido',
                'fundamento_legal' => 'Política comercial de la empresa',
                'accion_recomendada' => 'Producto no cumple condiciones para cambio'
            ];
        }
        
        // Cambio aprobado
        return [
            'permitido' => true,
            'tipo' => 'cambio',
            'titulo' => 'Cambio Permitido',
            'mensaje' => "El producto cumple las condiciones para cambio. Compra {$medioCompra} dentro del plazo de {$diasLimite} días ({$diasTranscurridos} días transcurridos). Producto en condiciones originales.",
            'alertClass' => 'alert-success',
            'cardClass' => 'result-permitido',
            'fundamento_legal' => 'Política comercial de la empresa',
            'accion_recomendada' => 'Proceder con el cambio por otro producto'
        ];
    }
    
    /**
     * Validar estado físico del producto
     */
    private function validarEstadoProducto($productoUsado, $tieneEtiquetas, $tipoSolicitud) {
        $requiereNoUsado = $this->reglasNegocio['require_unused_for_return'];
        $requiereEtiquetas = $this->reglasNegocio['require_tags_for_return'];
        
        $problemas = [];
        
        // Para cambios, siempre requerir producto no usado y con etiquetas
        if ($tipoSolicitud === 'cambio') {
            if ($productoUsado) {
                $problemas[] = 'el producto ha sido usado';
            }
            if (!$tieneEtiquetas) {
                $problemas[] = 'el producto no tiene etiquetas originales';
            }
        }
        // Para devoluciones, aplicar configuración
        elseif ($tipoSolicitud === 'devolucion') {
            if ($requiereNoUsado && $productoUsado) {
                $problemas[] = 'el producto ha sido usado';
            }
            if ($requiereEtiquetas && !$tieneEtiquetas) {
                $problemas[] = 'el producto no tiene etiquetas originales';
            }
        }
        
        if (empty($problemas)) {
            return ['valido' => true];
        }
        
        $razon = count($problemas) > 1 
            ? implode(' y ', $problemas)
            : $problemas[0];
            
        return [
            'valido' => false,
            'razon' => ucfirst($razon) . '.'
        ];
    }
    
    /**
     * Crear resultado de rechazo estandarizado
     */
    private function crearResultadoRechazado($mensaje, $tipo = 'no_permitido') {
        return [
            'permitido' => false,
            'tipo' => $tipo,
            'titulo' => 'Solicitud No Permitida',
            'mensaje' => $mensaje,
            'alertClass' => 'alert-danger',
            'cardClass' => 'result-no-permitido'
        ];
    }
    
    /**
     * Cargar reglas de negocio desde configuración
     */
    private function cargarReglasNegocio() {
        // Valores por defecto basados en la Ley 24.240
        $this->reglasNegocio = [
            'devolucion_online_dias' => 10,      // Artículo 34 - Derecho de arrepentimiento
            'cambio_online_dias' => 30,          // Política comercial
            'cambio_presencial_dias' => 15,      // Política comercial
            'garantia_falla_dias' => 365,        // Artículo 11 - Garantía legal
            'require_tags_for_return' => true,   // Política comercial
            'require_unused_for_return' => true  // Política comercial
        ];
        
        // Intentar cargar desde configuración si está disponible
        try {
            if (class_exists('Config')) {
                $this->reglasNegocio['devolucion_online_dias'] = Config::get('business_rules.devolucion_online_dias', 10);
                $this->reglasNegocio['cambio_online_dias'] = Config::get('business_rules.cambio_online_dias', 30);
                $this->reglasNegocio['cambio_presencial_dias'] = Config::get('business_rules.cambio_presencial_dias', 15);
                $this->reglasNegocio['garantia_falla_dias'] = Config::get('business_rules.garantia_falla_dias', 365);
                $this->reglasNegocio['require_tags_for_return'] = Config::get('business_rules.require_tags_for_return', true);
                $this->reglasNegocio['require_unused_for_return'] = Config::get('business_rules.require_unused_for_return', true);
            }
        } catch (Exception $e) {
            // Usar valores por defecto si hay error
            error_log("Error cargando configuración en ValidacionService: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener reglas aplicadas para un caso específico
     */
    private function obtenerReglasAplicadas($motivo, $medioCompra) {
        $reglas = [];
        
        switch ($motivo) {
            case 'falla':
                $reglas[] = [
                    'descripcion' => 'Garantía legal por falla',
                    'dias' => $this->reglasNegocio['garantia_falla_dias'],
                    'fundamento' => 'Artículo 11, Ley 24.240'
                ];
                break;
                
            case 'devolucion':
                if ($medioCompra === 'online') {
                    $reglas[] = [
                        'descripcion' => 'Devolución para compras online',
                        'dias' => $this->reglasNegocio['devolucion_online_dias'],
                        'fundamento' => 'Artículo 34, Ley 24.240'
                    ];
                }
                break;
                
            case 'cambio':
                $dias = ($medioCompra === 'online') 
                    ? $this->reglasNegocio['cambio_online_dias']
                    : $this->reglasNegocio['cambio_presencial_dias'];
                    
                $reglas[] = [
                    'descripcion' => "Cambio para compras {$medioCompra}",
                    'dias' => $dias,
                    'fundamento' => 'Política comercial'
                ];
                break;
        }
        
        return $reglas;
    }
    
    /**
     * Obtener información de plazos vigentes
     */
    public function obtenerInformacionPlazos() {
        return [
            'devolucion_online' => [
                'dias' => $this->reglasNegocio['devolucion_online_dias'],
                'descripcion' => 'Plazo para devolución en compras online',
                'fundamento' => 'Artículo 34, Ley 24.240'
            ],
            'cambio_online' => [
                'dias' => $this->reglasNegocio['cambio_online_dias'],
                'descripcion' => 'Plazo para cambio en compras online',
                'fundamento' => 'Política comercial'
            ],
            'cambio_presencial' => [
                'dias' => $this->reglasNegocio['cambio_presencial_dias'],
                'descripcion' => 'Plazo para cambio en compras presenciales',
                'fundamento' => 'Política comercial'
            ],
            'garantia_falla' => [
                'dias' => $this->reglasNegocio['garantia_falla_dias'],
                'descripcion' => 'Garantía legal por falla o defecto',
                'fundamento' => 'Artículo 11, Ley 24.240'
            ]
        ];
    }
    
    /**
     * Generar reporte detallado de evaluación
     */
    public function generarReporteEvaluacion($datos, $evaluacion) {
        $fechaRecepcion = new DateTime($datos['fecha_recepcion_producto']);
        $fechaActual = new DateTime();
        
        $reporte = [
            'solicitud' => [
                'fecha_evaluacion' => $fechaActual->format('Y-m-d H:i:s'),
                'fecha_recepcion' => $fechaRecepcion->format('Y-m-d'),
                'dias_transcurridos' => $evaluacion['dias_transcurridos'],
                'medio_compra' => $datos['medio_compra'],
                'motivo_solicitud' => $datos['motivo_solicitud'],
                'producto_usado' => $datos['producto_usado'] ?? false,
                'tiene_etiquetas' => $datos['tiene_etiquetas'] ?? true
            ],
            'evaluacion' => $evaluacion,
            'reglas_negocio' => $this->reglasNegocio,
            'normativa_aplicada' => $this->obtenerNormativaAplicada($datos['motivo_solicitud']),
            'metadata' => [
                'version_sistema' => '1.0.0',
                'normativa_aplicada' => 'Ley 24.240 - Defensa del Consumidor',
                'usuario_evaluacion' => $datos['usuario_creacion'] ?? 'Sistema'
            ]
        ];
        
        return $reporte;
    }
    
    /**
     * Obtener normativa legal aplicada
     */
    private function obtenerNormativaAplicada($motivo) {
        $normativa = [
            'ley_principal' => 'Ley 24.240 - Defensa del Consumidor',
            'articulos_aplicables' => []
        ];
        
        switch ($motivo) {
            case 'falla':
                $normativa['articulos_aplicables'] = [
                    'Artículo 11' => 'Garantía legal - El proveedor debe garantizar el producto por vicios o defectos',
                    'Artículo 12' => 'Plazo de garantía - Mínimo 3 meses para bienes muebles no consumibles'
                ];
                break;
                
            case 'devolucion':
                $normativa['articulos_aplicables'] = [
                    'Artículo 34' => 'Venta fuera del establecimiento - Derecho de arrepentimiento de 10 días',
                    'Artículo 35' => 'Condiciones del derecho de arrepentimiento'
                ];
                break;
                
            case 'cambio':
                $normativa['articulos_aplicables'] = [
                    'Política Comercial' => 'Cambios por cortesía comercial según política de la empresa'
                ];
                break;
        }
        
        return $normativa;
    }
    
    /**
     * Validar configuración del servicio
     */
    public function validarConfiguracion() {
        $errores = [];
        
        // Verificar que las reglas tengan valores válidos
        if ($this->reglasNegocio['devolucion_online_dias'] < 1 || $this->reglasNegocio['devolucion_online_dias'] > 365) {
            $errores[] = "Días de devolución online fuera de rango válido (1-365)";
        }
        
        if ($this->reglasNegocio['cambio_online_dias'] < 1 || $this->reglasNegocio['cambio_online_dias'] > 365) {
            $errores[] = "Días de cambio online fuera de rango válido (1-365)";
        }
        
        if ($this->reglasNegocio['cambio_presencial_dias'] < 1 || $this->reglasNegocio['cambio_presencial_dias'] > 365) {
            $errores[] = "Días de cambio presencial fuera de rango válido (1-365)";
        }
        
        if ($this->reglasNegocio['garantia_falla_dias'] < 90) {
            $errores[] = "Garantía por falla menor al mínimo legal (90 días)";
        }
        
        return [
            'valido' => empty($errores),
            'errores' => $errores,
            'reglas_cargadas' => $this->reglasNegocio
        ];
    }
    
    /**
     * Obtener estadísticas de evaluaciones (si se implementa logging)
     */
    public function obtenerEstadisticasEvaluaciones($fechaDesde = null, $fechaHasta = null) {
        // Esta función podría implementarse para analizar patrones de evaluación
        // Por ahora retorna estructura base
        return [
            'total_evaluaciones' => 0,
            'por_motivo' => [
                'cambio' => 0,
                'devolucion' => 0,
                'falla' => 0
            ],
            'por_resultado' => [
                'permitidas' => 0,
                'rechazadas' => 0
            ],
            'promedio_dias_solicitud' => 0
        ];
    }
}
?>