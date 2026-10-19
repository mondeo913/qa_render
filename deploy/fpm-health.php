<?php

// A container is ready only after PHP-FPM listens and its dependencies respond.
$socket = @stream_socket_client('tcp://127.0.0.1:9000', $errorCode, $errorMessage, 1);
if (!is_resource($socket)) exit(1);
fclose($socket);
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
exit($app->make(\App\Services\InfrastructureReadinessService::class)->ready() ? 0 : 1);
