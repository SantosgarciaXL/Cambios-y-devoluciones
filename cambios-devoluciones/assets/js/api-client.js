/**
 * Cliente API para Sistema de Cambios y Devoluciones
 * Maneja todas las comunicaciones con el backend
 */

class ApiClient {
    constructor(baseUrl = 'api/') {
        this.baseUrl = baseUrl;
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }

    /**
     * Realizar petición HTTP genérica
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const config = {
            headers: { ...this.defaultHeaders, ...options.headers },
            ...options
        };

        try {
            const response = await fetch(url, config);
            
            // Si la respuesta es CSV, manejar diferente
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('text/csv')) {
                const blob = await response.blob();
                return {
                    success: true,
                    data: blob,
                    filename: this.extractFilename(response.headers.get('content-disposition'))
                };
            }

            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || `HTTP error! status: ${response.status}`);
            }

            return data;

        } catch (error) {
            console.error('API Request Error:', error);
            throw error;
        }
    }

    /**
     * Extraer nombre de archivo de Content-Disposition header
     */
    extractFilename(contentDisposition) {
        if (!contentDisposition) return 'export.csv';
        const matches = /filename="([^"]+)"/.exec(contentDisposition);
        return matches ? matches[1] : 'export.csv';
    }

    /**
     * Crear nueva solicitud
     */
    async crearSolicitud(datos) {
        return this.request('crear_solicitud.php', {
            method: 'POST',
            body: JSON.stringify(datos)
        });
    }

    /**
     * Evaluar solicitud sin guardar (preview)
     */
    async evaluarPreview(datos) {
        return this.request('crear_solicitud.php', {
            method: 'POST',
            body: JSON.stringify({ ...datos, preview: true })
        });
    }

    /**
     * Listar solicitudes con filtros
     */
    async listarSolicitudes(filtros = {}, pagina = 1, porPagina = 20) {
        const params = new URLSearchParams({
            pagina: pagina.toString(),
            por_pagina: porPagina.toString(),
            ...Object.fromEntries(
                Object.entries(filtros).filter(([_, value]) => value !== '' && value !== null)
            )
        });

        return this.request(`listar_solicitudes.php?${params.toString()}`, {
            method: 'GET'
        });
    }

    /**
     * Obtener solicitud por ID
     */
    async obtenerSolicitud(id) {
        return this.request(`obtener_solicitud.php?id=${id}`, {
            method: 'GET'
        });
    }

    /**
     * Actualizar decisión de solicitud
     */
    async actualizarDecision(id, decision, usuario, observaciones = null) {
        return this.request('actualizar_decision.php', {
            method: 'POST',
            body: JSON.stringify({
                id,
                decision,
                usuario,
                observaciones
            })
        });
    }

    /**
     * Obtener estadísticas
     */
    async obtenerEstadisticas(fechaDesde = null, fechaHasta = null) {
        const params = new URLSearchParams();
        if (fechaDesde) params.append('fecha_desde', fechaDesde);
        if (fechaHasta) params.append('fecha_hasta', fechaHasta);

        const queryString = params.toString();
        const endpoint = queryString ? `estadisticas.php?${queryString}` : 'estadisticas.php';

        return this.request(endpoint, { method: 'GET' });
    }

    /**
     * Obtener dashboard completo
     */
    async obtenerDashboard() {
        return this.request('estadisticas.php?dashboard=true', {
            method: 'GET'
        });
    }

    /**
     * Exportar datos a CSV
     */
    async exportarCSV(filtros = {}) {
        const params = new URLSearchParams(
            Object.fromEntries(
                Object.entries(filtros).filter(([_, value]) => value !== '' && value !== null)
            )
        );

        const queryString = params.toString();
        const endpoint = queryString ? `exportar_datos.php?${queryString}` : 'exportar_datos.php';

        return this.request(endpoint, { method: 'GET' });
    }

    /**
     * Obtener configuración del sistema
     */
    async obtenerConfiguracion() {
        return this.request('configuracion.php', {
            method: 'GET'
        });
    }

    /**
     * Manejar descarga de archivos
     */
    async descargarArchivo(blob, filename) {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }

    /**
     * Procesar respuesta de error
     */
    manejarError(error) {
        console.error('Error de API:', error);
        
        let mensaje = 'Ha ocurrido un error inesperado';
        
        if (error.message) {
            mensaje = error.message;
        } else if (typeof error === 'string') {
            mensaje = error;
        }

        return {
            success: false,
            message: mensaje,
            timestamp: new Date().toISOString()
        };
    }

    /**
     * Validar conexión con el servidor
     */
    async validarConexion() {
        try {
            const response = await this.request('health.php', {
                method: 'GET'
            });
            return response.success;
        } catch (error) {
            return false;
        }
    }

    /**
     * Obtener información del sistema
     */
    async obtenerInfoSistema() {
        try {
            return await this.request('info.php', {
                method: 'GET'
            });
        } catch (error) {
            return this.manejarError(error);
        }
    }
}

// Crear instancia global del cliente API
window.apiClient = new ApiClient();

// Exportar para uso en módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ApiClient;
}