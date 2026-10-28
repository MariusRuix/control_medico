<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PatientManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $medico;

    private User $recepcion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);

        $this->medico = User::factory()->create()->assignRole(Role::Medico->value);
        $this->recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'first_name' => 'Ana',
            'last_name' => 'López',
            'second_last_name' => 'Pérez',
            'birth_date' => '1990-01-01',
            'sex' => 'F',
            'curp' => 'lopa900101mdfpnn09',
            'phone' => '55 1234 5678',
            'blood_type' => 'O+',
            ...$overrides,
        ];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('patients.index'))->assertRedirect(route('login'));
    }

    public function test_reception_can_register_a_patient_with_automatic_folio(): void
    {
        $response = $this->actingAs($this->recepcion)->post(route('patients.store'), $this->validPayload());

        $patient = Patient::query()->firstOrFail();

        $response->assertRedirect(route('patients.show', $patient));
        $this->assertSame('P-000001', $patient->folio);
        $this->assertSame('LOPA900101MDFPNN09', $patient->curp);
    }

    public function test_curp_must_be_valid_and_unique(): void
    {
        $this->actingAs($this->recepcion);

        $this->post(route('patients.store'), $this->validPayload(['curp' => 'XXXX']))
            ->assertSessionHasErrors('curp');

        $this->post(route('patients.store'), $this->validPayload())->assertSessionHasNoErrors();

        $this->post(route('patients.store'), $this->validPayload(['first_name' => 'Otra']))
            ->assertSessionHasErrors(['curp' => 'Ya existe un paciente con esta CURP (folio P-000001).']);
    }

    public function test_updating_a_patient_keeps_its_own_curp_valid(): void
    {
        $this->actingAs($this->recepcion)->post(route('patients.store'), $this->validPayload());
        $patient = Patient::query()->firstOrFail();

        $this->put(route('patients.update', $patient), $this->validPayload(['phone' => '5599990000']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('patients.show', $patient));

        $this->assertSame('5599990000', $patient->fresh()?->phone);
    }

    public function test_directory_searches_by_name_folio_and_full_curp(): void
    {
        $this->actingAs($this->recepcion)->post(route('patients.store'), $this->validPayload());
        $this->post(route('patients.store'), $this->validPayload([
            'first_name' => 'Luis', 'last_name' => 'Martínez', 'curp' => null, 'phone' => '5511112222',
        ]));

        foreach (['López Pérez' => 'Ana López Pérez', 'P-000002' => 'Luis Martínez Pérez', 'LOPA900101MDFPNN09' => 'Ana López Pérez'] as $term => $expected) {
            $this->get(route('patients.index', ['q' => $term]))
                ->assertInertia(fn (Assert $page) => $page
                    ->component('patients/Index')
                    ->has('patients.data', 1)
                    ->where('patients.data.0.full_name', $expected));
        }
    }

    public function test_reception_does_not_receive_clinical_data_but_sees_critical_allergies(): void
    {
        $patient = Patient::query()->create(['folio' => 'P-000010', 'first_name' => 'Eva', 'last_name' => 'Ruiz']);
        $patient->histories()->create(['type' => 'alergia', 'description' => 'Penicilina', 'is_critical' => true]);
        $patient->histories()->create(['type' => 'patologico', 'description' => 'Hipertensión']);

        $this->actingAs($this->recepcion)
            ->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->component('patients/Show')
                ->where('histories', null)
                ->where('consultations', null)
                ->where('criticalAllergies', ['Penicilina'])
                ->where('can.manageHistories', false));

        $this->actingAs($this->medico)
            ->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->has('histories', 2)
                ->where('can.manageHistories', true));
    }

    public function test_viewing_a_record_is_audited(): void
    {
        $patient = Patient::query()->create(['folio' => 'P-000011', 'first_name' => 'Eva', 'last_name' => 'Ruiz']);

        $this->actingAs($this->medico)->get(route('patients.show', $patient))->assertOk();

        $this->assertTrue(AuditLog::query()
            ->where('action', 'viewed')
            ->where('auditable_id', $patient->id)
            ->where('user_id', $this->medico->id)
            ->exists());
    }

    public function test_only_the_doctor_manages_histories(): void
    {
        $patient = Patient::query()->create(['folio' => 'P-000012', 'first_name' => 'Eva', 'last_name' => 'Ruiz']);
        $payload = ['type' => 'alergia', 'description' => 'Sulfas', 'is_critical' => '1'];

        $this->actingAs($this->recepcion)
            ->post(route('patients.histories.store', $patient), $payload)
            ->assertForbidden();

        $this->actingAs($this->medico)
            ->post(route('patients.histories.store', $patient), $payload)
            ->assertRedirect(route('patients.show', $patient));

        $history = $patient->histories()->firstOrFail();
        $this->assertTrue($history->is_critical);
        $this->assertSame($this->medico->id, $history->created_by);

        $this->put(route('patients.histories.update', [$patient, $history]), [...$payload, 'is_critical' => '0'])
            ->assertRedirect();
        $this->assertFalse($history->fresh()?->is_critical);

        $this->delete(route('patients.histories.destroy', [$patient, $history]))->assertRedirect();
        $this->assertSoftDeleted($history);
    }

    public function test_histories_are_scoped_to_their_patient(): void
    {
        $a = Patient::query()->create(['folio' => 'P-000013', 'first_name' => 'A', 'last_name' => 'Uno']);
        $b = Patient::query()->create(['folio' => 'P-000014', 'first_name' => 'B', 'last_name' => 'Dos']);
        $history = $a->histories()->create(['type' => 'alergia', 'description' => 'Látex']);

        $this->actingAs($this->medico)
            ->delete(route('patients.histories.destroy', [$b, $history]))
            ->assertNotFound();
    }

    public function test_only_the_doctor_can_archive_and_future_appointments_are_cancelled(): void
    {
        $patient = Patient::query()->create(['folio' => 'P-000015', 'first_name' => 'Eva', 'last_name' => 'Ruiz']);
        $appointment = Appointment::query()->create([
            'patient_id' => $patient->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
        ]);

        $this->actingAs($this->recepcion)->delete(route('patients.destroy', $patient))->assertForbidden();

        $this->actingAs($this->medico)
            ->delete(route('patients.destroy', $patient))
            ->assertRedirect(route('patients.index'));

        $this->assertSoftDeleted($patient);
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()?->status);
    }
}
