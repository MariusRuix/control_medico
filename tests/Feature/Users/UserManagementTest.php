<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $medico;

    private User $recepcion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);

        $this->medico = User::factory()->create(['name' => 'Dra. Laura'])->assignRole(Role::Medico->value);
        $this->recepcion = User::factory()->create(['name' => 'Sofía'])->assignRole(Role::Recepcion->value);
    }

    private function asMedico(): static
    {
        return $this->actingAs($this->medico)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_reception_cannot_manage_users(): void
    {
        $this->actingAs($this->recepcion)->withSession(['auth.password_confirmed_at' => time()]);

        $this->get(route('settings.users.index'))->assertForbidden();
        $this->post(route('settings.users.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'medico'])->assertForbidden();
    }

    public function test_user_screen_requires_password_confirmation(): void
    {
        $this->actingAs($this->medico)->get(route('settings.users.index'))->assertRedirect(route('password.confirm'));
    }

    public function test_doctor_sees_the_staff_list(): void
    {
        $this->asMedico()->get(route('settings.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Users')
                ->has('users', 2)
                ->where('users.0.name', 'Dra. Laura')
                ->where('users.0.is_self', true)
                ->where('users.1.role', 'recepcion')
                ->has('roles', 2));
    }

    public function test_doctor_creates_a_user_with_a_temporary_password(): void
    {
        $response = $this->asMedico()->post(route('settings.users.store'), [
            'name' => '  Mariana   López ',
            'email' => 'Mariana@Consultorio.test',
            'role' => 'recepcion',
            'password' => '',
        ]);

        $response->assertRedirect(route('settings.users.index'));

        $user = User::query()->where('email', 'mariana@consultorio.test')->firstOrFail();
        $this->assertSame('Mariana López', $user->name);
        $this->assertTrue($user->hasRole('recepcion'));
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);

        $flash = $response->getSession()->get('inertia.flash_data', []);
        $this->assertIsArray($flash);
        $this->assertArrayHasKey('credentials', $flash);
        $this->assertTrue(Hash::check($flash['credentials']['password'], $user->password));
        $this->assertSame(14, strlen($flash['credentials']['password']));

        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'auditable_id' => $user->id, 'user_id' => $this->medico->id]);
        $this->assertStringNotContainsString($flash['credentials']['password'], (string) json_encode(AuditLog::query()->get()->toArray()));
    }

    public function test_doctor_can_set_the_initial_password_and_email_must_be_unique(): void
    {
        $this->asMedico()->post(route('settings.users.store'), [
            'name' => 'Otra', 'email' => strtoupper($this->recepcion->email), 'role' => 'recepcion',
        ])->assertSessionHasErrors('email');

        $response = $this->asMedico()->post(route('settings.users.store'), [
            'name' => 'Suplente', 'email' => 'suplente@example.com', 'role' => 'medico', 'password' => 'clave-segura-123',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('credentials', (array) $response->getSession()->get('inertia.flash_data', []));
        $user = User::query()->where('email', 'suplente@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('clave-segura-123', $user->password));
        $this->assertTrue($user->hasRole('medico'));
    }

    public function test_doctor_updates_name_email_and_role(): void
    {
        $this->asMedico()->put(route('settings.users.update', $this->recepcion), [
            'name' => 'Sofía Ramírez', 'email' => 'sofia@example.com', 'role' => 'medico',
        ])->assertSessionHasNoErrors();

        $this->recepcion->refresh();
        $this->assertSame('Sofía Ramírez', $this->recepcion->name);
        $this->assertTrue($this->recepcion->hasRole('medico'));
        $this->assertFalse($this->recepcion->hasRole('recepcion'));

        $log = AuditLog::query()->where('action', 'updated')->where('auditable_id', $this->recepcion->id)->firstOrFail();
        $this->assertSame('recepcion', $log->old_values['role']);
        $this->assertSame('medico', $log->new_values['role']);
    }

    public function test_doctor_cannot_change_own_role_or_leave_the_system_without_an_active_doctor(): void
    {
        $this->asMedico()->put(route('settings.users.update', $this->medico), [
            'name' => 'Dra. Laura', 'email' => $this->medico->email, 'role' => 'recepcion',
        ])->assertSessionHasErrors('role');

        $this->asMedico()->patch(route('settings.users.status', $this->medico), ['active' => false])
            ->assertSessionHasErrors('active');

        // Un segundo médico (suplente) sí se puede desactivar porque queda uno activo.
        $suplente = User::factory()->create()->assignRole(Role::Medico->value);
        $this->asMedico()->patch(route('settings.users.status', $suplente), ['active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($suplente->refresh()->is_active);

        // Cambiar el rol de un médico inactivo no deja al sistema sin médico activo.
        $this->asMedico()->put(route('settings.users.update', $suplente), [
            'name' => $suplente->name, 'email' => $suplente->email, 'role' => 'recepcion',
        ])->assertSessionHasNoErrors();
    }

    public function test_deactivating_ends_sessions_and_blocks_login(): void
    {
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'abc', 'user_id' => $this->recepcion->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => '', 'last_activity' => time(),
        ]);

        $this->asMedico()->patch(route('settings.users.status', $this->recepcion), ['active' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->recepcion->refresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['user_id' => $this->recepcion->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deactivated', 'auditable_id' => $this->recepcion->id]);

        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $this->recepcion->email, 'password' => 'password']);
        $this->assertGuest();

        $this->asMedico()->patch(route('settings.users.status', $this->recepcion), ['active' => true]);
        $this->assertTrue($this->recepcion->refresh()->is_active);
    }

    public function test_an_open_session_of_a_deactivated_user_is_closed(): void
    {
        $this->recepcion->forceFill(['is_active' => false])->save();

        $this->actingAs($this->recepcion)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();
    }

    public function test_doctor_resets_a_password_and_second_factor(): void
    {
        $this->recepcion->forceFill([
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt('[]'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->asMedico()->put(route('settings.users.password', $this->recepcion), [
            'password' => '',
            'reset_second_factor' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $flash = (array) $response->getSession()->get('inertia.flash_data', []);
        $this->recepcion->refresh();

        $this->assertTrue(Hash::check($flash['credentials']['password'], $this->recepcion->password));
        $this->assertNull($this->recepcion->two_factor_secret);
        $this->assertNull($this->recepcion->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_reset', 'auditable_id' => $this->recepcion->id]);

        $this->asMedico()->put(route('settings.users.password', $this->medico), ['password' => 'otra-clave-123'])
            ->assertSessionHasErrors('password');
    }
}
