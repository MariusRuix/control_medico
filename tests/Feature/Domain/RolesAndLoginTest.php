<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_reception_cannot_view_consultations(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $medico = User::factory()->create()->assignRole(Role::Medico->value);
        $recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);

        $this->assertTrue($medico->can(Permission::ConsultationsView->value));
        $this->assertFalse($recepcion->can(Permission::ConsultationsView->value));
        $this->assertTrue($recepcion->can(Permission::AppointmentsManage->value));
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }
}
