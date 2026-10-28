<?php

declare(strict_types=1);

namespace Tests\Feature\Clinical;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Cie10Code;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Cie10CodeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConsultationTest extends TestCase
{
    use RefreshDatabase;

    private User $medico;

    private User $recepcion;

    private Patient $patient;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-28 10:00:00'));
        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class, Cie10CodeSeeder::class]);

        $this->medico = User::factory()->create()->assignRole(Role::Medico->value);
        $this->recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);
        $this->patient = Patient::query()->create(['folio' => 'P-000001', 'first_name' => 'Ana', 'last_name' => 'López']);
        $this->appointment = Appointment::query()->create([
            'patient_id' => $this->patient->id,
            'starts_at' => now(),
            'ends_at' => now()->addMinutes(30),
            'status' => AppointmentStatus::Confirmed,
            'reason' => 'Dolor de garganta',
        ]);
    }

    private function code(string $code): int
    {
        return (int) Cie10Code::query()->where('code', $code)->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function note(array $overrides = []): array
    {
        return [
            'weight_kg' => 68,
            'height_cm' => 162,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'temperature_c' => 38.2,
            'reason' => 'Dolor de garganta',
            'subjective' => 'Odinofagia de 2 días.',
            'objective' => 'Faringe hiperémica.',
            'assessment' => 'Faringitis aguda.',
            'plan' => 'Paracetamol 500 mg c/8 h por 3 días.',
            'diagnoses' => [
                ['cie10_code_id' => $this->code('J02.9'), 'type' => 'principal', 'notes' => null],
                ['cie10_code_id' => $this->code('R50.9'), 'type' => 'secundario', 'notes' => null],
            ],
            ...$overrides,
        ];
    }

    private function startConsultation(): Consultation
    {
        $this->actingAs($this->medico)
            ->post(route('consultations.start-from-appointment', $this->appointment))
            ->assertRedirect();

        return Consultation::query()->latest('id')->firstOrFail();
    }

    public function test_starting_from_an_appointment_moves_it_to_the_office(): void
    {
        $consultation = $this->startConsultation();

        $this->assertSame($this->appointment->id, $consultation->appointment_id);
        $this->assertSame('Dolor de garganta', $consultation->reason);
        $this->assertSame(AppointmentStatus::InProgress, $this->appointment->fresh()?->status);

        // Volver a iniciar abre la misma consulta.
        $this->post(route('consultations.start-from-appointment', $this->appointment))
            ->assertRedirect(route('consultations.edit', $consultation));
        $this->assertSame(1, Consultation::query()->count());
    }

    public function test_reception_cannot_open_or_start_consultations(): void
    {
        $consultation = $this->startConsultation();

        $this->actingAs($this->recepcion)
            ->post(route('consultations.start-from-appointment', $this->appointment))
            ->assertForbidden();
        $this->get(route('consultations.edit', $consultation))->assertForbidden();
        $this->get(route('consultations.show', $consultation))->assertForbidden();
    }

    public function test_draft_is_saved_encrypted_with_diagnoses_and_bmi(): void
    {
        $consultation = $this->startConsultation();

        $this->put(route('consultations.update', $consultation), $this->note())->assertSessionHasNoErrors();

        $consultation->refresh();
        $this->assertSame('25.91', $consultation->bmi);
        $this->assertSame('Odinofagia de 2 días.', $consultation->subjective);
        $this->assertCount(2, $consultation->diagnoses);
        $this->assertNotSame('Odinofagia de 2 días.', DB::table('consultations')->value('subjective'));
        $this->assertNull($consultation->signed_at);
    }

    public function test_vital_signs_outside_physiological_ranges_are_rejected(): void
    {
        $consultation = $this->startConsultation();

        $this->put(route('consultations.update', $consultation), $this->note([
            'height_cm' => 1620,
            'systolic_bp' => 80,
            'diastolic_bp' => 90,
        ]))->assertSessionHasErrors(['height_cm', 'diastolic_bp']);
    }

    public function test_only_one_principal_diagnosis_is_allowed(): void
    {
        $consultation = $this->startConsultation();

        $this->put(route('consultations.update', $consultation), $this->note([
            'diagnoses' => [
                ['cie10_code_id' => $this->code('J02.9'), 'type' => 'principal'],
                ['cie10_code_id' => $this->code('R50.9'), 'type' => 'principal'],
            ],
        ]))->assertSessionHasErrors(['diagnoses' => 'Solo puede haber un diagnóstico principal.']);
    }

    public function test_signing_requires_evolution_plan_and_principal_diagnosis(): void
    {
        $consultation = $this->startConsultation();

        $this->post(route('consultations.sign', $consultation), $this->note([
            'subjective' => '',
            'plan' => '',
            'diagnoses' => [],
        ]))->assertSessionHasErrors(['subjective', 'plan', 'diagnoses']);

        $this->assertNull($consultation->fresh()?->signed_at);
    }

    public function test_unfilled_template_text_cannot_be_signed(): void
    {
        $this->seed(\Database\Seeders\NoteTemplateSeeder::class);
        $template = \App\Models\NoteTemplate::query()->firstOrFail();
        $consultation = $this->startConsultation();

        $this->post(route('consultations.sign', $consultation), $this->note([
            'note_template_id' => $template->id,
            'plan' => $template->structure['plan'],
        ]))->assertSessionHasErrors(['plan' => 'Completa este apartado: aún tiene el texto de la plantilla.']);
    }

    public function test_signed_notes_are_locked_and_close_the_appointment(): void
    {
        $consultation = $this->startConsultation();

        $this->post(route('consultations.sign', $consultation), $this->note())
            ->assertRedirect(route('consultations.show', $consultation));

        $this->assertNotNull($consultation->fresh()?->signed_at);
        $this->assertSame(AppointmentStatus::Completed, $this->appointment->fresh()?->status);

        $this->put(route('consultations.update', $consultation), $this->note(['plan' => 'Otro']))
            ->assertSessionHasErrors('consultation');
        $this->delete(route('consultations.destroy', $consultation))->assertSessionHasErrors('consultation');
        $this->get(route('consultations.edit', $consultation))->assertRedirect(route('consultations.show', $consultation));

        $this->get(route('consultations.show', $consultation))
            ->assertInertia(fn (Assert $page) => $page
                ->component('consultations/Show')
                ->where('consultation.plan', 'Paracetamol 500 mg c/8 h por 3 días.')
                ->has('consultation.diagnoses', 2));
    }

    public function test_discarding_a_draft_returns_the_appointment_and_allows_restarting(): void
    {
        $consultation = $this->startConsultation();

        $this->delete(route('consultations.destroy', $consultation))
            ->assertRedirect(route('patients.show', $this->patient));

        $this->assertSoftDeleted($consultation);
        $this->assertSame(AppointmentStatus::Confirmed, $this->appointment->fresh()?->status);

        $this->post(route('consultations.start-from-appointment', $this->appointment))->assertRedirect();
        $this->assertSame(1, Consultation::query()->count());
    }

    public function test_walk_in_consultation_reuses_open_draft(): void
    {
        $this->actingAs($this->medico)->post(route('consultations.start', $this->patient))->assertRedirect();
        $this->post(route('consultations.start', $this->patient))->assertRedirect();

        $this->assertSame(1, Consultation::query()->whereNull('appointment_id')->count());
    }

    public function test_workspace_includes_previous_notes_and_vital_trend(): void
    {
        $previous = Consultation::query()->create([
            'patient_id' => $this->patient->id,
            'consulted_at' => now()->subMonth(),
            'weight_kg' => 70,
            'height_cm' => 162,
            'plan' => 'Control en un mes',
            'signed_at' => now()->subMonth(),
        ]);

        $consultation = $this->startConsultation();

        $this->get(route('consultations.edit', $consultation))
            ->assertInertia(fn (Assert $page) => $page
                ->component('consultations/Edit')
                ->has('previous', 1)
                ->where('previous.0.id', $previous->id)
                ->has('trend', 1)
                ->where('trend.0.weight_kg', '70.00')
                ->has('templates', 0));
    }

    public function test_cie10_search_prioritizes_exact_codes(): void
    {
        $this->actingAs($this->medico)
            ->getJson(route('catalogs.cie10', ['q' => 'j02']))
            ->assertOk()
            ->assertJsonPath('data.0.code', 'J02.9');

        $this->actingAs($this->medico)
            ->getJson(route('catalogs.cie10', ['q' => 'faring']))
            ->assertOk()
            ->assertJsonPath('data.0.code', 'J02.9');
    }
}
