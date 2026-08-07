<?php

namespace Tests\Feature;

use Tests\TestCase;

class AssetUrlHostTest extends TestCase
{
    public function test_login_page_uses_current_request_host_for_assets(): void
    {
        $response = $this->withServerVariables([
            'HTTP_HOST' => 'preview.example.test',
            'HTTPS' => 'on',
        ])->get('/iniciar-sesion');

        $response->assertOk();
        $response->assertSee('/build/assets/', false);
        $response->assertDontSee('http://127.0.0.1:8000/build/', false);
    }
}
