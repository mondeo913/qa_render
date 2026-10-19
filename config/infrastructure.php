<?php

return [
    'require_repository' => (bool) env('SIGET_REQUIRE_PRIVATE_REPOSITORY', false),
    'repository_id' => env('SIGET_REPOSITORY_ID'),
];
