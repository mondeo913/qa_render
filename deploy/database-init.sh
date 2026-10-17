#!/usr/bin/env bash
set -euo pipefail
# psql receives credentials through environment, never command-line SQL/password arguments.
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<'SQL'
\getenv app_user SIGET_APP_DB_USER
\getenv app_password SIGET_APP_DB_PASSWORD
\getenv app_database POSTGRES_DB
SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD %L', :'app_user', :'app_password') \gexec
SELECT format('GRANT CONNECT, TEMPORARY ON DATABASE %I TO %I', :'app_database', :'app_user') \gexec
SELECT format('ALTER SCHEMA public OWNER TO %I', :'app_user') \gexec
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
SQL
