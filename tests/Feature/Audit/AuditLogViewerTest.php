<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
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

    private int $folio = 0;

    private function patient(string $first): Patient
    {
        return Patient::query()->create([
            'folio' => sprintf('P-%06d', ++$this->folio),
            'first_name' => $first,
            'last_name' => 'Prueba',
        ]);
    }

    public function test_reception_cannot_see_the_audit_log(): void
    {
        $this->actingAs($this->recepcion)->get(route('audit-logs.index'))->assertForbidden();
        $this->actingAs($this->recepcion)->get(route('audit-logs.export'))->assertForbidden();
    }

    public function test_records_are_linked_to_their_patient_and_can_be_filtered(): void
    {
        $this->actingAs($this->recepcion);
        $ana = $this->patient('Ana');
        $luis = $this->patient('Luis');

        $this->actingAs($this->medico)->get(route('patients.show', $ana))->assertOk();

        $this->assertSame(2, AuditLog::query()->where('patient_id', $ana->id)->count()); // alta + consulta del expediente
        $this->assertSame(1, AuditLog::query()->where('patient_id', $luis->id)->count());

        $this->get(route('audit-logs.index', ['patient' => $ana->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('audit/Index')
                ->has('logs.data', 2)
                ->where('logs.data.0.action', 'viewed')
                ->where('logs.data.0.user.name', 'Dra. Laura')
                ->where('logs.data.0.patient.id', $ana->id)
                ->where('logs.data.1.action', 'created')
                ->where('logs.data.1.user.name', 'Sofía')
                ->where('patient.id', $ana->id));

        $this->get(route('audit-logs.index', ['user' => $this->recepcion->id, 'type' => 'patient', 'action' => 'created']))
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 2));
    }

    public function test_date_filter_and_validation(): void
    {
        $this->actingAs($this->medico);
        Carbon::setTestNow('2026-10-01 10:00');
        $this->patient('Antes');
        Carbon::setTestNow('2026-10-20 10:00');
        $this->patient('Despues');
        Carbon::setTestNow();

        $this->get(route('audit-logs.index', ['from' => '2026-10-15', 'to' => '2026-10-20', 'type' => 'patient']))
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1));

        $this->get(route('audit-logs.index', ['from' => '2026-10-20', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('to');
        $this->get(route('audit-logs.index', ['action' => 'hack']))
            ->assertSessionHasErrors('action');
    }

    public function test_changes_hide_encrypted_values(): void
    {
        $this->actingAs($this->medico);
        $ana = $this->patient('Ana');
        $ana->update(['phone' => '5511112222', 'curp' => 'GOMA800101MDFRRN09']);

        $this->get(route('audit-logs.index', ['patient' => $ana->id, 'action' => 'updated']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.changes', fn ($changes): bool => collect($changes)->contains(fn ($c): bool => $c['field'] === 'phone' && $c['new'] === '5511112222')
                    && collect($changes)->contains(fn ($c): bool => $c['field'] === 'curp' && $c['new'] === '[protegido]')));
    }

    public function test_logins_logouts_and_failed_attempts_are_recorded(): void
    {
        $this->post(route('login.store'), ['email' => $this->recepcion->email, 'password' => 'mala']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_failed', 'user_id' => null]);

        $this->post(route('login.store'), ['email' => $this->recepcion->email, 'password' => 'password']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login', 'user_id' => $this->recepcion->id]);

        $this->post(route('logout'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'logout', 'user_id' => $this->recepcion->id]);
    }

    public function test_export_is_a_csv_and_is_itself_recorded(): void
    {
        $this->actingAs($this->medico);
        $this->patient('Ana');

        $response = $this->get(route('audit-logs.export', ['type' => 'patient']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Fecha y hora",Usuario,Acción', $csv);
        $this->assertStringContainsString('"Dra. Laura",Alta,Paciente', $csv);

        $this->assertDatabaseHas('audit_logs', ['action' => 'exported', 'user_id' => $this->medico->id]);
    }

    public function test_audit_records_cannot_be_modified(): void
    {
        $this->actingAs($this->medico);
        $this->patient('Ana');
        $log = AuditLog::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $log->update(['action' => 'deleted']);
    }
}
