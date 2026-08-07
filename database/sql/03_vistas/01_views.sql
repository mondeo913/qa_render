CREATE OR REPLACE VIEW vw_calendar_events AS
SELECT
    l.id,
    l.title,
    l.contracting_agency_id,
    a.name AS contracting_agency,
    l.original_open_at,
    l.original_close_at,
    l.effective_open_at,
    l.effective_close_at,
    l.status,
    l.traffic_light,
    scheduled_load_is_enabled(l.id, now()) AS upload_enabled,
    CASE
        WHEN l.status IN ('SUSPENDIDA','REPROGRAMADA') THEN
            'Esta fecha está reprogramada para después del 8 de septiembre de 2026.'
        WHEN scheduled_load_is_enabled(l.id, now()) THEN
            'Carga habilitada dentro de la ventana efectiva.'
        ELSE
            'La carga no se encuentra habilitada.'
    END AS tooltip
FROM scheduled_loads l
JOIN contracting_agencies a ON a.id = l.contracting_agency_id;

CREATE OR REPLACE VIEW vw_load_progress AS
SELECT
    l.id AS scheduled_load_id,
    l.contracting_agency_id,
    l.title,
    l.status,
    l.traffic_light,
    count(d.id) AS total_deliverables,
    count(d.id) FILTER (WHERE d.status IN ('ENVIADO','EN_REVISION','OBSERVADO','CORREGIDO','VALIDADO','CERRADO')) AS submitted_deliverables,
    count(d.id) FILTER (WHERE d.status IN ('VALIDADO','CERRADO')) AS validated_deliverables,
    count(d.id) FILTER (WHERE d.status = 'OBSERVADO') AS observed_deliverables,
    round(
        CASE WHEN count(d.id) = 0 THEN 0
        ELSE 100.0 * count(d.id) FILTER (WHERE d.status IN ('VALIDADO','CERRADO')) / count(d.id)
        END, 2
    ) AS validated_percentage
FROM scheduled_loads l
LEFT JOIN scheduled_load_deliverables d ON d.scheduled_load_id = l.id
GROUP BY l.id;

CREATE OR REPLACE VIEW vw_agency_compliance AS
SELECT
    a.id AS contracting_agency_id,
    a.code,
    a.name,
    count(l.id) FILTER (WHERE l.status NOT IN ('SUSPENDIDA','REPROGRAMADA','CANCELADA')) AS measurable_loads,
    count(l.id) FILTER (WHERE l.status = 'VALIDADO_Y_CERRADO') AS closed_loads,
    count(l.id) FILTER (WHERE l.status IN ('SUSPENDIDA','REPROGRAMADA')) AS reprogrammed_loads,
    count(l.id) FILTER (WHERE l.status = 'VENCIDA') AS expired_loads,
    round(
        CASE WHEN count(l.id) FILTER (WHERE l.status NOT IN ('SUSPENDIDA','REPROGRAMADA','CANCELADA')) = 0 THEN 0
        ELSE 100.0 * count(l.id) FILTER (WHERE l.status = 'VALIDADO_Y_CERRADO') /
             count(l.id) FILTER (WHERE l.status NOT IN ('SUSPENDIDA','REPROGRAMADA','CANCELADA'))
        END, 2
    ) AS compliance_percentage
FROM contracting_agencies a
LEFT JOIN scheduled_loads l ON l.contracting_agency_id = a.id
GROUP BY a.id;

CREATE MATERIALIZED VIEW IF NOT EXISTS mv_daily_indicators AS
SELECT
    current_date AS snapshot_date,
    contracting_agency_id,
    measurable_loads,
    closed_loads,
    reprogrammed_loads,
    expired_loads,
    compliance_percentage
FROM vw_agency_compliance;

CREATE UNIQUE INDEX IF NOT EXISTS ux_mv_daily_indicators
ON mv_daily_indicators(snapshot_date, contracting_agency_id);
