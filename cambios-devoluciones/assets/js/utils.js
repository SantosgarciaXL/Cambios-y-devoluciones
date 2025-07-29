/**
 * Utilidades y funciones helper
 * Sistema de Cambios y Devoluciones
 */

class Utils {
    /**
     * Formatear fecha en formato español
     */
    static formatearFecha(fecha, incluirHora = false) {
        if (!fecha) return '';
        
        const date = new Date(fecha);
        const opciones = {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        };
        
        if (incluirHora) {
            opciones.hour = '2-digit';
            opciones.minute = '2-digit';
        }
        
        return date.toLocaleDateString('es-AR', opciones);
    }

    /**
     * Formatear números con separadores de miles
     */
    static formatearNumero(numero, decimales = 0) {
        if (numero === null || numero === undefined) return '0';
        return Number(numero).toLocaleString('es-AR', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        });
    }

    /**
     * Formatear moneda
     */
    static formatearMoneda(monto) {
        if (monto === null || monto === undefined) return '$0';
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS'
        }).format(monto);
    }

    /**
     * Obtener badge HTML para estados
     */
    static obtenerBadgeEstado(permitido) {
        if (permitido === true || permitido === 'true') {
            return '<span class="badge bg-success">Permitido</span>';
        } else if (permitido === false || permitido === 'false') {
            return '<span class="badge bg-danger">No Permitido</span>';
        }
        return '<span class="badge bg-warning">Pendiente</span>';
    }

    /**
     * Obtener badge HTML para decisiones
     */
    static obtenerBadgeDecision(decision) {
        const badges = {
            'Aprobada': '<span class="badge bg-success">Aprobada</span>',
            'Rechazada': '<span class="badge bg-danger">Rechazada</span>',
            'Pendiente': '<span class="badge bg-warning">Pendiente</span>'
        };
        return badges[decision] || '<span class="badge bg-secondary">Sin Decisión</span>';
    }

    /**
     * Obtener badge HTML para motivos
     */
    static obtenerBadgeMotivo(motivo) {
        const badges = {
            'cambio': '<span class="badge bg-info">Cambio</span>',
            'devolucion': '<span class="badge bg-warning">Devolución</span>',
            'falla': '<span class="badge bg-danger">Falla</span>'
        };
        return badges[motivo] || motivo;
    }

    /**
     * Obtener badge HTML para medio de compra
     */
    static obtenerBadgeMedio(medio) {
        const badges = {
            'online': '<span class="badge bg-primary">Online</span>',
            'presencial': '<span class="badge bg-secondary">Presencial</span>'
        };
        return badges[medio] || medio;
    }

    /**
     * Validar email
     */
    static validarEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    /**
     * Validar fecha (no futura)
     */
    static validarFecha(fecha) {
        if (!fecha) return false;
        const fechaInput = new Date(fecha);
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        return fechaInput <= hoy;
    }

    /**
     * Calcular días transcurridos
     */
    static calcularDiasTranscurridos(fechaInicio, fechaFin = null) {
        const inicio = new Date(fechaInicio);
        const fin = fechaFin ? new Date(fechaFin) : new Date();
        const diffTime = Math.abs(fin - inicio);
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    }

    /**
     * Obtener color para gráficos según el contexto
     */
    static obtenerColoresGrafico(tipo = 'estados') {
        const colores = {
            estados: ['#dc3545', '#ffc107', '#28a745'], // Rojo, Amarillo, Verde
            medios: ['#007bff', '#6c757d'], // Azul, Gris
            motivos: ['#17a2b8', '#fd7e14', '#dc3545'], // Celeste, Naranja, Rojo
            tendencia: '#007bff'
        };
        return colores[tipo] || colores.tendencia;
    }

    /**
     * Mostrar loading overlay
     */
    static mostrarLoading(mostrar = true, mensaje = 'Procesando...') {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = mostrar ? 'flex' : 'none';
            const textoElement = overlay.querySelector('p');
            if (textoElement) {
                textoElement.textContent = mensaje;
            }
        }
    }

    /**
     * Mostrar notificación toast
     */
    static mostrarNotificacion(mensaje, tipo = 'info', duracion = 3000) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: tipo,
            title: mensaje,
            showConfirmButton: false,
            timer: duracion,
            timerProgressBar: true
        });
    }

    /**
     * Mostrar modal de confirmación
     */
    static async confirmar(titulo, mensaje, textoConfirmar = 'Confirmar', textoCancelar = 'Cancelar') {
        const result = await Swal.fire({
            title: titulo,
            text: mensaje,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#007bff',
            cancelButtonColor: '#6c757d',
            confirmButtonText: textoConfirmar,
            cancelButtonText: textoCancelar
        });
        return result.isConfirmed;
    }

    /**
     * Mostrar modal de error
     */
    static mostrarError(mensaje, detalles = null) {
        let contenido = mensaje;
        if (detalles) {
            contenido += `\n\nDetalles: ${JSON.stringify(detalles, null, 2)}`;
        }

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: contenido,
            confirmButtonColor: '#dc3545'
        });
    }

    /**
     * Mostrar modal de éxito
     */
    static mostrarExito(mensaje, callback = null) {
        Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: mensaje,
            confirmButtonColor: '#28a745'
        }).then(() => {
            if (callback) callback();
        });
    }

    /**
     * Debounce function para búsquedas
     */
    static debounce(func, wait, immediate) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                timeout = null;
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    }

    /**
     * Copiar texto al portapapeles
     */
    static async copiarAlPortapapeles(texto) {
        try {
            await navigator.clipboard.writeText(texto);
            this.mostrarNotificacion('Copiado al portapapeles', 'success', 1500);
            return true;
        } catch (err) {
            console.error('Error al copiar:', err);
            return false;
        }
    }

    /**
     * Generar ID único
     */
    static generarId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    /**
     * Escapar HTML para prevenir XSS
     */
    static escaparHTML(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    /**
     * Convertir objeto a query string
     */
    static objetoAQueryString(obj) {
        return Object.keys(obj)
            .filter(key => obj[key] !== null && obj[key] !== undefined && obj[key] !== '')
            .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(obj[key]))
            .join('&');
    }

    /**
     * Validar formulario
     */
    static validarFormulario(formulario) {
        const errores = [];
        const elementos = formulario.querySelectorAll('[required]');

        elementos.forEach(elemento => {
            if (!elemento.value.trim()) {
                errores.push(`El campo ${elemento.getAttribute('data-name') || elemento.name || elemento.id} es requerido`);
                elemento.classList.add('is-invalid');
            } else {
                elemento.classList.remove('is-invalid');
            }

            // Validaciones específicas
            if (elemento.type === 'email' && elemento.value && !this.validarEmail(elemento.value)) {
                errores.push('El formato del email no es válido');
                elemento.classList.add('is-invalid');
            }

            if (elemento.type === 'date' && elemento.value && !this.validarFecha(elemento.value)) {
                errores.push('La fecha no puede ser futura');
                elemento.classList.add('is-invalid');
            }
        });

        return errores;
    }

    /**
     * Limpiar formulario
     */
    static limpiarFormulario(formulario) {
        formulario.reset();
        formulario.querySelectorAll('.is-invalid').forEach(elemento => {
            elemento.classList.remove('is-invalid');
        });
        formulario.querySelectorAll('.is-valid').forEach(elemento => {
            elemento.classList.remove('is-valid');
        });
    }

    /**
     * Obtener parámetros URL
     */
    static obtenerParametrosURL() {
        const params = new URLSearchParams(window.location.search);
        const resultado = {};
        for (const [key, value] of params) {
            resultado[key] = value;
        }
        return resultado;
    }

    /**
     * Actualizar parámetros URL sin recargar
     */
    static actualizarURL(parametros) {
        const url = new URL(window.location);
        Object.keys(parametros).forEach(key => {
            if (parametros[key]) {
                url.searchParams.set(key, parametros[key]);
            } else {
                url.searchParams.delete(key);
            }
        });
        window.history.replaceState({}, '', url);
    }

    /**
     * Formatear datos para tabla DataTables
     */
    static formatearDatosTabla(solicitudes) {
        return solicitudes.map(solicitud => [
            solicitud.id,
            this.formatearFecha(solicitud.fecha_solicitud),
            solicitud.cliente_nombre || 'N/A',
            solicitud.numero_pedido || 'N/A',
            this.obtenerBadgeMedio(solicitud.medio_compra),
            this.obtenerBadgeMotivo(solicitud.motivo_solicitud),
            this.formatearEstadoProducto(solicitud),
            this.obtenerBadgeEstado(solicitud.resultado_permitido),
            this.obtenerBadgeDecision(solicitud.decision_final),
            this.generarBotonesAccion(solicitud.id)
        ]);
    }

    /**
     * Formatear estado del producto para tabla
     */
    static formatearEstadoProducto(solicitud) {
        const usado = solicitud.producto_usado ? 'Usado' : 'No usado';
        const etiquetas = solicitud.tiene_etiquetas ? 'Con etiquetas' : 'Sin etiquetas';
        return `${usado}<br><small class="text-muted">${etiquetas}</small>`;
    }

    /**
     * Generar botones de acción para tabla
     */
    static generarBotonesAccion(id) {
        return `
            <button class="btn btn-sm btn-outline-primary" onclick="verDetalle(${id})" title="Ver detalle">
                <i class="fas fa-eye"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="copiarId(${id})" title="Copiar ID">
                <i class="fas fa-copy"></i>
            </button>
        `;
    }

    /**
     * Configurar fecha máxima (hoy) en inputs date
     */
    static configurarFechasMaximas() {
        const hoy = new Date().toISOString().split('T')[0];
        document.querySelectorAll('input[type="date"]').forEach(input => {
            if (!input.hasAttribute('max')) {
                input.max = hoy;
            }
        });
    }

    /**
     * Inicializar tooltips de Bootstrap
     */
    static inicializarTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

// Hacer disponible globalmente
window.Utils = Utils;