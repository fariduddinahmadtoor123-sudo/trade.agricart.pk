<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SettingsNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Filament only allows non-FilamentUser models in the local environment.
        config(['app.env' => 'local']);
    }

    public function test_guests_are_redirected_from_settings_roles(): void
    {
        $this->get('/admin/settings/roles')->assertRedirect('/admin/login');
    }

    public function test_user_without_role_permission_cannot_open_settings_roles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/settings/roles')
            ->assertForbidden();
    }

    public function test_authorized_user_sees_roles_under_settings_submenu(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:Role'));

        $this->actingAs($user)
            ->get('/admin/settings/roles')
            ->assertOk()
            ->assertSee('Settings', false)
            ->assertSee('Roles', false)
            ->assertDontSee('Filament Shield', false);
    }
}
