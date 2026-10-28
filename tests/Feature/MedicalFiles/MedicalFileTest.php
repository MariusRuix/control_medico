<?php

declare(strict_types=1);

namespace Tests\Feature\MedicalFiles;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\MedicalFile;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MedicalFileTest extends TestCase
{
    use RefreshDatabase;

    private User $medico;

    private User $recepcion;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);

        $this->medico = User::factory()->create()->assignRole(Role::Medico->value);
        $this->recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);
        $this->patient = Patient::query()->create(['folio' => 'P-000001', 'first_name' => 'Ana', 'last_name' => 'López']);
    }

    private function pdf(string $name = 'biometria.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    public function test_reception_can_upload_several_files_with_random_names(): void
    {
        $this->actingAs($this->recepcion)
            ->post(route('medical-files.store', $this->patient), [
                'files' => [$this->pdf(), UploadedFile::fake()->image('torax.png', 600, 600)],
                'type' => 'laboratorio',
                'title' => 'Estudios de control',
                'study_date' => '2026-10-20',
            ])
            ->assertSessionHasNoErrors();

        $files = MedicalFile::query()->orderBy('id')->get();

        $this->assertCount(2, $files);
        $this->assertSame('Estudios de control · biometria', $files[0]->title);
        $this->assertSame('application/pdf', $files[0]->mime_type);
        $this->assertSame($this->recepcion->id, $files[0]->uploaded_by);
        $this->assertStringStartsWith("patients/{$this->patient->id}/", $files[0]->path);
        $this->assertStringNotContainsString('biometria', $files[0]->path);
        Storage::disk('local')->assertExists($files[0]->path);
        Storage::disk('local')->assertExists($files[1]->path);
    }

    public function test_disallowed_types_and_oversized_files_are_rejected(): void
    {
        $this->actingAs($this->recepcion);

        $this->post(route('medical-files.store', $this->patient), [
            'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
            'type' => 'laboratorio',
        ])->assertSessionHasErrors(['files.0' => 'Solo se aceptan PDF o imágenes (JPG, PNG, WEBP).']);

        $this->post(route('medical-files.store', $this->patient), [
            'files' => [UploadedFile::fake()->create('grande.pdf', 30000, 'application/pdf')],
            'type' => 'laboratorio',
        ])->assertSessionHasErrors('files.0');

        $this->assertSame(0, MedicalFile::query()->count());
    }

    public function test_consultation_must_belong_to_the_patient(): void
    {
        $other = Patient::query()->create(['folio' => 'P-000002', 'first_name' => 'Luis', 'last_name' => 'Soto']);
        $foreign = Consultation::query()->create(['patient_id' => $other->id, 'consulted_at' => now()]);
        $own = Consultation::query()->create(['patient_id' => $this->patient->id, 'consulted_at' => now()]);

        $this->actingAs($this->medico)
            ->post(route('medical-files.store', $this->patient), [
                'files' => [$this->pdf()], 'type' => 'laboratorio', 'consultation_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('consultation_id');

        $this->post(route('medical-files.store', $this->patient), [
            'files' => [$this->pdf()], 'type' => 'laboratorio', 'consultation_id' => $own->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($own->id, MedicalFile::query()->firstOrFail()->consultation_id);
    }

    public function test_only_the_doctor_can_open_files_and_access_is_audited(): void
    {
        $this->actingAs($this->recepcion)->post(route('medical-files.store', $this->patient), [
            'files' => [$this->pdf()], 'type' => 'laboratorio',
        ]);
        $file = MedicalFile::query()->firstOrFail();

        $this->get(route('medical-files.show', $file))->assertForbidden();

        $this->actingAs($this->medico)
            ->get(route('medical-files.show', $file))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get(route('medical-files.show', ['file' => $file, 'download' => 1]))
            ->assertOk()
            ->assertDownload();

        $this->assertSame(2, AuditLog::query()->whereIn('action', ['viewed', 'downloaded'])->where('auditable_id', $file->id)->count());
    }

    public function test_deleting_keeps_the_physical_file_and_requires_a_reason(): void
    {
        $this->actingAs($this->medico)->post(route('medical-files.store', $this->patient), [
            'files' => [$this->pdf()], 'type' => 'laboratorio',
        ]);
        $file = MedicalFile::query()->firstOrFail();

        $this->actingAs($this->recepcion)->delete(route('medical-files.destroy', $file), ['reason' => 'Error'])->assertForbidden();

        $this->actingAs($this->medico)->delete(route('medical-files.destroy', $file))->assertSessionHasErrors('reason');
        $this->delete(route('medical-files.destroy', $file), ['reason' => 'Paciente equivocado'])->assertSessionHasNoErrors();

        $this->assertSoftDeleted($file);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_patient_page_lists_files_for_reception_without_view_permission(): void
    {
        $this->actingAs($this->recepcion)->post(route('medical-files.store', $this->patient), [
            'files' => [$this->pdf()], 'type' => 'rayos_x', 'title' => 'Tórax PA',
        ]);

        $this->get(route('patients.show', $this->patient))
            ->assertInertia(fn (Assert $page) => $page
                ->has('files', 1)
                ->where('files.0.title', 'Tórax PA')
                ->where('files.0.type_label', 'Rayos X')
                ->missing('files.0.path')
                ->where('can.uploadFiles', true)
                ->where('can.viewFiles', false)
                ->where('can.deleteFiles', false));
    }
}
