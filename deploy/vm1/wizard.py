#!/usr/bin/env python3
"""Asistente local de infraestructura. No publica un instalador web ni guarda claves en git."""
import getpass
import ipaddress
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import subprocess
import sys
from urllib.parse import urlparse

DEPLOY = Path(__file__).resolve().parent
RELEASE = DEPLOY.parent.parent
ENV = DEPLOY / '.env.production'


def run(args, **kwargs):
    return subprocess.run([str(arg) for arg in args], check=True, **kwargs)


def prompt(label, default='', secret=False):
    value = (getpass.getpass(label + ': ') if secret else input(label + (f' [{default}]' if default else '') + ': ')).strip()
    value = value or default
    if '\n' in value or '\r' in value or '\x00' in value:
        raise ValueError('No se permiten saltos de línea en la configuración.')
    return value


def dotenv(value):
    # Compose interpolates double-quoted values; $$ preserves a literal dollar sign.
    return json.dumps(str(value), ensure_ascii=False).replace('$', '$$')


def check_host():
    if os.geteuid() != 0:
        raise ValueError('Ejecute el asistente con sudo en VM1.')
    for command in ['docker', 'findmnt', 'openssl', 'systemctl', 'setpriv']:
        if not shutil.which(command):
            raise ValueError(f'Falta {command}. Instale los prerrequisitos del manual de infraestructura.')
    if re.search(r'\s', str(RELEASE)):
        raise ValueError('Instale el paquete en una ruta sin espacios, por ejemplo /opt/siget/current.')
    version = run(['docker', 'compose', 'version', '--short'], capture_output=True, text=True).stdout.strip().lstrip('v')
    parts = re.match(r'(\d+)\.(\d+)', version)
    if not parts or tuple(map(int, parts.groups())) < (2, 24):
        raise ValueError('Se requiere Docker Compose 2.24 o posterior.')
    mount_type = run(['findmnt', '-n', '-o', 'FSTYPE', '--target', '/srv/siget/repository'], capture_output=True, text=True).stdout.strip()
    if mount_type != 'nfs4':
        raise ValueError('Monte primero el repositorio de VM2 con mount-repository.sh. No se acepta un directorio local.')
    repository_id = run(['setpriv', '--reuid=33', '--regid=33', '--clear-groups', 'cat', '/srv/siget/repository/.siget-repository-id'], capture_output=True, text=True).stdout.strip()
    if not re.fullmatch(r'[a-f0-9-]{36}', repository_id):
        raise ValueError('Identificador de repositorio no válido.')
    for path in ['/etc/siget/tls/fullchain.pem', '/etc/siget/tls/privkey.pem']:
        if not Path(path).is_file():
            raise ValueError(f'Falta el certificado institucional: {path}')
    run(['openssl', 'x509', '-in', '/etc/siget/tls/fullchain.pem', '-noout', '-checkend', '86400'], stdout=subprocess.DEVNULL)
    return repository_id


def main():
    print('SIGET · Asistente de instalación en dos VM · Respaldos mensuales')
    print('1/6 Verificación del servidor, montaje NFSv4 y certificados')
    repository_id = check_host()
    if '--check' in sys.argv:
        print('Prerrequisitos correctos. No se modificó la configuración.')
        return
    values = {}
    if ENV.exists():
        if '--resume' not in sys.argv:
            raise ValueError('Ya existe configuración. Use install.sh --resume para completar un intento o upgrade.sh para actualizar.')
        for line in ENV.read_text().splitlines():
            if line and not line.startswith('#'):
                key, value = line.split('=', 1)
                values[key] = (json.loads(value) if value.startswith('"') else value).replace('$$', '$')
        if values.get('SIGET_REPOSITORY_ID') != repository_id:
            raise ValueError('El repositorio montado no coincide con el configurado. Revise VM2.')
        url = values['APP_URL']
        backup_day = int(values.get('SIGET_BACKUP_DAY', '1'))
        print('2/6 Reanudando con las claves y configuración existentes')
    else:
        values = configure(repository_id)
        url = values['APP_URL']
        backup_day = int(values['SIGET_BACKUP_DAY'])
    parsed = urlparse(url)
    if parsed.scheme != 'https' or not parsed.hostname or parsed.path not in ['', '/'] or parsed.query or parsed.username or parsed.port not in [None, 443]:
        raise ValueError('Indique una URL HTTPS válida, sin credenciales ni subruta, en el puerto 443.')
    run(['openssl', 'x509', '-in', '/etc/siget/tls/fullchain.pem', '-noout', '-checkhost', parsed.hostname], stdout=subprocess.DEVNULL)
    os.umask(0o077)
    if not ENV.exists():
        with ENV.open('x') as output:
            output.write('\n'.join(f'{key}={dotenv(value)}' for key, value in values.items()) + '\n')
    os.chmod(ENV, 0o600)
    run(['install', '-d', '-o', '33', '-g', '33', '-m', '0700', '/srv/siget/backups'])
    compose = ['docker', 'compose', '--env-file', str(ENV), '-f', str(DEPLOY / 'compose.yml')]
    print('3/6 Preparación de imágenes y servicios privados')
    images = RELEASE / 'images.tar.gz'
    if images.exists():
        run(['docker', 'load', '-i', str(images)])
    else:
        run(compose + ['build', 'app', 'web', 'antivirus'])
        run(compose + ['pull', 'database'])
    run(compose + ['config', '--quiet'])
    run(compose + ['up', '-d', '--wait', '--wait-timeout', '600', 'database', 'antivirus'])
    print('4/6 Migraciones y administrador inicial, sin datos QA')
    run(compose + ['run', '--rm', '--no-deps', '-T', 'app', 'php', 'artisan', 'migrate', '--force'])
    run(compose + ['run', '--rm', '--no-deps', 'app', 'php', 'artisan', 'siget:install'])
    print('5/6 Arranque automático, watchdog y respaldo mensual')
    for source in DEPLOY.glob('siget*.*'):
        if source.suffix not in ['.service', '.timer']:
            continue
        content = source.read_text().replace('@RELEASE_DIR@', str(RELEASE))
        if source.name == 'siget-backup.timer':
            content = content.replace('*-*-01', f'*-*-{backup_day:02d}').replace('día 1 ', f'día {backup_day} ')
        destination = Path('/etc/systemd/system') / source.name
        destination.write_text(content)
        destination.chmod(0o644)
    run(['systemctl', 'daemon-reload'])
    run(['systemctl', 'enable', '--now', 'siget.service', 'siget-watchdog.timer', 'siget-backup.timer'])
    print('6/6 Validación, respaldo inicial y restauración de prueba')
    run(compose + ['exec', '-T', 'app', 'php', 'artisan', 'siget:readiness'])
    run(['systemctl', 'start', 'siget-backup.service'])
    run(compose + ['exec', '-T', 'app', 'php', 'artisan', 'siget:readiness'])
    run(['systemctl', 'list-timers', 'siget-*', '--no-pager'])
    print(f'Instalación completada: {url}. Acceso desde el navegador con usuarios autorizados.')
    print('Respaldo inicial completado y calendario de respaldos MENSUALES habilitado.')
    print('Custodie .env.production y la clave APP_KEY en el mecanismo institucional de secretos.')


def configure(repository_id):
    print('2/6 Configuración institucional')
    url = prompt('URL HTTPS de SIGET', 'https://siget.institucion.gob.mx')
    smtp_host = prompt('Servidor SMTP institucional')
    smtp_port = int(prompt('Puerto SMTP', '587'))
    if not smtp_host or not 1 <= smtp_port <= 65535:
        raise ValueError('Revise el servidor y puerto SMTP.')
    smtp_scheme = prompt('Esquema SMTP: smtp para STARTTLS; smtps para TLS implícito', 'smtp')
    if smtp_scheme not in ['smtp', 'smtps']:
        raise ValueError('El esquema debe ser smtp o smtps.')
    smtp_user = prompt('Usuario SMTP (vacío si se autoriza por IP)')
    smtp_password = prompt('Contraseña SMTP (vacío si se autoriza por IP)', secret=True)
    sender = prompt('Correo remitente institucional')
    support = prompt('Correo de soporte')
    accounting = prompt('Correos de contabilidad, separados por comas')
    for address in [sender, support] + [a.strip() for a in accounting.split(',') if a.strip()]:
        if not re.fullmatch(r'[^\s@]+@[^\s@]+\.[^\s@]+', address):
            raise ValueError('Revise los correos institucionales.')
    backup_day = int(prompt('Día del respaldo MENSUAL (1 a 28), a las 02:00 Ciudad de México', '1'))
    if not 1 <= backup_day <= 28:
        raise ValueError('El día del respaldo mensual debe estar entre 1 y 28.')
    values = {}
    for line in (DEPLOY / '.env.production.example').read_text().splitlines():
        if line and not line.startswith('#'):
            key, value = line.split('=', 1)
            values[key] = value
    values.update(APP_URL=url.rstrip('/'), APP_KEY='base64:' + __import__('base64').b64encode(secrets.token_bytes(32)).decode(),
                  DB_PASSWORD=secrets.token_hex(32), SIGET_DATABASE_ADMIN_PASSWORD=secrets.token_hex(32),
                  SIGET_METRICS_TOKEN=secrets.token_hex(32), SIGET_REPOSITORY_ID=repository_id,
                  MAIL_HOST=smtp_host, MAIL_PORT=smtp_port, MAIL_SCHEME=smtp_scheme, MAIL_USERNAME=smtp_user,
                  MAIL_PASSWORD=smtp_password, MAIL_FROM_ADDRESS=sender, SIGET_SUPPORT_EMAIL=support,
                  SIGET_ACCOUNTING_RECIPIENTS=accounting, SIGET_BACKUP_DAY=backup_day)
    return values


if __name__ == '__main__':
    try:
        main()
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        print(f'Instalación detenida: {error}. No elimine volúmenes ni la configuración existente.', file=sys.stderr)
        raise SystemExit(1)
