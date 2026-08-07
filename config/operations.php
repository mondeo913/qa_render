<?php
return [
 'metrics_token'=>env('SIGET_METRICS_TOKEN'),
 'retention_days'=>(int)env('SIGET_OPERATIONAL_METRICS_RETENTION_DAYS',90),
 'health_cache_seconds'=>(int)env('SIGET_HEALTH_CACHE_SECONDS',30),
];
