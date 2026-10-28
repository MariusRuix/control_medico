<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private User $medico;

    private User $recepcion;

    private Patient $patient;

    private Service $service;

    /** Miércoles futuro: horario 09:00–14:00 y 16:00–20:00. */
    private CarbonImmutable $wednesday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00'));
        $this->wednesday = CarbonImmutable::parse('2026-10-28');

        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class, ServiceSeeder::class]);

        $this->medico = User::factory()->create()->assignRole(Role::Medico->value);
        $this->recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);
        $this->patient = Patient::query()->create(['folio' => 'P-000001', 'first_name' => 'Ana', 'last_name' => 'López']);
        $this->service = Service::query()->where('name', 'Consulta subsecuente')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'patient_id' => $this->patient->id,
            'service_id' => $this->service->id,
            'date' => $this->wednesday->toDateString(),
            'time' => '10:00',
            'duration_minutes' => 30,
            'reason' => 'Seguimiento',
            ...$overrides,
        ];
    }

    public function test_reception_can_book_an_appointment(): void
    {
        $this->actingAs($this->recepcion)
            ->post(route('appointments.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $appointment = Appointment::query()->firstOrFail();
        $this->assertSame('2026-10-28 10:00:00', $appointment->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-28 10:30:00', $appointment->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame(AppointmentStatus::Scheduled, $appointment->status);
        $this->assertSame($this->recepcion->id, $appointment->created_by);
    }

    public function test_overlapping_appointments_are_rejected(): void
    {
        $this->actingAs($this->recepcion)->post(route('appointments.store'), $this->payload());

        $this->post(route('appointments.store'), $this->payload(['time' => '10:15']))
            ->assertSessionHasErrors(['starts_at' => 'Se empalma con la cita de Ana López (10:00–10:30).']);

        // Una cita cancelada libera el horario.
        Appointment::query()->firstOrFail()->update(['status' => AppointmentStatus::Cancelled]);

        $this->post(route('appointments.store'), $this->payload(['time' => '10:15']))->assertSessionHasNoErrors();
    }

    public function test_working_hours_and_blocks_are_enforced(): void
    {
        $this->actingAs($this->recepcion);

        $this->post(route('appointments.store'), $this->payload(['time' => '14:30']))
            ->assertSessionHasErrors(['starts_at' => 'El horario está fuera del horario de atención.']);

        $this->post(route('appointments.store'), $this->payload(['time' => '13:45']))
            ->assertSessionHasErrors('starts_at');

        $this->post(route('appointments.store'), $this->payload(['time' => '14:30', 'allow_outside_hours' => '1']))
            ->assertSessionHasNoErrors();

        ScheduleBlock::query()->create([
            'starts_at' => $this->wednesday->setTime(16, 0),
            'ends_at' => $this->wednesday->setTime(18, 0),
            'reason' => 'Congreso',
        ]);

        $this->post(route('appointments.store'), $this->payload(['time' => '17:00']))
            ->assertSessionHasErrors(['starts_at' => 'El horario está bloqueado (Congreso).']);
    }

    public function test_past_appointments_cannot_be_booked(): void
    {
        $this->actingAs($this->recepcion)
            ->post(route('appointments.store'), $this->payload(['date' => '2026-10-23']))
            ->assertSessionHasErrors(['time' => 'No se pueden agendar citas en el pasado.']);
    }

    public function test_archived_patients_cannot_be_booked(): void
    {
        $this->patient->delete();

        $this->actingAs($this->recepcion)
            ->post(route('appointments.store'), $this->payload())
            ->assertSessionHasErrors('patient_id');
    }

    public function test_free_slots_exclude_booked_and_blocked_times(): void
    {
        $this->actingAs($this->recepcion)->post(route('appointments.store'), $this->payload());
        ScheduleBlock::query()->create([
            'starts_at' => $this->wednesday->setTime(16, 0),
            'ends_at' => $this->wednesday->setTime(20, 0),
        ]);

        $slots = $this->getJson(route('appointments.availability', [
            'date' => $this->wednesday->toDateString(),
            'duration_minutes' => 30,
        ]))->assertOk()->json('slots');

        $this->assertSame(['09:00', '09:30', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30'], $slots);
    }

    public function test_status_transitions_are_validated(): void
    {
        $this->actingAs($this->recepcion)->post(route('appointments.store'), $this->payload());
        $appointment = Appointment::query()->firstOrFail();

        $this->patch(route('appointments.status', $appointment), ['status' => 'finalizada'])
            ->assertSessionHasErrors('status');

        $this->patch(route('appointments.status', $appointment), ['status' => 'cancelada'])
            ->assertSessionHasErrors(['cancel_reason' => 'Indica el motivo de la cancelación.']);

        $this->patch(route('appointments.status', $appointment), ['status' => 'confirmada'])->assertSessionHasNoErrors();
        $this->patch(route('appointments.status', $appointment), ['status' => 'en_consultorio'])->assertSessionHasNoErrors();
        $this->patch(route('appointments.status', $appointment), ['status' => 'finalizada'])->assertSessionHasNoErrors();

        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()?->status);

        $this->put(route('appointments.update', $appointment), $this->payload(['time' => '11:00']))
            ->assertSessionHasErrors('starts_at');
    }

    public function test_rescheduling_ignores_the_appointment_itself(): void
    {
        $this->actingAs($this->recepcion)->post(route('appointments.store'), $this->payload());
        $appointment = Appointment::query()->firstOrFail();

        $this->put(route('appointments.update', $appointment), $this->payload(['time' => '10:15']))
            ->assertSessionHasNoErrors();

        $this->assertSame('10:15', $appointment->fresh()?->starts_at->format('H:i'));
    }

    public function test_calendar_returns_the_requested_week(): void
    {
        Appointment::query()->create([
            'patient_id' => $this->patient->id,
            'starts_at' => $this->wednesday->setTime(9, 0),
            'ends_at' => $this->wednesday->setTime(9, 30),
        ]);
        Appointment::query()->create([
            'patient_id' => $this->patient->id,
            'starts_at' => $this->wednesday->addWeek()->setTime(9, 0),
            'ends_at' => $this->wednesday->addWeek()->setTime(9, 30),
        ]);

        $this->actingAs($this->recepcion)
            ->get(route('appointments.index', ['view' => 'week', 'date' => '2026-10-28']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('appointments/Index')
                ->where('range.from', '2026-10-26')
                ->where('range.to', '2026-11-02')
                ->has('appointments', 1)
                ->where('appointments.0.starts_at', '2026-10-28T09:00:00')
                ->where('appointments.0.patient.full_name', 'Ana López')
                ->where('can.manage', true)
                ->where('can.manageBlocks', false));
    }

    public function test_only_the_doctor_manages_schedule_blocks(): void
    {
        $payload = ['starts_at' => '2026-12-24T00:00', 'ends_at' => '2026-12-26T00:00', 'reason' => 'Navidad'];

        $this->actingAs($this->recepcion)->post(route('schedule-blocks.store'), $payload)->assertForbidden();

        $this->actingAs($this->medico)->post(route('schedule-blocks.store'), $payload)->assertSessionHasNoErrors();
        $block = ScheduleBlock::query()->firstOrFail();

        $this->delete(route('schedule-blocks.destroy', $block))->assertRedirect();
        $this->assertModelMissing($block);
    }

    public function test_patient_search_returns_json(): void
    {
        $this->actingAs($this->recepcion)
            ->getJson(route('patients.search', ['q' => 'lóp']))
            ->assertOk()
            ->assertJsonPath('data.0.folio', 'P-000001');
    }
}
