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
