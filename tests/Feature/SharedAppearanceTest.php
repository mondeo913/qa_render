<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedAppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_uses_the_same_complete_appearance_controls_and_single_asset_layer(): void
    {
        $this->seed(RolePermissionSeeder::class);
        foreach (Role::all() as $role) {
            $user = User::factory()->create(['role_id' => $role->id, 'status' => 'ACTIVE']);
            $response = $this->actingAs($user)->get(route('dashboard'));
            $response->assertOk()
                ->assertSee('data-siget-user="'.$user->id.'"', false)
                ->assertSee('data-zenith-layout="topnav"', false)
                ->assertSee('data-theme-value="dark"', false)
                ->assertSee('data-zenith-density="comfortable"', false)
                ->assertSee('data-zenith-container="boxed"', false)
                ->assertSee('data-zenith-radius="soft"', false);
            $this->assertSame(1, substr_count($response->getContent(), 'siget-zenith-runtime.js'), $role->code);
            $this->assertStringNotContainsString('siget-zenith-runtime.css', $response->getContent(), $role->code);
            $this->assertStringNotContainsString('report-executive-fallback.js', $response->getContent(), $role->code);
        }
    }

    public function test_guest_login_and_recovery_share_the_asset_layer(): void
    {
        foreach ([route('login'), route('password.request')] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('data-siget-user="guest"', false);
            $this->assertSame(1, substr_count($response->getContent(), 'siget-zenith-runtime.js'));
            $this->assertStringNotContainsString('siget-zenith-runtime.css', $response->getContent());
        }
    }
}
