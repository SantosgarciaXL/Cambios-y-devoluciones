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
        $this->reglasNegocio = [
            'devolucion_online_dias' => Config::get('business_rules.devolucion_online_dias', 10),
            'cambio_online_dias' => Config::get('business_rules.cambio_online_dias', 30),