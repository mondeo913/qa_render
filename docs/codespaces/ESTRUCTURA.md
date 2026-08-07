# Estructura consolidada

```text
SIGET_CODESPACES_QA_COMPLETO_V1/
├── .devcontainer/
│   ├── devcontainer.json
│   ├── docker-compose.yml
│   ├── Dockerfile
│   ├── php.ini
│   ├── supervisord.conf
│   └── scripts/
├── .codespace/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── artisan
├── composer.json
├── package.json
├── Makefile
└── README.md
```

La aplicación y la configuración de Codespaces forman un único repositorio. No es necesario copiar fases ni overlays después de crear el Codespace.
