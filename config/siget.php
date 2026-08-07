<?php
return [
    'name' => env('SIGET_NAME', 'SIGET'),
    'subtitle' => env('SIGET_SUBTITLE', 'Sistema de Gestión de Evidencias de Transmisión'),
    'environment_label' => env('SIGET_ENVIRONMENT_LABEL', env('APP_ENV','production') === 'production' ? 'PRODUCCIÓN' : 'QA CODESPACES'),
    'institution_name' => env('SIGET_INSTITUTION_NAME', 'Sistema Público de Radiodifusión del Estado Mexicano'),
    'support_email' => env('SIGET_SUPPORT_EMAIL', 'soporte.siget@institucion.local'),
    'default_theme' => env('SIGET_DEFAULT_THEME', 'auto'),
    'mailpit_url' => env('SIGET_MAILPIT_URL'),
    'repository_disk' => env('SIGET_REPOSITORY_DISK', env('FILESYSTEM_DISK','local')),
    'repository_root' => env('SIGET_REPOSITORY_ROOT','siget'),
    'antivirus_enabled' => (bool) env('SIGET_ANTIVIRUS_ENABLED',false),
    'antivirus_command' => env('SIGET_ANTIVIRUS_COMMAND','clamscan'),
    'rescheduled_tooltip' => 'Esta fecha está reprogramada para después del 8 de septiembre de 2026.',
    'suspension_2026' => ['starts_at'=>'2026-08-25 00:00:00','ends_at'=>'2026-09-08 23:59:59'],
    'accounting_recipients' => array_values(array_filter(array_map('trim', explode(',', env('SIGET_ACCOUNTING_RECIPIENTS', 'contabilidad@siget.local'))))),
];
