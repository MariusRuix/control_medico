<?php

declare(strict_types=1);

namespace Tests\Feature\Clinical;

use App\Enums\Role;
use App\Mail\PrescriptionMail;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\MedicationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $medico;

    private User $recepcion;

    private Patient $patient;

    private Consultation $consultation;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class, MedicationSeeder::class]);

        $this->medico = User::factory()->create()->assignRole(Role::Medico->value);
        $this->recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);
        $this->patient = Patient::query()->create([
            'folio' => 'P-000001', 'first_name' => 'Ana', 'last_name' => 'López', 'email' => 'ana@example.com',
        ]);
        $this->consultation = Consultation::query()->create([
            'patient_id' => $this->patient->id, 'consulted_at' => now(), 'weight_kg' => 68,
        ]);
    }

    private function configurePrescriber(): void
    {
        app(SettingsService::class)->setMany([
            'doctor.name' => 'Dra. Laura Méndez',
            'doctor.professional_license' => '12345678',
            'doctor.university' => 'UNAM',
            'clinic.address' => 'Av. Reforma 100, CDMX',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $paracetamol = Medication::query()->where('generic_name', 'Paracetamol')->firstOrFail();

        return [
            'general_instructions' => 'Abundantes líquidos.',
            'items' => [
                [
                    'medication_id' => $paracetamol->id,
                    'medication_name' => 'Paracetamol 500 mg Tabletas',
                    'dose' => '1 tableta',
                    'route' => 'Oral',
                    'frequency' => 'Cada 8 horas',
                    'duration' => '3 días',
                    'quantity' => '1 caja',
                ],
                [
                    'medication_id' => null,
                    'medication_name' => 'Benzonatato 100 mg cápsulas',
                    'dose' => '1 cápsula',
                    'frequency' => 'Cada 8 horas',
                    'save_to_vademecum' => true,
                ],
            ],
        ];
    }

    public function test_prescriber_data_is_required_before_issuing(): void
    {
        $this->actingAs($this->medico)
            ->post(route('prescriptions.store', $this->consultation), $this->payload())
            ->assertSessionHasErrors('prescriber');

        $this->assertSame(0, Prescription::query()->count());
    }

    public function test_issuing_assigns_folio_items_fingerprint_and_pdf(): void
    {
        $this->configurePrescriber();

        $this->actingAs($this->medico)
            ->post(route('prescriptions.store', $this->consultation), $this->payload())
            ->assertSessionHasNoErrors();

        $prescription = Prescription::query()->with('items')->firstOrFail();

        $this->assertSame('R-000001', $prescription->folio);
        $this->assertCount(2, $prescription->items);
        $this->assertSame(64, strlen((string) $prescription->signature_hash));
        $this->assertNotNull($prescription->pdf_path);
        Storage::disk('local')->assertExists((string) $prescription->pdf_path);
        $this->assertStringStartsWith('%PDF', (string) Storage::disk('local')->get((string) $prescription->pdf_path));

        // El medicamento libre se guardó en el vademécum.
        $this->assertTrue(Medication::query()->where('generic_name', 'Benzonatato 100 mg cápsulas')->where('is_favorite', true)->exists());
    }

    public function test_items_require_dose_and_frequency(): void
    {
        $this->configurePrescriber();

        $this->actingAs($this->medico)
            ->post(route('prescriptions.store', $this->consultation), [
                'items' => [['medication_name' => 'Ibuprofeno', 'dose' => '', 'frequency' => '']],
            ])
            ->assertSessionHasErrors(['items.0.dose', 'items.0.frequency']);

        $this->post(route('prescriptions.store', $this->consultation), ['items' => []])
            ->assertSessionHasErrors(['items' => 'Agrega al menos un medicamento.']);
    }

    public function test_pdf_is_served_only_to_the_doctor_and_regenerated_if_missing(): void
    {
        $this->configurePrescriber();
        $this->actingAs($this->medico)->post(route('prescriptions.store', $this->consultation), $this->payload());
        $prescription = Prescription::query()->firstOrFail();

        Storage::disk('local')->delete((string) $prescription->pdf_path);

        $this->get(route('prescriptions.pdf', $prescription))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->recepcion)->get(route('prescriptions.pdf', $prescription))->assertForbidden();
        $this->actingAs($this->recepcion)->post(route('prescriptions.store', $this->consultation), $this->payload())->assertForbidden();
    }

    public function test_prescription_can_be_emailed_to_the_patient(): void
    {
        Mail::fake();
        $this->configurePrescriber();
        $this->actingAs($this->medico)->post(route('prescriptions.store', $this->consultation), $this->payload());
        $prescription = Prescription::query()->firstOrFail();

        $this->post(route('prescriptions.email', $prescription))->assertSessionHasNoErrors();

        Mail::assertSent(PrescriptionMail::class, fn (PrescriptionMail $mail): bool => $mail->hasTo('ana@example.com')
            && count($mail->attachments()) === 1);
    }

    public function test_voiding_requires_a_reason_and_is_audited(): void
    {
        $this->configurePrescriber();
        $this->actingAs($this->medico)->post(route('prescriptions.store', $this->consultation), $this->payload());
        $prescription = Prescription::query()->firstOrFail();

        $this->delete(route('prescriptions.destroy', $prescription))->assertSessionHasErrors('reason');

        $this->delete(route('prescriptions.destroy', $prescription), ['reason' => 'Error en la dosis'])->assertSessionHasNoErrors();

        $this->assertSoftDeleted($prescription);
        $this->assertSame(
            'Error en la dosis',
            AuditLog::query()->where('action', 'voided')->firstOrFail()->new_values['reason'] ?? null,
        );
    }

    public function test_consultation_page_lists_prescriptions_and_favorites(): void
    {
        $this->configurePrescriber();
        $this->actingAs($this->medico)->post(route('prescriptions.store', $this->consultation), $this->payload());

        $this->get(route('consultations.edit', $this->consultation))
            ->assertInertia(fn (Assert $page) => $page
                ->component('consultations/Edit')
                ->has('prescriptions', 1)
                ->where('prescriptions.0.folio', 'R-000001')
                ->where('prescriberMissing', [])
                ->where('patient.has_email', true)
                ->has('favorites'));
    }

    public function test_clinic_settings_can_be_updated_with_signature(): void
    {
        $this->actingAs($this->medico)
            ->post(route('settings.clinic.update'), [
                'clinic' => ['name' => 'Consultorio Méndez', 'address' => 'Av. Reforma 100'],
                'doctor' => ['name' => 'Dra. Laura Méndez', 'professional_license' => '1234567', 'university' => 'UNAM'],
                'signature' => UploadedFile::fake()->image('firma.png', 400, 150),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.clinic.edit'));

        $settings = app(SettingsService::class);
        $this->assertSame('1234567', $settings->get('doctor.professional_license'));
        Storage::disk('local')->assertExists((string) $settings->get('doctor.signature_path'));

        $this->get(route('settings.clinic.image', 'signature'))->assertOk();

        $this->post(route('settings.clinic.update'), [
            'clinic' => ['name' => 'Consultorio Méndez'],
            'doctor' => ['name' => 'Dra. Laura Méndez', 'professional_license' => '12'],
        ])->assertSessionHasErrors(['doctor.professional_license' => 'La cédula profesional debe tener 7 u 8 dígitos.']);

        $this->actingAs($this->recepcion)->get(route('settings.clinic.edit'))->assertForbidden();
    }
}
