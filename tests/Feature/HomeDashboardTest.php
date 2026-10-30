<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-28 10:00:00'));
        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class, ServiceSeeder::class]);
        $this->patient = Patient::query()->create(['folio' => 'P-000001', 'first_name' => 'Ana', 'last_name' => 'López']);

        $make = fn (string $time, AppointmentStatus $status, int $days = 0) => Appointment::query()->create([
            'patient_id' => $this->patient->id,
            'starts_at' => now()->startOfDay()->addDays($days)->setTimeFromTimeString($time),
            'ends_at' => now()->startOfDay()->addDays($days)->setTimeFromTimeString($time)->addMinutes(30),
            'status' => $status,
        ]);

        $make('09:00', AppointmentStatus::Completed);
        $make('10:30', AppointmentStatus::Confirmed);
        $make('11:00', AppointmentStatus::Scheduled);
        $make('12:00', AppointmentStatus::Cancelled);
        $make('09:00', AppointmentStatus::Scheduled, 1);

        Consultation::query()->create(['patient_id' => $this->patient->id, 'consulted_at' => now()]);
    }

    public function test_doctor_sees_appointments_stats_drafts_and_finance(): void
    {
        $medico = User::factory()->create()->assignRole(Role::Medico->value);

        $this->actingAs($medico)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('appointments', 4)
                ->where('stats.total', 3)
                ->where('stats.pending', 2)
                ->where('stats.completed', 1)
                ->where('stats.tomorrow', 1)
                ->has('drafts', 1)
                ->where('finance.cash_open', false)
                ->where('needsSetup', true)
                ->where('can.consult', true));
    }

    public function test_reception_does_not_receive_clinical_drafts(): void
    {
        $recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);

        $this->actingAs($recepcion)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('appointments', 4)
                ->where('drafts', null)
                ->where('needsSetup', false)
                ->where('can.consult', false)
                ->has('billing.services'));
    }

    public function test_users_without_role_get_an_empty_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('appointments', null)
                ->where('drafts', null)
                ->where('finance', null));
    }
}
