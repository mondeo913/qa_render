-- SIGET V2.0 - Instalación PostgreSQL
BEGIN;

CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS citext;


DO $$ BEGIN
    CREATE TYPE user_status AS ENUM ('ACTIVE','INACTIVE','BLOCKED');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE scheduled_load_status AS ENUM (
        'PROGRAMADA','ABIERTA','EN_CAPTURA','PARCIALMENTE_ENTREGADA',
        'ENTREGADA','EN_REVISION_INSTITUCIONAL','OBSERVADA',
        'LISTA_PARA_FIRMA','PENDIENTE_DOCUMENTO_FIRMADO',
        'VALIDADA','VALIDADO_Y_CERRADO','SUSPENDIDA',
        'REPROGRAMADA','REPROGRAMADA_ABIERTA','REPROGRAMADA_ENTREGADA',
        'VENCIDA','CANCELADA','REABIERTA'
    );
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE deliverable_status AS ENUM (
        'PENDIENTE','EN_CAPTURA','ENVIADO','EN_REVISION',
        'OBSERVADO','CORREGIDO','VALIDADO','CERRADO'
    );
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE import_status AS ENUM (
        'UPLOADED','VALIDATING','VALIDATED','WITH_ERRORS','CONFIRMED','CANCELLED'
    );
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE notification_channel AS ENUM ('IN_APP','EMAIL');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE notification_status AS ENUM ('PENDING','QUEUED','SENT','FAILED','READ');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE traffic_light AS ENUM (
        'GRAY','BLUE','YELLOW','PURPLE','ORANGE','GREEN','DARK_GREEN','RED'
    );
EXCEPTION WHEN duplicate_object THEN NULL; END $$;


CREATE TABLE IF NOT EXISTS roles (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS permissions (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    module VARCHAR(80) NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS permission_role (
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id BIGINT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE IF NOT EXISTS contracting_agencies (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(220) NOT NULL,
    legal_name VARCHAR(260),
    email_domain VARCHAR(160),
    active BOOLEAN NOT NULL DEFAULT TRUE,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS organizational_units (
    id BIGSERIAL PRIMARY KEY,
    contracting_agency_id BIGINT NOT NULL REFERENCES contracting_agencies(id),
    parent_id BIGINT REFERENCES organizational_units(id),
    code VARCHAR(70) NOT NULL,
    name VARCHAR(220) NOT NULL,
    unit_type VARCHAR(50) NOT NULL DEFAULT 'DIRECTION',
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (contracting_agency_id, code)
);

CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    role_id BIGINT NOT NULL REFERENCES roles(id),
    contracting_agency_id BIGINT REFERENCES contracting_agencies(id),
    organizational_unit_id BIGINT REFERENCES organizational_units(id),
    name VARCHAR(200) NOT NULL,
    email CITEXT NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status user_status NOT NULL DEFAULT 'ACTIVE',
    email_verified_at TIMESTAMPTZ,
    last_login_at TIMESTAMPTZ,
    remember_token VARCHAR(100),
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS user_scopes (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    contracting_agency_id BIGINT REFERENCES contracting_agencies(id) ON DELETE CASCADE,
    organizational_unit_id BIGINT REFERENCES organizational_units(id) ON DELETE CASCADE,
    can_read BOOLEAN NOT NULL DEFAULT TRUE,
    can_write BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(user_id, contracting_agency_id, organizational_unit_id)
);

CREATE TABLE IF NOT EXISTS catalogs (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    description TEXT,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS catalog_items (
    id BIGSERIAL PRIMARY KEY,
    catalog_id BIGINT NOT NULL REFERENCES catalogs(id) ON DELETE CASCADE,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(200) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(catalog_id, code)
);

CREATE TABLE IF NOT EXISTS evidence_templates (
    id BIGSERIAL PRIMARY KEY,
    contracting_agency_id BIGINT NOT NULL REFERENCES contracting_agencies(id),
    code VARCHAR(80) NOT NULL,
    name VARCHAR(240) NOT NULL,
    description TEXT,
    version INTEGER NOT NULL DEFAULT 1,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    requires_director_signature BOOLEAN NOT NULL DEFAULT TRUE,
    allowed_signed_extensions JSONB NOT NULL DEFAULT '["pdf","pdfa","jpg","jpeg","png","tif","tiff"]'::jsonb,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(contracting_agency_id, code, version)
);

CREATE TABLE IF NOT EXISTS template_requirements (
    id BIGSERIAL PRIMARY KEY,
    template_id BIGINT NOT NULL REFERENCES evidence_templates(id) ON DELETE CASCADE,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(240) NOT NULL,
    description TEXT,
    responsible_unit_id BIGINT REFERENCES organizational_units(id),
    responsible_role_code VARCHAR(60) NOT NULL DEFAULT 'OPERADOR',
    required BOOLEAN NOT NULL DEFAULT TRUE,
    requires_validation BOOLEAN NOT NULL DEFAULT TRUE,
    requires_signature BOOLEAN NOT NULL DEFAULT FALSE,
    min_files INTEGER NOT NULL DEFAULT 1 CHECK (min_files >= 0),
    max_files INTEGER NOT NULL DEFAULT 1 CHECK (max_files >= min_files),
    max_size_mb INTEGER NOT NULL DEFAULT 100 CHECK (max_size_mb > 0),
    allowed_extensions JSONB NOT NULL DEFAULT '["pdf","xlsx","docx","jpg","jpeg","png","mp4"]'::jsonb,
    sort_order INTEGER NOT NULL DEFAULT 0,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(template_id, code)
);

CREATE TABLE IF NOT EXISTS calendar_imports (
    id BIGSERIAL PRIMARY KEY,
    contracting_agency_id BIGINT NOT NULL REFERENCES contracting_agencies(id),
    uploaded_by BIGINT NOT NULL REFERENCES users(id),
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(700) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    workbook_version VARCHAR(50),
    status import_status NOT NULL DEFAULT 'UPLOADED',
    total_rows INTEGER NOT NULL DEFAULT 0,
    valid_rows INTEGER NOT NULL DEFAULT 0,
    error_rows INTEGER NOT NULL DEFAULT 0,
    warnings JSONB NOT NULL DEFAULT '[]'::jsonb,
    errors JSONB NOT NULL DEFAULT '[]'::jsonb,
    confirmed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS calendar_import_rows (
    id BIGSERIAL PRIMARY KEY,
    calendar_import_id BIGINT NOT NULL REFERENCES calendar_imports(id) ON DELETE CASCADE,
    sheet_name VARCHAR(120) NOT NULL,
    row_number INTEGER NOT NULL,
    contracting_agency_code VARCHAR(50),
    organizational_unit_code VARCHAR(70),
    template_code VARCHAR(80),
    original_open_at TIMESTAMPTZ,
    original_close_at TIMESTAMPTZ,
    delivery_name VARCHAR(240),
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_valid BOOLEAN NOT NULL DEFAULT FALSE,
    validation_messages JSONB NOT NULL DEFAULT '[]'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(calendar_import_id, sheet_name, row_number)
);

CREATE TABLE IF NOT EXISTS calendar_suspensions (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(220) NOT NULL UNIQUE,
    description TEXT,
    starts_at TIMESTAMPTZ NOT NULL,
    ends_at TIMESTAMPTZ NOT NULL,
    applies_to_all_agencies BOOLEAN NOT NULL DEFAULT TRUE,
    contracting_agency_id BIGINT REFERENCES contracting_agencies(id),
    block_upload BOOLEAN NOT NULL DEFAULT TRUE,
    exclude_from_compliance BOOLEAN NOT NULL DEFAULT TRUE,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (ends_at >= starts_at)
);

CREATE TABLE IF NOT EXISTS scheduled_loads (
    id BIGSERIAL PRIMARY KEY,
    calendar_import_id BIGINT NOT NULL REFERENCES calendar_imports(id),
    calendar_import_row_id BIGINT NOT NULL REFERENCES calendar_import_rows(id),
    contracting_agency_id BIGINT NOT NULL REFERENCES contracting_agencies(id),
    template_id BIGINT NOT NULL REFERENCES evidence_templates(id),
    title VARCHAR(260) NOT NULL,
    period_label VARCHAR(120),
    original_open_at TIMESTAMPTZ NOT NULL,
    original_close_at TIMESTAMPTZ NOT NULL,
    effective_open_at TIMESTAMPTZ NOT NULL,
    effective_close_at TIMESTAMPTZ NOT NULL,
    status scheduled_load_status NOT NULL DEFAULT 'PROGRAMADA',
    traffic_light traffic_light NOT NULL DEFAULT 'GRAY',
    is_blocked BOOLEAN NOT NULL DEFAULT FALSE,
    block_reason TEXT,
    retroactive BOOLEAN NOT NULL DEFAULT FALSE,
    delivered_at TIMESTAMPTZ,
    validated_at TIMESTAMPTZ,
    closed_at TIMESTAMPTZ,
    validated_by BIGINT REFERENCES users(id),
    closed_by BIGINT REFERENCES users(id),
    row_version INTEGER NOT NULL DEFAULT 1,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (original_close_at >= original_open_at),
    CHECK (effective_close_at >= effective_open_at)
);

CREATE TABLE IF NOT EXISTS load_reschedules (
    id BIGSERIAL PRIMARY KEY,
    scheduled_load_id BIGINT NOT NULL REFERENCES scheduled_loads(id) ON DELETE CASCADE,
    suspension_id BIGINT REFERENCES calendar_suspensions(id),
    old_open_at TIMESTAMPTZ NOT NULL,
    old_close_at TIMESTAMPTZ NOT NULL,
    new_open_at TIMESTAMPTZ,
    new_close_at TIMESTAMPTZ,
    reason TEXT NOT NULL,
    retroactive BOOLEAN NOT NULL DEFAULT TRUE,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    reprogrammed_by BIGINT REFERENCES users(id),
    reprogrammed_at TIMESTAMPTZ,
    notification_sent_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(scheduled_load_id, suspension_id),
    CHECK (new_close_at IS NULL OR new_open_at IS NOT NULL),
    CHECK (new_close_at IS NULL OR new_close_at >= new_open_at)
);

CREATE TABLE IF NOT EXISTS scheduled_load_deliverables (
    id BIGSERIAL PRIMARY KEY,
    scheduled_load_id BIGINT NOT NULL REFERENCES scheduled_loads(id) ON DELETE CASCADE,
    template_requirement_id BIGINT NOT NULL REFERENCES template_requirements(id),
    organizational_unit_id BIGINT NOT NULL REFERENCES organizational_units(id),
    responsible_user_id BIGINT REFERENCES users(id),
    status deliverable_status NOT NULL DEFAULT 'PENDIENTE',
    due_at TIMESTAMPTZ,
    submitted_at TIMESTAMPTZ,
    validated_at TIMESTAMPTZ,
    validated_by BIGINT REFERENCES users(id),
    observations TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(scheduled_load_id, template_requirement_id, organizational_unit_id)
);

CREATE TABLE IF NOT EXISTS repository_folders (
    id BIGSERIAL PRIMARY KEY,
    parent_id BIGINT REFERENCES repository_folders(id) ON DELETE CASCADE,
    contracting_agency_id BIGINT NOT NULL REFERENCES contracting_agencies(id),
    organizational_unit_id BIGINT REFERENCES organizational_units(id),
    scheduled_load_id BIGINT REFERENCES scheduled_loads(id) ON DELETE CASCADE,
    folder_type VARCHAR(60) NOT NULL,
    name VARCHAR(240) NOT NULL,
    path_key VARCHAR(900) NOT NULL UNIQUE,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS evidences (
    id BIGSERIAL PRIMARY KEY,
    scheduled_load_id BIGINT NOT NULL REFERENCES scheduled_loads(id) ON DELETE CASCADE,
    deliverable_id BIGINT NOT NULL REFERENCES scheduled_load_deliverables(id) ON DELETE CASCADE,
    folder_id BIGINT REFERENCES repository_folders(id),
    submitted_by BIGINT NOT NULL REFERENCES users(id),
    title VARCHAR(260) NOT NULL,
    description TEXT,
    status deliverable_status NOT NULL DEFAULT 'EN_CAPTURA',
    current_version INTEGER NOT NULL DEFAULT 1,
    submitted_at TIMESTAMPTZ,
    validated_at TIMESTAMPTZ,
    validated_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS evidence_files (
    id BIGSERIAL PRIMARY KEY,
    evidence_id BIGINT REFERENCES evidences(id) ON DELETE CASCADE,
    signed_document_id BIGINT,
    folder_id BIGINT REFERENCES repository_folders(id),
    uploaded_by BIGINT NOT NULL REFERENCES users(id),
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    storage_disk VARCHAR(60) NOT NULL,
    storage_path VARCHAR(900) NOT NULL,
    extension VARCHAR(20) NOT NULL,
    mime_type VARCHAR(160) NOT NULL,
    size_bytes BIGINT NOT NULL CHECK (size_bytes >= 0),
    sha256 CHAR(64) NOT NULL,
    antivirus_status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    version INTEGER NOT NULL DEFAULT 1,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS evidence_reviews (
    id BIGSERIAL PRIMARY KEY,
    evidence_id BIGINT NOT NULL REFERENCES evidences(id) ON DELETE CASCADE,
    reviewer_id BIGINT NOT NULL REFERENCES users(id),
    decision VARCHAR(30) NOT NULL CHECK (decision IN ('APPROVED','REJECTED','COMMENT')),
    comments TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS institutional_reviews (
    id BIGSERIAL PRIMARY KEY,
    scheduled_load_id BIGINT NOT NULL UNIQUE REFERENCES scheduled_loads(id) ON DELETE CASCADE,
    institutional_link_id BIGINT NOT NULL REFERENCES users(id),
    evidences_correct BOOLEAN NOT NULL DEFAULT FALSE,
    package_prepared_for_signature BOOLEAN NOT NULL DEFAULT FALSE,
    observations TEXT,
    started_at TIMESTAMPTZ,
    verified_at TIMESTAMPTZ,
    validated_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS signed_documents (
    id BIGSERIAL PRIMARY KEY,
    scheduled_load_id BIGINT NOT NULL REFERENCES scheduled_loads(id) ON DELETE CASCADE,
    folder_id BIGINT REFERENCES repository_folders(id),
    uploaded_by BIGINT NOT NULL REFERENCES users(id),
    document_type VARCHAR(60) NOT NULL DEFAULT 'DIRECTOR_SIGNED_PACKAGE',
    signer_name VARCHAR(220),
    signer_position VARCHAR(220),
    signed_on DATE,
    official_number VARCHAR(120),
    observations TEXT,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE evidence_files
    DROP CONSTRAINT IF EXISTS evidence_files_signed_document_id_fkey;
ALTER TABLE evidence_files
    ADD CONSTRAINT evidence_files_signed_document_id_fkey
    FOREIGN KEY (signed_document_id) REFERENCES signed_documents(id) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS load_closures (
    id BIGSERIAL PRIMARY KEY,
    scheduled_load_id BIGINT NOT NULL UNIQUE REFERENCES scheduled_loads(id),
    institutional_review_id BIGINT NOT NULL REFERENCES institutional_reviews(id),
    signed_document_id BIGINT NOT NULL REFERENCES signed_documents(id),
    closed_by BIGINT NOT NULL REFERENCES users(id),
    closed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    closing_comment TEXT,
    package_sha256 CHAR(64) NOT NULL,
    integrity_manifest JSONB NOT NULL,
    closure_certificate_path VARCHAR(900),
    reopened BOOLEAN NOT NULL DEFAULT FALSE,
    reopened_reason TEXT,
    reopened_by BIGINT REFERENCES users(id),
    reopened_at TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS load_status_history (
    id BIGSERIAL PRIMARY KEY,
    scheduled_load_id BIGINT NOT NULL REFERENCES scheduled_loads(id) ON DELETE CASCADE,
    old_status scheduled_load_status,
    new_status scheduled_load_status NOT NULL,
    changed_by BIGINT REFERENCES users(id),
    reason TEXT,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    scheduled_load_id BIGINT REFERENCES scheduled_loads(id) ON DELETE CASCADE,
    channel notification_channel NOT NULL,
    status notification_status NOT NULL DEFAULT 'PENDING',
    subject VARCHAR(260) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(700),
    scheduled_for TIMESTAMPTZ,
    sent_at TIMESTAMPTZ,
    read_at TIMESTAMPTZ,
    failure_message TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    event VARCHAR(100) NOT NULL,
    entity_type VARCHAR(180) NOT NULL,
    entity_id VARCHAR(120),
    old_values JSONB,
    new_values JSONB,
    ip_address INET,
    user_agent TEXT,
    request_id UUID,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS system_settings (
    id BIGSERIAL PRIMARY KEY,
    key VARCHAR(160) NOT NULL UNIQUE,
    value JSONB NOT NULL,
    description TEXT,
    updated_by BIGINT REFERENCES users(id),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);


CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END $$;

CREATE OR REPLACE FUNCTION date_overlaps_suspension(
    p_open TIMESTAMPTZ,
    p_close TIMESTAMPTZ,
    p_agency_id BIGINT DEFAULT NULL
)
RETURNS TABLE(suspension_id BIGINT, suspension_name TEXT)
LANGUAGE sql STABLE AS $$
    SELECT s.id, s.name::text
    FROM calendar_suspensions s
    WHERE s.active = TRUE
      AND tstzrange(p_open, p_close, '[]') && tstzrange(s.starts_at, s.ends_at, '[]')
      AND (
        s.applies_to_all_agencies = TRUE
        OR s.contracting_agency_id = p_agency_id
      )
    ORDER BY s.starts_at
    LIMIT 1;
$$;

CREATE OR REPLACE FUNCTION scheduled_load_is_enabled(
    p_load_id BIGINT,
    p_moment TIMESTAMPTZ DEFAULT now()
)
RETURNS BOOLEAN
LANGUAGE plpgsql STABLE AS $$
DECLARE
    v_load scheduled_loads%ROWTYPE;
    v_suspension_count INTEGER;
BEGIN
    SELECT * INTO v_load FROM scheduled_loads WHERE id = p_load_id;
    IF NOT FOUND THEN RETURN FALSE; END IF;

    IF v_load.is_blocked OR v_load.status IN (
        'SUSPENDIDA','REPROGRAMADA','VALIDADO_Y_CERRADO','CANCELADA','VENCIDA'
    ) THEN
        RETURN FALSE;
    END IF;

    SELECT count(*) INTO v_suspension_count
    FROM calendar_suspensions s
    WHERE s.active = TRUE
      AND s.block_upload = TRUE
      AND p_moment BETWEEN s.starts_at AND s.ends_at
      AND (s.applies_to_all_agencies OR s.contracting_agency_id = v_load.contracting_agency_id);

    IF v_suspension_count > 0 THEN RETURN FALSE; END IF;

    RETURN p_moment BETWEEN v_load.effective_open_at AND v_load.effective_close_at;
END $$;

CREATE OR REPLACE FUNCTION calculate_load_traffic_light(p_load_id BIGINT)
RETURNS traffic_light
LANGUAGE plpgsql STABLE AS $$
DECLARE
    v scheduled_loads%ROWTYPE;
    v_total INTEGER;
    v_submitted INTEGER;
    v_observed INTEGER;
    v_validated INTEGER;
    v_signed INTEGER;
BEGIN
    SELECT * INTO v FROM scheduled_loads WHERE id = p_load_id;
    IF NOT FOUND THEN RETURN 'GRAY'; END IF;

    IF v.status IN ('SUSPENDIDA','REPROGRAMADA','PROGRAMADA') THEN RETURN 'GRAY'; END IF;
    IF v.status = 'VALIDADO_Y_CERRADO' THEN RETURN 'DARK_GREEN'; END IF;

    SELECT count(*),
           count(*) FILTER (WHERE status IN ('ENVIADO','EN_REVISION','OBSERVADO','CORREGIDO','VALIDADO','CERRADO')),
           count(*) FILTER (WHERE status = 'OBSERVADO'),
           count(*) FILTER (WHERE status IN ('VALIDADO','CERRADO'))
      INTO v_total, v_submitted, v_observed, v_validated
      FROM scheduled_load_deliverables
     WHERE scheduled_load_id = p_load_id;

    SELECT count(*) INTO v_signed
      FROM signed_documents
     WHERE scheduled_load_id = p_load_id AND active = TRUE;

    IF v_observed > 0 THEN RETURN 'ORANGE'; END IF;
    IF v_total > 0 AND v_validated = v_total AND v_signed > 0 THEN RETURN 'GREEN'; END IF;
    IF v_total > 0 AND v_submitted = v_total THEN RETURN 'PURPLE'; END IF;
    IF v_submitted > 0 THEN RETURN 'YELLOW'; END IF;
    IF now() > v.effective_close_at THEN RETURN 'RED'; END IF;
    RETURN 'BLUE';
END $$;

CREATE OR REPLACE FUNCTION validate_load_closure(p_load_id BIGINT)
RETURNS BOOLEAN
LANGUAGE plpgsql AS $$
DECLARE
    v_review institutional_reviews%ROWTYPE;
    v_signed INTEGER;
    v_pending INTEGER;
BEGIN
    SELECT * INTO v_review
    FROM institutional_reviews
    WHERE scheduled_load_id = p_load_id;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'La carga no tiene revisión institucional';
    END IF;
    IF NOT v_review.evidences_correct THEN
        RAISE EXCEPTION 'Debe confirmar que las evidencias son correctas';
    END IF;
    IF NOT v_review.package_prepared_for_signature THEN
        RAISE EXCEPTION 'Debe confirmar que el expediente fue preparado para firma';
    END IF;

    SELECT count(*) INTO v_signed
    FROM signed_documents
    WHERE scheduled_load_id = p_load_id AND active = TRUE;

    IF v_signed = 0 THEN
        RAISE EXCEPTION 'Debe adjuntarse al menos un documento firmado';
    END IF;

    SELECT count(*) INTO v_pending
    FROM scheduled_load_deliverables d
    JOIN template_requirements r ON r.id = d.template_requirement_id
    WHERE d.scheduled_load_id = p_load_id
      AND r.required = TRUE
      AND d.status NOT IN ('VALIDADO','CERRADO');

    IF v_pending > 0 THEN
        RAISE EXCEPTION 'Existen entregables obligatorios pendientes de validación';
    END IF;

    RETURN TRUE;
END $$;

CREATE OR REPLACE FUNCTION close_scheduled_load(
    p_load_id BIGINT,
    p_user_id BIGINT,
    p_comment TEXT DEFAULT NULL
)
RETURNS BIGINT
LANGUAGE plpgsql AS $$
DECLARE
    v_review_id BIGINT;
    v_signed_id BIGINT;
    v_closure_id BIGINT;
    v_hash CHAR(64);
BEGIN
    PERFORM validate_load_closure(p_load_id);

    SELECT id INTO v_review_id
    FROM institutional_reviews WHERE scheduled_load_id = p_load_id;

    SELECT id INTO v_signed_id
    FROM signed_documents
    WHERE scheduled_load_id = p_load_id AND active = TRUE
    ORDER BY created_at DESC LIMIT 1;

    v_hash := encode(digest(p_load_id::text || ':' || p_user_id::text || ':' || clock_timestamp()::text, 'sha256'), 'hex');

    INSERT INTO load_closures (
        scheduled_load_id, institutional_review_id, signed_document_id,
        closed_by, closing_comment, package_sha256, integrity_manifest
    ) VALUES (
        p_load_id, v_review_id, v_signed_id, p_user_id, p_comment, v_hash,
        jsonb_build_object(
            'scheduled_load_id', p_load_id,
            'closed_by', p_user_id,
            'closed_at', now(),
            'status', 'VALIDADO_Y_CERRADO',
            'package_sha256', v_hash
        )
    )
    ON CONFLICT (scheduled_load_id) DO UPDATE SET
        signed_document_id = EXCLUDED.signed_document_id,
        closed_by = EXCLUDED.closed_by,
        closed_at = EXCLUDED.closed_at,
        closing_comment = EXCLUDED.closing_comment,
        package_sha256 = EXCLUDED.package_sha256,
        integrity_manifest = EXCLUDED.integrity_manifest
    RETURNING id INTO v_closure_id;

    UPDATE scheduled_loads SET
        status = 'VALIDADO_Y_CERRADO',
        traffic_light = 'DARK_GREEN',
        is_blocked = TRUE,
        block_reason = 'Cierre institucional concluido',
        validated_at = COALESCE(validated_at, now()),
        closed_at = now(),
        validated_by = COALESCE(validated_by, p_user_id),
        closed_by = p_user_id,
        row_version = row_version + 1,
        updated_at = now()
    WHERE id = p_load_id;

    INSERT INTO load_status_history (
        scheduled_load_id, old_status, new_status, changed_by, reason
    ) VALUES (
        p_load_id, 'VALIDADA', 'VALIDADO_Y_CERRADO', p_user_id, p_comment
    );

    RETURN v_closure_id;
END $$;

CREATE OR REPLACE FUNCTION apply_active_suspensions()
RETURNS INTEGER
LANGUAGE plpgsql AS $$
DECLARE
    v_count INTEGER := 0;
BEGIN
    WITH affected AS (
        SELECT l.id, s.id AS suspension_id
        FROM scheduled_loads l
        JOIN LATERAL date_overlaps_suspension(
            l.original_open_at, l.original_close_at, l.contracting_agency_id
        ) s ON TRUE
        WHERE l.status IN ('PROGRAMADA','ABIERTA')
    ),
    updated AS (
        UPDATE scheduled_loads l
        SET status = 'REPROGRAMADA',
            traffic_light = 'GRAY',
            is_blocked = TRUE,
            block_reason = 'Suspensión institucional; fecha pendiente de reprogramación',
            retroactive = TRUE,
            updated_at = now()
        FROM affected a
        WHERE l.id = a.id
        RETURNING l.id, a.suspension_id, l.original_open_at, l.original_close_at
    )
    INSERT INTO load_reschedules (
        scheduled_load_id, suspension_id, old_open_at, old_close_at,
        reason, retroactive, status
    )
    SELECT id, suspension_id, original_open_at, original_close_at,
           'Reprogramación automática por suspensión institucional global',
           TRUE, 'PENDING'
    FROM updated
    ON CONFLICT DO NOTHING;

    GET DIAGNOSTICS v_count = ROW_COUNT;
    RETURN v_count;
END $$;


DROP TRIGGER IF EXISTS trg_roles_updated_at ON roles;
CREATE TRIGGER trg_roles_updated_at BEFORE UPDATE ON roles
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_agencies_updated_at ON contracting_agencies;
CREATE TRIGGER trg_agencies_updated_at BEFORE UPDATE ON contracting_agencies
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_units_updated_at ON organizational_units;
CREATE TRIGGER trg_units_updated_at BEFORE UPDATE ON organizational_units
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_users_updated_at ON users;
CREATE TRIGGER trg_users_updated_at BEFORE UPDATE ON users
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_templates_updated_at ON evidence_templates;
CREATE TRIGGER trg_templates_updated_at BEFORE UPDATE ON evidence_templates
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_requirements_updated_at ON template_requirements;
CREATE TRIGGER trg_requirements_updated_at BEFORE UPDATE ON template_requirements
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_imports_updated_at ON calendar_imports;
CREATE TRIGGER trg_imports_updated_at BEFORE UPDATE ON calendar_imports
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_loads_updated_at ON scheduled_loads;
CREATE TRIGGER trg_loads_updated_at BEFORE UPDATE ON scheduled_loads
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_deliverables_updated_at ON scheduled_load_deliverables;
CREATE TRIGGER trg_deliverables_updated_at BEFORE UPDATE ON scheduled_load_deliverables
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_evidences_updated_at ON evidences;
CREATE TRIGGER trg_evidences_updated_at BEFORE UPDATE ON evidences
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_reviews_updated_at ON institutional_reviews;
CREATE TRIGGER trg_reviews_updated_at BEFORE UPDATE ON institutional_reviews
FOR EACH ROW EXECUTE FUNCTION set_updated_at();


CREATE INDEX IF NOT EXISTS idx_users_role_status ON users(role_id, status);
CREATE INDEX IF NOT EXISTS idx_users_agency_unit ON users(contracting_agency_id, organizational_unit_id);
CREATE INDEX IF NOT EXISTS idx_units_agency_parent ON organizational_units(contracting_agency_id, parent_id);
CREATE INDEX IF NOT EXISTS idx_imports_agency_status ON calendar_imports(contracting_agency_id, status);
CREATE INDEX IF NOT EXISTS idx_import_rows_import_valid ON calendar_import_rows(calendar_import_id, is_valid);
CREATE INDEX IF NOT EXISTS idx_loads_agency_effective ON scheduled_loads(contracting_agency_id, effective_open_at, effective_close_at);
CREATE INDEX IF NOT EXISTS idx_loads_status_light ON scheduled_loads(status, traffic_light);
CREATE INDEX IF NOT EXISTS idx_loads_original_window ON scheduled_loads(original_open_at, original_close_at);
CREATE INDEX IF NOT EXISTS idx_reschedules_pending ON load_reschedules(status, new_open_at);
CREATE INDEX IF NOT EXISTS idx_deliverables_load_status ON scheduled_load_deliverables(scheduled_load_id, status);
CREATE INDEX IF NOT EXISTS idx_evidences_load_status ON evidences(scheduled_load_id, status);
CREATE INDEX IF NOT EXISTS idx_files_evidence ON evidence_files(evidence_id);
CREATE INDEX IF NOT EXISTS idx_files_sha256 ON evidence_files(sha256);
CREATE INDEX IF NOT EXISTS idx_notifications_user_status ON notifications(user_id, status, scheduled_for);
CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_logs(entity_type, entity_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_folders_load_path ON repository_folders(scheduled_load_id, path_key);


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

COMMIT;
