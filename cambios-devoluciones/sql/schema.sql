-- Schema para Sistema de Cambios y Devoluciones
-- Compatible con SQL Server

-- Crear base de datos si no existe
IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'ecommerce_cambios_devoluciones')
BEGIN
    CREATE DATABASE ecommerce_cambios_devoluciones;
END
GO

USE ecommerce_cambios_devoluciones;
GO

-- Tabla principal de solicitudes
CREATE TABLE solicitudes_cambios_devoluciones (
    id INT IDENTITY(1,1) PRIMARY KEY,
    
    -- Información básica de la solicitud
    fecha_solicitud DATETIME2 DEFAULT GETDATE(),
    fecha_recepcion_producto DATE NOT NULL,
    dias_transcurridos AS DATEDIFF(DAY, fecha_recepcion_producto, CAST(fecha_solicitud AS DATE)) PERSISTED,
    
    -- Datos del cliente/solicitud
    cliente_nombre NVARCHAR(200),
    cliente_email NVARCHAR(200),
    cliente_telefono NVARCHAR(50),
    numero_pedido NVARCHAR(50),
    numero_factura NVARCHAR(50),
    
    -- Información del producto
    codigo_producto NVARCHAR(50),
    descripcion_producto NVARCHAR(500),
    precio_producto DECIMAL(10,2),
    cantidad_producto INT DEFAULT 1,
    
    -- Información de la solicitud
    medio_compra NVARCHAR(20) NOT NULL CHECK (medio_compra IN ('online', 'presencial')),
    motivo_solicitud NVARCHAR(20) NOT NULL CHECK (motivo_solicitud IN ('cambio', 'devolucion', 'falla')),
    producto_usado BIT NOT NULL DEFAULT 0,
    tiene_etiquetas BIT NOT NULL DEFAULT 1,
    observaciones NVARCHAR(MAX),
    
    -- Resultado de la evaluación
    resultado_evaluacion NVARCHAR(50),
    resultado_permitido BIT,
    resultado_tipo NVARCHAR(20),
    resultado_mensaje NVARCHAR(MAX),
    
    -- Decisión final
    decision_final NVARCHAR(20) CHECK (decision_final IN ('Aprobada', 'Rechazada', 'Pendiente')),
    decision_fecha DATETIME2,
    decision_usuario NVARCHAR(100),
    decision_observaciones NVARCHAR(MAX),
    
    -- Información de seguimiento
    estado_solicitud NVARCHAR(20) DEFAULT 'Activa' CHECK (estado_solicitud IN ('Activa', 'Procesada', 'Cancelada')),
    usuario_creacion NVARCHAR(100),
    usuario_procesamiento NVARCHAR(100),
    
    -- Timestamps
    created_at DATETIME2 DEFAULT GETDATE(),
    updated_at DATETIME2 DEFAULT GETDATE()
);
GO

-- Trigger para actualizar updated_at automáticamente
CREATE TRIGGER trg_solicitudes_updated_at
    ON solicitudes_cambios_devoluciones
    AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE solicitudes_cambios_devoluciones
    SET updated_at = GETDATE()
    FROM solicitudes_cambios_devoluciones s
    INNER JOIN inserted i ON s.id = i.id;
END;
GO

-- Tabla de seguimiento de estados (log de cambios)
CREATE TABLE solicitudes_seguimiento (
    id INT IDENTITY(1,1) PRIMARY KEY,
    solicitud_id INT NOT NULL,
    estado_anterior NVARCHAR(20),
    estado_nuevo NVARCHAR(20),
    usuario NVARCHAR(100),
    observaciones NVARCHAR(MAX),
    fecha_cambio DATETIME2 DEFAULT GETDATE(),
    
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_cambios_devoluciones(id)
);
GO

-- Tabla de configuración de reglas de negocio
CREATE TABLE reglas_negocio (
    id INT IDENTITY(1,1) PRIMARY KEY,
    clave NVARCHAR(50) UNIQUE NOT NULL,
    valor NVARCHAR(200) NOT NULL,
    descripcion NVARCHAR(500),
    activa BIT DEFAULT 1,
    created_at DATETIME2 DEFAULT GETDATE(),
    updated_at DATETIME2 DEFAULT GETDATE()
);
GO

-- Insertar reglas de negocio por defecto (Ley 24.240)
INSERT INTO reglas_negocio (clave, valor, descripcion) VALUES
('devolucion_online_dias', '10', 'Días permitidos para devolución en compras online'),
('cambio_online_dias', '30', 'Días permitidos para cambio en compras online'),
('cambio_presencial_dias', '15', 'Días permitidos para cambio en compras presenciales'),
('garantia_falla_dias', '365', 'Días de garantía por falla o defecto'),
('require_tags_for_return', '1', 'Requiere etiquetas para devolución (1=Sí, 0=No)'),
('require_unused_for_return', '1', 'Requiere producto no usado para devolución (1=Sí, 0=No)');
GO

-- Tabla de logs del sistema
CREATE TABLE sistema_logs (
    id INT IDENTITY(1,1) PRIMARY KEY,
    nivel NVARCHAR(10) NOT NULL, -- DEBUG, INFO, WARNING, ERROR
    mensaje NVARCHAR(MAX) NOT NULL,
    contexto NVARCHAR(MAX), -- JSON con información adicional
    usuario NVARCHAR(100),
    ip_address NVARCHAR(45),
    user_agent NVARCHAR(500),
    url NVARCHAR(500),
    created_at DATETIME2 DEFAULT GETDATE()
);
GO

-- Índices para optimizar consultas
CREATE INDEX IX_solicitudes_fecha_solicitud ON solicitudes_cambios_devoluciones(fecha_solicitud);
CREATE INDEX IX_solicitudes_medio_compra ON solicitudes_cambios_devoluciones(medio_compra);
CREATE INDEX IX_solicitudes_motivo ON solicitudes_cambios_devoluciones(motivo_solicitud);
CREATE INDEX IX_solicitudes_estado ON solicitudes_cambios_devoluciones(estado_solicitud);
CREATE INDEX IX_solicitudes_decision ON solicitudes_cambios_devoluciones(decision_final);
CREATE INDEX IX_solicitudes_numero_pedido ON solicitudes_cambios_devoluciones(numero_pedido);
CREATE INDEX IX_seguimiento_solicitud ON solicitudes_seguimiento(solicitud_id);
CREATE INDEX IX_logs_fecha ON sistema_logs(created_at);
CREATE INDEX IX_logs_nivel ON sistema_logs(nivel);
GO

-- Vista para reportes y estadísticas
CREATE VIEW vw_solicitudes_resumen AS
SELECT 
    s.id,
    s.fecha_solicitud,
    s.fecha_recepcion_producto,
    s.dias_transcurridos,
    s.medio_compra,
    s.motivo_solicitud,
    s.producto_usado,
    s.tiene_etiquetas,
    s.resultado_permitido,
    s.resultado_tipo,
    s.decision_final,
    s.estado_solicitud,
    
    -- Campos calculados para reportes
    CASE 
        WHEN s.dias_transcurridos <= 7 THEN 'Inmediata'
        WHEN s.dias_transcurridos <= 15 THEN 'Rápida'
        WHEN s.dias_transcurridos <= 30 THEN 'Normal'
        ELSE 'Tardía'
    END AS categoria_tiempo,
    
    CASE 
        WHEN s.producto_usado = 0 AND s.tiene_etiquetas = 1 THEN 'Óptimo'
        WHEN s.producto_usado = 0 OR s.tiene_etiquetas = 1 THEN 'Aceptable'
        ELSE 'Deficiente'
    END AS estado_producto_categoria,
    
    DATENAME(weekday, s.fecha_solicitud) AS dia_semana,
    DATENAME(month, s.fecha_solicitud) AS mes,
    YEAR(s.fecha_solicitud) AS año
    
FROM solicitudes_cambios_devoluciones s
WHERE s.estado_solicitud = 'Activa';
GO

-- Vista para estadísticas rápidas
CREATE VIEW vw_estadisticas_dashboard AS
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
    AVG(CAST(dias_transcurridos AS FLOAT)) AS promedio_dias
FROM solicitudes_cambios_devoluciones
WHERE estado_solicitud = 'Activa';
GO

-- Procedimiento almacenado para obtener estadísticas por período
CREATE PROCEDURE sp_estadisticas_periodo
    @fecha_inicio DATE,
    @fecha_fin DATE
AS
BEGIN
    SELECT 
        COUNT(*) AS total_solicitudes,
        COUNT(CASE WHEN decision_final = 'Aprobada' THEN 1 END) AS aprobadas,
        COUNT(CASE WHEN decision_final = 'Rechazada' THEN 1 END) AS rechazadas,
        AVG(CAST(dias_transcurridos AS FLOAT)) AS promedio_dias,
        
        -- Por motivo
        COUNT(CASE WHEN motivo_solicitud = 'devolucion' THEN 1 END) AS devoluciones,
        COUNT(CASE WHEN motivo_solicitud = 'cambio' THEN 1 END) AS cambios,
        COUNT(CASE WHEN motivo_solicitud = 'falla' THEN 1 END) AS fallas,
        
        -- Por medio
        COUNT(CASE WHEN medio_compra = 'online' THEN 1 END) AS online,
        COUNT(CASE WHEN medio_compra = 'presencial' THEN 1 END) AS presencial,
        
        -- Tasas de aprobación
        CASE 
            WHEN COUNT(*) > 0 
            THEN ROUND(COUNT(CASE WHEN decision_final = 'Aprobada' THEN 1 END) * 100.0 / COUNT(*), 2)
            ELSE 0 
        END AS tasa_aprobacion
        
    FROM solicitudes_cambios_devoluciones
    WHERE CAST(fecha_solicitud AS DATE) BETWEEN @fecha_inicio AND @fecha_fin
    AND estado_solicitud = 'Activa';
END;
GO

-- Función para validar reglas de negocio
CREATE FUNCTION fn_validar_solicitud(
    @dias_transcurridos INT,
    @medio_compra NVARCHAR(20),
    @motivo_solicitud NVARCHAR(20),
    @producto_usado BIT,
    @tiene_etiquetas BIT
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        CASE 
            -- Falla siempre permite garantía
            WHEN @motivo_solicitud = 'falla' THEN 1
            
            -- Devolución online
            WHEN @motivo_solicitud = 'devolucion' AND @medio_compra = 'online' 
                AND @dias_transcurridos <= 10 
                AND (@producto_usado = 0 OR @tiene_etiquetas = 1) THEN 1
                
            -- Cambio (online o presencial)
            WHEN @motivo_solicitud = 'cambio' 
                AND @producto_usado = 0 AND @tiene_etiquetas = 1
                AND ((@medio_compra = 'online' AND @dias_transcurridos <= 30) 
                     OR (@medio_compra = 'presencial' AND @dias_transcurridos <= 15)) THEN 1
                     
            ELSE 0
        END AS permitido,
        
        CASE 
            WHEN @motivo_solicitud = 'falla' THEN 'garantia'
            WHEN @motivo_solicitud = 'devolucion' THEN 'devolucion'
            WHEN @motivo_solicitud = 'cambio' THEN 'cambio'
            ELSE 'no_permitido'
        END AS tipo_resultado
);
GO