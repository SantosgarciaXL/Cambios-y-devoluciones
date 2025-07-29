/**
 * Aplicación Principal
 * Sistema de Cambios y Devoluciones
 */

class CambiosDevolucionesApp {
    constructor() {
        this.dataTable = null;
        this.solicitudTemporal = null;
        this.charts = {};
        this.init();
    }

    /**
     * Inicializar aplicación
     */
    async init() {
        try {
            Utils.mostrarLoading(true, 'Inicializando aplicación...');
            
            // Configurar elementos básicos
            this.setupEventListeners();
            Utils.configurarFechasMaximas();
            Utils.inicializarTooltips();
            
            // Cargar datos iniciales
            await this.cargarEstadisticas();
            
            Utils.mostrarLoading(false);
            console.log('Aplicación inicializada correctamente');
            
        } catch (error) {
            console.error('Error inicializando aplicación:', error);
            Utils.mostrarError('Error al inicializar la aplicación', error.message);
            Utils.mostrarLoading(false);
        }
    }

    /**
     * Configurar event listeners
     */
    setupEventListeners() {
        // Formulario principal
        document.getElementById('solicitudForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.procesarSolicitud();
        });

        // Botón vista previa
        document.getElementById('btnPreview').addEventListener('click', () => {
            this.mostrarPreview();
        });

        // Botones de confirmación
        document.getElementById('confirmarSi').addEventListener('click', () => {
            this.confirmarDecision(true);
        });

        document.getElementById('confirmarNo').addEventListener('click', () => {
            this.confirmarDecision(false);
        });

        // Tab de administración
        document.getElementById('administracion-tab').addEventListener('click', () => {
            setTimeout(() => {
                this.cargarDatosAdministracion();
            }, 100);
        });

        // Filtros
        document.getElementById('aplicarFiltros').addEventListener('click', () => {
            this.aplicarFiltros();
        });

        // Exportar CSV
        document.getElementById('exportarCSV').addEventListener('click', () => {
            this.exportarCSV();
        });

        // Refrescar datos
        document.getElementById('refrescarDatos').addEventListener('click', () => {
            this.cargarDatosAdministracion();
        });

        // Validación en tiempo real
        this.setupValidacionTiempoReal();
    }

    /**
     * Configurar validación en tiempo real
     */
    setupValidacionTiempoReal() {
        const inputs = document.querySelectorAll('#solicitudForm input, #solicitudForm select, #solicitudForm textarea');
        
        inputs.forEach(input => {
            input.addEventListener('change', () => {
                this.validarCampo(input);
            });

            if (input.type === 'date') {
                input.addEventListener('change', () => {
                    if (input.value > new Date().toISOString().split('T')[0]) {
                        input.value = new Date().toISOString().split('T')[0];
                        Utils.mostrarNotificacion('No se puede seleccionar una fecha futura', 'warning');
                    }
                });
            }
        });
    }

    /**
     * Validar campo individual
     */
    validarCampo(input) {
        input.classList.remove('is-invalid', 'is-valid');
        
        if (input.hasAttribute('required') && !input.value.trim()) {
            input.classList.add('is-invalid');
            return false;
        }

        if (input.type === 'email' && input.value && !Utils.validarEmail(input.value)) {
            input.classList.add('is-invalid');
            return false;
        }

        if (input.type === 'date' && input.value && !Utils.validarFecha(input.value)) {
            input.classList.add('is-invalid');
            return false;
        }

        input.classList.add('is-valid');
        return true;
    }

    /**
     * Recopilar datos del formulario
     */
    recopilarDatosFormulario() {
        const form = document.getElementById('solicitudForm');
        const formData = new FormData(form);
        
        const datos = {
            // Información del cliente
            cliente_nombre: document.getElementById('clienteNombre').value.trim(),
            cliente_email: document.getElementById('clienteEmail').value.trim(),
            cliente_telefono: document.getElementById('clienteTelefono').value.trim(),
            
            // Información del pedido
            numero_pedido: document.getElementById('numeroPedido').value.trim(),
            numero_factura: document.getElementById('numeroFactura').value.trim(),
            
            // Datos principales
            fecha_recepcion_producto: document.getElementById('fechaRecepcion').value,
            medio_compra: document.getElementById('medioCompra').value,
            motivo_solicitud: document.getElementById('motivo').value,
            producto_usado: document.querySelector('input[name="usado"]:checked')?.value === 'true',
            tiene_etiquetas: document.querySelector('input[name="etiquetas"]:checked')?.value === 'true',
            observaciones: document.getElementById('observaciones').value.trim(),
            
            // Metadatos
            usuario_creacion: 'Frontend'
        };

        return datos;
    }

    /**
     * Mostrar vista previa de evaluación
     */
    async mostrarPreview() {
        try {
            const datos = this.recopilarDatosFormulario();
            
            // Validar formulario
            const errores = Utils.validarFormulario(document.getElementById('solicitudForm'));
            if (errores.length > 0) {
                Utils.mostrarError('Por favor, complete todos los campos requeridos', errores.join('\n'));
                return;
            }

            Utils.mostrarLoading(true, 'Evaluando solicitud...');
            
            const respuesta = await apiClient.evaluarPreview(datos);
            
            if (respuesta.success) {
                this.mostrarResultado(respuesta.data.evaluacion, datos, true);
            } else {
                Utils.mostrarError('Error al evaluar solicitud', respuesta.message);
            }
            
        } catch (error) {
            console.error('Error en preview:', error);
            Utils.mostrarError('Error al evaluar solicitud', error.message);
        } finally {
            Utils.mostrarLoading(false);
        }
    }

    /**
     * Procesar solicitud completa
     */
    async procesarSolicitud() {
        try {
            const datos = this.recopilarDatosFormulario();
            
            // Validar formulario
            const errores = Utils.validarFormulario(document.getElementById('solicitudForm'));
            if (errores.length > 0) {
                Utils.mostrarError('Por favor, complete todos los campos requeridos', errores.join('\n'));
                return;
            }

            Utils.mostrarLoading(true, 'Creando solicitud...');
            
            const respuesta = await apiClient.crearSolicitud(datos);
            
            if (respuesta.success) {
                this.mostrarResultado(respuesta.data.evaluacion, datos, false);
                this.solicitudTemporal = {
                    id: respuesta.data.solicitud_id,
                    datos: datos,
                    evaluacion: respuesta.data.evaluacion
                };
            } else {
                Utils.mostrarError('Error al crear solicitud', respuesta.message);
            }
            
        } catch (error) {
            console.error('Error procesando solicitud:', error);
            Utils.mostrarError('Error al procesar solicitud', error.message);
        } finally {
            Utils.mostrarLoading(false);
        }
    }

    /**
     * Mostrar resultado de evaluación
     */
    mostrarResultado(evaluacion, datos, esPreview) {
        // Actualizar contenido
        document.getElementById('resultadoTitulo').textContent = evaluacion.titulo;
        document.getElementById('resultadoTexto').textContent = evaluacion.mensaje;
        
        // Configurar alerta
        const alertElement = document.getElementById('resultadoAlert');
        alertElement.className = `alert ${evaluacion.alertClass}`;
        alertElement.innerHTML = `
            <i class="fas ${evaluacion.permitido ? 'fa-check-circle' : 'fa-times-circle'} me-2"></i>
            <strong>${evaluacion.permitido ? 'Solicitud Aprobada' : 'Solicitud Rechazada'}</strong>
        `;

        // Configurar card
        const cardElement = document.querySelector('.result-card');
        cardElement.className = `result-card card ${evaluacion.cardClass}`;

        // Mostrar información adicional
        this.mostrarInformacionAdicional(evaluacion, datos);

        // Mostrar/ocultar botones según el tipo
        const botonesConfirmacion = document.getElementById('botonesConfirmacion');
        if (esPreview) {
            botonesConfirmacion.style.display = 'none';
        } else {
            botonesConfirmacion.style.display = 'block';
        }

        // Mostrar resultado
        document.getElementById('resultadoContainer').style.display = 'block';
        document.getElementById('resultadoContainer').scrollIntoView({ behavior: 'smooth' });
    }

    /**
     * Mostrar información adicional de la evaluación
     */
    mostrarInformacionAdicional(evaluacion, datos) {
        const container = document.getElementById('informacionAdicional');
        
        let html = '<div class="row mt-3">';
        
        // Días transcurridos
        if (evaluacion.dias_transcurridos !== undefined) {
            html += `
                <div class="col-md-6">
                    <strong>Días transcurridos:</strong> ${evaluacion.dias_transcurridos}
                </div>
            `;
        }

        // Fundamento legal
        if (evaluacion.fundamento_legal) {
            html += `
                <div class="col-md-6">
                    <strong>Fundamento legal:</strong> ${evaluacion.fundamento_legal}
                </div>
            `;
        }

        // Reglas aplicadas
        if (evaluacion.reglas_aplicadas && evaluacion.reglas_aplicadas.length > 0) {
            html += `
                <div class="col-12 mt-2">
                    <strong>Reglas aplicadas:</strong>
                    <ul class="list-unstyled mt-1">
                        ${evaluacion.reglas_aplicadas.map(regla => `
                            <li><small class="text-muted">• ${regla.descripcion} (${regla.dias} días) - ${regla.fundamento}</small></li>
                        `).join('')}
                    </ul>
                </div>
            `;
        }

        html += '</div>';
        container.innerHTML = html;
    }

    /**
     * Confirmar decisión final
     */
    async confirmarDecision(proceder) {
        if (!this.solicitudTemporal) {
            Utils.mostrarError('No hay solicitud para confirmar');
            return;
        }

        try {
            Utils.mostrarLoading(true, 'Registrando decisión...');

            const decision = proceder ? 'Aprobada' : 'Rechazada';
            
            const respuesta = await apiClient.actualizarDecision(
                this.solicitudTemporal.id,
                decision,
                'Usuario Frontend',
                null
            );

            if (respuesta.success) {
                const mensaje = `La solicitud ha sido ${decision.toLowerCase()} y registrada en el sistema.`;
                
                Utils.mostrarExito(mensaje, () => {
                    this.limpiarFormulario();
                });
            } else {
                Utils.mostrarError('Error al registrar decisión', respuesta.message);
            }

        } catch (error) {
            console.error('Error confirmando decisión:', error);
            Utils.mostrarError('Error al confirmar decisión', error.message);
        } finally {
            Utils.mostrarLoading(false);
        }
    }

    /**
     * Limpiar formulario y resultado
     */
    limpiarFormulario() {
        Utils.limpiarFormulario(document.getElementById('solicitudForm'));
        document.getElementById('resultadoContainer').style.display = 'none';
        this.solicitudTemporal = null;
    }

    /**
     * Cargar estadísticas para el dashboard
     */
    async cargarEstadisticas() {
        try {
            const respuesta = await apiClient.obtenerDashboard();
            
            if (respuesta.success) {
                this.actualizarEstadisticas(respuesta.data);
            }
        } catch (error) {
            console.error('Error cargando estadísticas:', error);
        }
    }

    /**
     * Actualizar estadísticas en el dashboard
     */
    actualizarEstadisticas(data) {
        const container = document.getElementById('estadisticasContainer');
        if (!container) return;

        const estadisticas = data.resumen.ultimos_30_dias;
        
        container.innerHTML = `
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">${Utils.formatearNumero(estadisticas.total_solicitudes)}</div>
                    <div class="stats-label">Total Solicitudes</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">${Utils.formatearNumero(estadisticas.solicitudes_hoy)}</div>
                    <div class="stats-label">Hoy</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">${Utils.formatearNumero(estadisticas.aprobadas)}</div>
                    <div class="stats-label">Aprobadas</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">${Utils.formatearNumero(estadisticas.rechazadas)}</div>
                    <div class="stats-label">Rechazadas</div>
                </div>
            </div>
        `;

        // Actualizar gráficos si hay datos
        if (data.tendencias) {
            this.actualizarGraficos(data);
        }
    }

    /**
     * Cargar datos para la sección de administración
     */
    async cargarDatosAdministracion() {
        try {
            Utils.mostrarLoading(true, 'Cargando datos...');

            // Cargar solicitudes y estadísticas en paralelo
            const [solicitudesResp, estadisticasResp] = await Promise.all([
                apiClient.listarSolicitudes({}, 1, 100),
                apiClient.obtenerDashboard()
            ]);

            if (solicitudesResp.success) {
                this.actualizarTabla(solicitudesResp.data.solicitudes);
            }

            if (estadisticasResp.success) {
                this.actualizarEstadisticas(estadisticasResp.data);
                this.actualizarGraficos(estadisticasResp.data);
            }

        } catch (error) {
            console.error('Error cargando datos de administración:', error);
            Utils.mostrarError('Error al cargar datos', error.message);
        } finally {
            Utils.mostrarLoading(false);
        }
    }

    /**
     * Actualizar tabla de solicitudes
     */
    actualizarTabla(solicitudes) {
        // Destruir tabla existente
        if (this.dataTable) {
            this.dataTable.destroy();
        }

        // Formatear datos
        const datosFormateados = Utils.formatearDatosTabla(solicitudes);

        // Crear nueva tabla
        this.dataTable = $('#solicitudesTable').DataTable({
            data: datosFormateados,
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            columnDefs: [
                {
                    targets: [4, 5, 6, 7, 8, 9], // Columnas con HTML
                    orderable: true,
                    searchable: true
                },
                {
                    targets: '_all',
                    className: 'text-center'
                },
                {
                    targets: [2, 3], // Cliente, Pedido
                    className: 'text-start'
                }
            ],
            responsive: true
        });
    }

    /**
     * Aplicar filtros a la tabla
     */
    async aplicarFiltros() {
        try {
            const filtros = {
                fecha_desde: document.getElementById('filtroFechaDesde').value,
                fecha_hasta: document.getElementById('filtroFechaHasta').value,
                medio_compra: document.getElementById('filtroMedio').value,
                motivo_solicitud: document.getElementById('filtroMotivo').value,
                decision_final: document.getElementById('filtroDecision').value
            };

            Utils.mostrarLoading(true, 'Aplicando filtros...');

            const respuesta = await apiClient.listarSolicitudes(filtros, 1, 100);

            if (respuesta.success) {
                this.actualizarTabla(respuesta.data.solicitudes);
                
                if (respuesta.data.solicitudes.length === 0) {
                    Utils.mostrarNotificacion('No se encontraron resultados con los filtros aplicados', 'info');
                }
            } else {
                Utils.mostrarError('Error al aplicar filtros', respuesta.message);
            }

        } catch (error) {
            console.error('Error aplicando filtros:', error);
            Utils.mostrarError('Error al aplicar filtros', error.message);
        } finally {
            Utils.mostrarLoading(false);
        }
    }

    /**
     * Exportar datos a CSV
     */
    async exportarCSV() {
        try {
            const filtros = {
                fecha_desde: document.getElementById('filtroFechaDesde').value,
                fecha_hasta: document.getElementById('filtroFechaHasta').value,
                medio_compra: document.getElementById('filtroMedio').value,
                motivo_solicitud: document.getElementById('filtroMotivo').value,
                decision_final: document.getElementById('filtroDecision').value
            };

            Utils.mostrarLoading(true, 'Generando archivo...');

            const respuesta = await apiClient.exportarCSV(filtros);

            if (respuesta.success) {
                await apiClient.descargarArchivo(respuesta.data, respuesta.filename);
                Utils.mostrarNotificacion('Archivo descargado exitosamente', 'success');
            } else {
                Utils.mostrarError('Error al exportar datos', respuesta.message);
            }

        } catch (error) {
            console.error('Error exportando CSV:', error);
            Utils.mostrarError('Error al exportar datos', error.message);
        } finally {
            Utils.mostrarLoading(false);
        }
    }

    /**
     * Actualizar gráficos
     */
    actualizarGraficos(data) {
        this.crearGraficoEstados(data);
        this.crearGraficoTendencia(data);
    }

    /**
     * Crear gráfico de estados
     */
    crearGraficoEstados(data) {
        const ctx = document.getElementById('chartEstados');
        if (!ctx) return;

        const estadisticas = data.resumen.ultimos_30_dias;
        const labels = ['Aprobadas', 'Rechazadas', 'Pendientes'];
        const valores = [
            estadisticas.aprobadas || 0,
            estadisticas.rechazadas || 0,
            estadisticas.pendientes || 0
        ];

        if (this.charts.estados) {
            this.charts.estados.destroy();
        }

        this.charts.estados = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: valores,
                    backgroundColor: Utils.obtenerColoresGrafico('estados'),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    }

    /**
     * Crear gráfico de tendencia
     */
    crearGraficoTendencia(data) {
        const ctx = document.getElementById('chartTendencia');
        if (!ctx || !data.tendencias) return;

        const labels = data.tendencias.map(t => Utils.formatearFecha(t.fecha));
        const valores = data.tendencias.map(t => t.cantidad);

        if (this.charts.tendencia) {
            this.charts.tendencia.destroy();
        }

        this.charts.tendencia = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Solicitudes por Día',
                    data: valores,
                    borderColor: Utils.obtenerColoresGrafico('tendencia'),
                    backgroundColor: Utils.obtenerColoresGrafico('tendencia') + '20',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: Utils.obtenerColoresGrafico('tendencia'),
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
}

// Funciones globales para eventos desde HTML
window.verDetalle = async function(id) {
    try {
        Utils.mostrarLoading(true, 'Cargando detalle...');
        
        // En una implementación completa, cargaríamos el detalle desde la API
        Utils.mostrarNotificacion('Función de detalle en desarrollo', 'info');
        
    } catch (error) {
        Utils.mostrarError('Error al cargar detalle', error.message);
    } finally {
        Utils.mostrarLoading(false);
    }
};

window.copiarId = function(id) {
    Utils.copiarAlPortapapeles(id.toString());
};

// Inicializar aplicación cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Verificar dependencias
    if (typeof Utils === 'undefined') {
        console.error('Utils no está disponible');
        return;
    }
    
    if (typeof apiClient === 'undefined') {
        console.error('ApiClient no está disponible');
        return;
    }

    // Inicializar aplicación
    window.app = new CambiosDevolucionesApp();

    // Manejar errores globales
    window.addEventListener('error', function(e) {
        console.error('Error global:', e.error);
        Utils.mostrarError('Ha ocurrido un error inesperado', e.error?.message);
    });

    // Manejar promesas rechazadas
    window.addEventListener('unhandledrejection', function(e) {
        console.error('Promise rechazada:', e.reason);
        Utils.mostrarError('Error de comunicación', e.reason?.message);
    });

    console.log('Sistema de Cambios y Devoluciones inicializado correctamente');
});