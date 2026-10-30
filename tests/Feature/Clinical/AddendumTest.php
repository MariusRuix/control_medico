<?php

declare(strict_types=1);

namespace Tests\Feature\Clinical;

use App\Enums\Role;
use App\Models\Consultation;
use App\Models\ConsultationAddendum;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class AddendumTest extends TestCase
{
    use RefreshDatabase;

    private User $medico;

    private Consultation $signed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
        $this->medico = User::factory()->create(['name' => 'Dra. Méndez'])->assignRole(Role::Medico->value);

        $patient = Patient::query()->create(['folio' => 'P-000001', 'first_name' => 'Ana', 'last_name' => 'López']);
        $this->signed = Consultation::query()->create([
            'patient_id' => $patient->id,
            'consulted_at' => now()->subDay(),
            'subjective' => 'Odinofagia',
            'plan' => 'Amoxicilina c/12 h',
            'signed_at' => now()->subDay(),
        ]);
    }

    public function test_doctor_can_add_an_encrypted_addendum_to_a_signed_note(): void
    {
        $this->actingAs($this->medico)
            ->post(route('consultations.addenda.store', $this->signed), ['body' => 'Se corrige: amoxicilina cada 8 horas.'])
            ->assertSessionHasNoErrors();

        $addendum = ConsultationAddendum::query()->firstOrFail();
        $this->assertSame('Se corrige: amoxicilina cada 8 horas.', $addendum->body);
        $this->assertNotSame('Se corrige: amoxicilina cada 8 horas.', DB::table('consultation_addenda')->value('body'));

        // La nota original no cambia.
        $this->assertSame('Amoxicilina c/12 h', $this->signed->fresh()?->plan);

        $this->get(route('consultations.show', $this->signed))
            ->assertInertia(fn (Assert $page) => $page
                ->has('consultation.addenda', 1)
                ->where('consultation.addenda.0.author', 'Dra. Méndez')
                ->where('consultation.addenda.0.body', 'Se corrige: amoxicilina cada 8 horas.'));
    }

    public function test_drafts_are_edited_directly_instead_of_addenda(): void
    {
        $draft = Consultation::query()->create(['patient_id' => $this->signed->patient_id, 'consulted_at' => now()]);

        $this->actingAs($this->medico)
            ->post(route('consultations.addenda.store', $draft), ['body' => 'Aclaración de prueba'])
            ->assertSessionHasErrors(['body' => 'La nota aún no está firmada: edítala directamente.']);
    }

    public function test_addenda_require_text_and_doctor_permission(): void
    {
        $this->actingAs($this->medico)
            ->post(route('consultations.addenda.store', $this->signed), ['body' => 'ok'])
            ->assertSessionHasErrors('body');

        $recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);
        $this->actingAs($recepcion)
            ->post(route('consultations.addenda.store', $this->signed), ['body' => 'Intento de recepción'])
            ->assertForbidden();
    }

    public function test_addenda_cannot_be_modified_or_deleted(): void
    {
        $addendum = $this->signed->addenda()->create(['body' => 'Aclaración original', 'created_by' => $this->medico->id]);

        try {
            $addendum->update(['body' => 'Cambiada']);
            $this->fail('Se esperaba una excepción al modificar.');
        } catch (LogicException) {
            // esperado
        }

        $this->expectException(LogicException::class);
        $addendum->delete();
    }
}
