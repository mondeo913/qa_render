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
