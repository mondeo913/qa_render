<?php

return [
    'token_minutes' => (int) env('SIGET_API_TOKEN_MINUTES', 480),
    'login_per_minute' => (int) env('SIGET_API_LOGIN_PER_MINUTE', 5),
    'requests_per_minute' => (int) env('SIGET_API_REQUESTS_PER_MINUTE', 120),
];
