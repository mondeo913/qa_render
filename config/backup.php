<?php

return [
    'directory' => env('SIGET_BACKUP_DIRECTORY', storage_path('app/backups')),
    'minimum_free_bytes' => (int) env('SIGET_BACKUP_MIN_FREE_GB', 5) * 1024 ** 3,
    'alert_email' => env('SIGET_BACKUP_ALERT_EMAIL'),
    'maximum_age_days' => 35,
];
