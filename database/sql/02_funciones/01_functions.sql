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
