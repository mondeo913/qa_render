<?php

return [
    'stateful' => [],
    'guard' => [],
    'expiration' => (int) env('SIGET_API_TOKEN_MINUTES', 480),
    'token_prefix' => 'siget_',
    'middleware' => [],
];
