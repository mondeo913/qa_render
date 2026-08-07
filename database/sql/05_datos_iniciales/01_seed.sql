INSERT INTO roles(code,name,description) VALUES
('ADMINISTRADOR','Administrador','Configuración general y control técnico'),
('DIRECTOR_GENERAL','Director General','Centro de Inteligencia e indicadores'),
('DIRECTOR','Director','Seguimiento de su dirección'),
('ENLACE_INSTITUCIONAL','Enlace Institucional','Operación institucional, pautas, revisión y cierre'),
('OPERADOR','Operador','Carga y seguimiento de evidencias de su dirección'),
('FISCALIZADOR','Fiscalizador','Consulta, repositorio y reportes')
ON CONFLICT (code) DO UPDATE SET name = EXCLUDED.name, description = EXCLUDED.description;

INSERT INTO permissions(code,name,module) VALUES
('dashboard.view','Ver dashboard','dashboard'),
('users.manage','Administrar usuarios','administration'),
('roles.manage','Administrar roles','administration'),
('agencies.manage','Administrar dependencias','administration'),
('catalogs.manage','Administrar catálogos','administration'),
('settings.manage','Administrar configuración','administration'),
('logs.view','Consultar logs','audit'),
('intelligence.view','Ver Centro de Inteligencia','intelligence'),
('indicators.view','Ver indicadores','indicators'),
('direction.dashboard','Ver dashboard de dirección','direction'),
('direction.repository','Ver repositorio de su dirección','repository'),
('calendar.view','Ver calendario','calendar'),
('calendar.import','Adjuntar Excel final de pautas','calendar'),
('calendar.confirm','Confirmar importación de pautas','calendar'),
('calendar.reschedule','Reprogramar cargas','calendar'),
('templates.manage','Administrar plantillas','templates'),
('evidence.upload','Cargar evidencias','evidence'),
('evidence.review','Revisar evidencias','evidence'),
('repository.view','Consultar repositorio','repository'),
('repository.download','Descargar evidencias','repository'),
('scheduled_load.review','Iniciar revisión institucional','closure'),
('scheduled_load.verify','Marcar verificaciones de cierre','closure'),
('scheduled_load.signature_package','Descargar expediente para firma','closure'),
('scheduled_load.upload_signed','Adjuntar documento firmado','closure'),
('scheduled_load.close','Validar y cerrar carga','closure'),
('scheduled_load.reopen','Reabrir carga cerrada','closure'),
('reports.view','Consultar reportes','reports'),
('reports.export','Exportar PDF y Excel','reports'),
('alerts.view','Consultar alertas','notifications')
ON CONFLICT (code) DO UPDATE SET name=EXCLUDED.name, module=EXCLUDED.module;

INSERT INTO permission_role(role_id, permission_id)
SELECT r.id, p.id
FROM roles r CROSS JOIN permissions p
WHERE
    (r.code = 'ADMINISTRADOR')
 OR (r.code = 'DIRECTOR_GENERAL' AND p.code IN ('intelligence.view','indicators.view','reports.view','repository.view'))
 OR (r.code = 'DIRECTOR' AND p.code IN ('direction.dashboard','direction.repository','indicators.view','reports.view','repository.view','repository.download'))
 OR (r.code = 'ENLACE_INSTITUCIONAL' AND p.code IN (
     'dashboard.view','calendar.view','calendar.import','calendar.confirm','calendar.reschedule',
     'templates.manage','evidence.review','repository.view','repository.download',
     'scheduled_load.review','scheduled_load.verify','scheduled_load.signature_package',
     'scheduled_load.upload_signed','scheduled_load.close','reports.view','reports.export',
     'alerts.view','indicators.view'
 ))
 OR (r.code = 'OPERADOR' AND p.code IN (
     'dashboard.view','calendar.view','evidence.upload','repository.view','repository.download',
     'reports.view','alerts.view'
 ))
 OR (r.code = 'FISCALIZADOR' AND p.code IN ('repository.view','repository.download','reports.view','reports.export'))
ON CONFLICT DO NOTHING;

INSERT INTO contracting_agencies(code,name,legal_name) VALUES
('IMSS','IMSS','Instituto Mexicano del Seguro Social'),
('IPAB','IPAB','Instituto para la Protección al Ahorro Bancario')
ON CONFLICT (code) DO UPDATE SET name=EXCLUDED.name, legal_name=EXCLUDED.legal_name;

INSERT INTO calendar_suspensions(
    name, description, starts_at, ends_at,
    applies_to_all_agencies, block_upload, exclude_from_compliance, active
) VALUES (
    'Suspensión institucional agosto-septiembre 2026',
    'Todas las pautas con fechas del 25 de agosto al 8 de septiembre de 2026 quedan inhabilitadas y deben reprogramarse de forma retroactiva después del 8 de septiembre.',
    '2026-08-25 00:00:00-06',
    '2026-09-08 23:59:59-06',
    TRUE, TRUE, TRUE, TRUE
)
ON CONFLICT DO NOTHING;

INSERT INTO system_settings(key,value,description) VALUES
('calendar.non_programmed_days_disabled','true'::jsonb,'Solo fechas provenientes del Excel final confirmado habilitan carga'),
('calendar.rescheduled_tooltip',to_jsonb('Esta fecha está reprogramada para después del 8 de septiembre de 2026.'::text),'Texto mostrado en fechas reprogramadas'),
('repository.preferred_signed_format',to_jsonb('pdf/a'::text),'Formato preferido del documento firmado'),
('queue.connection',to_jsonb('database'::text),'Conexión inicial de colas Laravel')
ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value, description=EXCLUDED.description;
