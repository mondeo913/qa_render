<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginVisualIntegrationTest extends TestCase
{
    public function test_new_login_renders_identity_and_preserves_laravel_auth_routes(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk()
            ->assertSee('Cada evidencia.', false)
            ->assertSee('Bajo control.', false)
            ->assertSee('La evidencia en el centro de cada decisión.', false)
            ->assertSee('Trazabilidad operativa', false)
            ->assertDontSee('Seguridad por roles', false)
            ->assertDontSee('Ciclo operativo integrado', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee(route('password.request'), false)
            ->assertSee('siget-login.css', false)
            ->assertSee('siget-login.js', false);
    }
}
