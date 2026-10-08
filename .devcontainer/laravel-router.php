<?php

declare(strict_types=1);

$publicPath = realpath(__DIR__.'/../public');

if ($publicPath === false) {
    http_response_code(500);
    echo 'SIGET: no se encontró el directorio public.';
    return;
}

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

// Sirve directamente archivos estáticos existentes; el resto pasa a Laravel.
if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
