#!/usr/bin/env python3
"""Real HTTPS/API/ClamAV acceptance against an isolated Compose project, never real VMs."""
import datetime
import json
import os
from pathlib import Path
import secrets
import ssl
import subprocess
import sys
import urllib.error
import urllib.request

if os.environ.get('SIGET_PRODUCTION_SMOKE') != '1':
    raise SystemExit('Sólo se permite este script en la prueba aislada de CI.')
context = ssl.create_default_context(cafile=os.environ['SIGET_CI_CERT'])
base = 'https://localhost/api/v1'
compose = ['docker', 'compose', '--env-file', sys.argv[1], '-f', 'deploy/vm1/compose.yml', '-p', 'siget_ci_production']
count = 0


def request(method, path, token=None, data=None, file=None, expected=200):
    global count
    headers = {'Accept': 'application/json'}
    if token:
        headers['Authorization'] = 'Bearer ' + token
    if file:
        boundary = 'siget-' + secrets.token_hex(12)
        body = bytearray()
        for key, value in (data or {}).items():
            body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
        key, name, contents = file
        body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode())
        body.extend(contents)
        body.extend(f'\r\n--{boundary}--\r\n'.encode())
        headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
        body = bytes(body)
    else:
        body = json.dumps(data).encode() if data is not None else None
        if body:
            headers['Content-Type'] = 'application/json'
    try:
        response = urllib.request.urlopen(urllib.request.Request(base + path, method=method, data=body, headers=headers), context=context, timeout=120)
    except urllib.error.HTTPError as error:
        response = error
    payload = response.read()
    if response.status != expected:
        # Do not print credentials/tokens from any response.
        raise AssertionError(f'{method} {path}: esperado {expected}, recibido {response.status}; {payload[:250] if response.status >= 400 else b""}')
    count += 1
    return json.loads(payload) if 'application/json' in response.headers.get('Content-Type', '') and payload else payload


def fixture(action, load_id):
    subprocess.run(compose + ['exec', '-T', 'app', 'php', 'deploy/tests/production-fixture.php', action, str(load_id)], check=True)


password = os.environ['SIGET_CI_ADMIN_PASSWORD']
admin = request('POST', '/auth/token', data={'email': 'admin@institucion.test', 'password': password, 'device_name': 'Production acceptance'}, expected=201)['access_token']
roles = {role['code']: role['id'] for role in request('GET', '/admin/roles', admin)['data']}
agencies = request('GET', '/admin/agencies', admin)['data']
assert len(agencies) == 7
agency = next(agency for agency in agencies if agency['code'] == 'IMSS')
units = {unit['code']: unit['id'] for unit in agency['units']}
tokens = {'ADMINISTRADOR': admin}
users = {}
for role in roles:
    if role == 'ADMINISTRADOR':
        continue
    direction = 'DIR_A' if role.endswith('TRANSMISION') else ('DIR_B' if role.endswith('PROGRAMACION_CONTINUIDAD') else None)
    body = {'name': 'Aceptación ' + role, 'email': role.lower() + '@institucion.test', 'password': password,
            'role_id': roles[role], 'general_profile': role != 'FISCALIZADOR'}
    if direction:
        body['organizational_unit_id'] = units[direction]
    users[role] = request('POST', '/admin/users', admin, body, expected=201)['data']['id']
    tokens[role] = request('POST', '/auth/token', data={'email': body['email'], 'password': password, 'device_name': 'CI ' + role}, expected=201)['access_token']
assert len(tokens) == 8
enlace = tokens['ENLACE_INSTITUCIONAL']
request('POST', '/admin/agencies', admin, {'code': 'CI_NUEVA', 'name': 'Dependencia de aceptación'}, expected=201)
assert len(request('GET', '/agencies', enlace)['data']) == 8
assert len(request('GET', '/agencies', tokens['OPERADOR_TRANSMISION'])['data']) == 8
request('GET', '/admin/users', enlace, expected=403)
request('GET', '/loads', expected=401)
request('GET', '/reports/csv', tokens['OPERADOR_TRANSMISION'], expected=403)
request('GET', '/reports/csv', tokens['DIRECTOR_GENERAL'])
pauta = request('POST', '/imports', enlace, {'contracting_agency_id': agency['id'], 'schedule_year': int(sys.argv[3])},
                ('calendar_file', 'pauta.xlsx', Path(sys.argv[2]).read_bytes()), expected=201)['data']['id']
assert request('POST', f'/imports/{pauta}/confirm', enlace, {})['loads_created'] == 2
request('POST', f'/imports/{pauta}/confirm', enlace, {}, expected=422)
load = request('GET', '/loads?calendar_import_id=' + str(pauta), enlace)['data'][0]['id']
fixture('open', load)
details = request('GET', f'/loads/{load}', enlace)['data']['deliverables']
pdf = b'%PDF-1.4\n% SIGET acceptance document\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n'
eicar = b'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*'
evidence_ids = {}
for direction, operator, director in [('DIR_A', 'OPERADOR_TRANSMISION', 'DIRECTOR_TRANSMISION'), ('DIR_B', 'OPERADOR_PROGRAMACION_CONTINUIDAD', 'DIRECTOR_PROGRAMACION_CONTINUIDAD')]:
    deliverable = next(item for item in details if item['unit']['code'] == direction)
    rejected = request('POST', '/evidences', tokens[operator], {'deliverable_id': deliverable['id']}, ('file', 'infectado.pdf', eicar), expected=422)
    assert 'antivirus' in rejected['message']
    evidence = request('POST', '/evidences', tokens[operator], {'deliverable_id': deliverable['id']}, ('file', 'evidencia.pdf', pdf), expected=201)['data']
    evidence_ids[direction] = evidence['id']
    assert all(key not in json.dumps(evidence) for key in ['storage_path', 'storage_disk', 'stored_name'])
    request('GET', f'/files/{evidence["files"][0]["id"]}/download', tokens[operator])
    other_director = tokens['DIRECTOR_PROGRAMACION_CONTINUIDAD' if direction == 'DIR_A' else 'DIRECTOR_TRANSMISION']
    request('GET', f'/evidences/{evidence["id"]}', other_director, expected=403)
    request('POST', f'/evidences/{evidence["id"]}/submit', tokens[operator], {})
    request('POST', f'/evidences/{evidence["id"]}/reviews', tokens[director], {'decision': 'APROBADO'})
    assert request('POST', f'/evidences/{evidence["id"]}/reviews', enlace, {'decision': 'APROBADO'})['data']['status'] == 'VALIDADO'
request('GET', f'/loads/{load}', tokens['FISCALIZADOR'], expected=403)
request('PUT', f'/loads/{load}/fiscal-assignment', enlace, {'fiscalizador_id': users['FISCALIZADOR']})
assert request('POST', f'/evidences/{evidence_ids["DIR_A"]}/reviews', tokens['FISCALIZADOR'], {'decision': 'OBSERVADO', 'comments': 'Revisión independiente de fiscalización'})['data']['status'] == 'VALIDADO'
request('POST', f'/loads/{load}/close', tokens['DIRECTOR_GENERAL'], {}, expected=403)
fixture('expire', load)
request('POST', f'/loads/{load}/checklist', enlace, {'evidences_correct': True, 'package_prepared_for_signature': True})
package = request('POST', f'/loads/{load}/signature-package', enlace, {})
assert package.startswith(b'%PDF')
request('POST', f'/loads/{load}/signed-document', enlace, {'signer_name': 'Director autorizado'}, ('file', 'firmado.pdf', pdf), expected=201)
assert request('POST', f'/loads/{load}/close', enlace, {'closing_comment': 'Aceptación integral del entorno de producción'})['data']['status'] == 'VALIDADO_Y_CERRADO'
start = datetime.date.today().replace(day=1)
end = (start.replace(day=28) + datetime.timedelta(days=4)).replace(day=1)
for role, token in tokens.items():
    request('GET', '/me', token)
    request('GET', '/dashboard', token)
    request('GET', f'/calendar/events?start={start}&end={end}', token)
request('DELETE', '/auth/token', admin, expected=204)
request('GET', '/me', admin, expected=401)
print(f'PRODUCTION HTTPS API PASS: {count} solicitudes; 8 perfiles; 2 direcciones; antivirus EICAR; pauta a cierre; fiscalización; aislamiento y revocación.')
