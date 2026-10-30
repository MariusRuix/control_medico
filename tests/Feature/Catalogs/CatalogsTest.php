<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Enums\Role;
use App\Models\Cie10Code;
use App\Models\Medication;
use App\Models\NoteTemplate;
use App\Models\Service;
use App\Models\User;
use App\Services\Agenda\WorkingHours;
use Database\Seeders\Cie10CodeSeeder;
use Database\Seeders\MedicationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogsTest extends TestCase
{
    use RefreshDatabase;

    private User $medico;

    private User $recepcion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class, MedicationSeeder::class, ServiceSeeder::class, Cie10CodeSeeder::class]);

        $this->medico = User::factory()->create()->assignRole(Role::Medico->value);
        $this->recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);
    }

    public function test_reception_cannot_manage_catalogs_or_schedule(): void
    {
        $this->actingAs($this->recepcion);

        $this->get(route('catalogs.vademecum.index'))->assertForbidden();
        $this->post(route('catalogs.services.store'), ['name' => 'X', 'price' => 1])->assertForbidden();
        $this->get(route('settings.schedule.edit'))->assertForbidden();
    }

    public function test_vademecum_list_filters_and_creates_without_duplicates(): void
    {
        $this->actingAs($this->medico)
            ->get(route('catalogs.vademecum.index', ['filter' => 'favorites']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalogs/Medications')
                ->has('medications.data', 4)
                ->has('medications.meta'));

        $payload = ['generic_name' => 'Cetirizina', 'concentration' => '10 mg', 'presentation' => 'Tabletas', 'is_favorite' => true, 'default_dose' => '1 tableta'];

        $this->post(route('catalogs.vademecum.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('catalogs.vademecum.store'), $payload)
            ->assertSessionHasErrors(['generic_name' => 'Ya existe este medicamento con la misma concentración y presentación.']);

        $med = Medication::query()->where('generic_name', 'Cetirizina')->firstOrFail();
        $this->assertTrue($med->is_favorite);

        $this->patch(route('catalogs.vademecum.favorite', $med))->assertRedirect();
        $this->assertFalse($med->fresh()?->is_favorite);

        $this->put(route('catalogs.vademecum.update', $med), [...$payload, 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($med->fresh()?->is_active);
    }

    public function test_services_require_duration_only_when_appointable(): void
    {
        $this->actingAs($this->medico);

        $this->post(route('catalogs.services.store'), ['name' => 'Retiro de puntos', 'price' => '350', 'is_appointable' => true])
            ->assertSessionHasErrors(['duration_minutes' => 'Indica la duración para poder agendarlo.']);

        $this->post(route('catalogs.services.store'), ['name' => 'Retiro de puntos', 'price' => '350', 'is_appointable' => false])
            ->assertSessionHasNoErrors();

        $this->post(route('catalogs.services.store'), ['name' => 'Retiro de puntos', 'price' => '1'])
            ->assertSessionHasErrors('name');

        $service = Service::query()->where('name', 'Retiro de puntos')->firstOrFail();
        $this->put(route('catalogs.services.update', $service), [
            'name' => 'Retiro de puntos', 'price' => '400', 'is_appointable' => true, 'duration_minutes' => 15, 'color' => 'teal',
        ])->assertSessionHasNoErrors();

        $this->assertSame('400.00', $service->fresh()?->price);
        $this->assertTrue($service->fresh()?->is_appointable);
    }

    public function test_note_templates_crud(): void
    {
        $this->actingAs($this->medico);

        $this->post(route('catalogs.templates.store'), ['name' => 'Vacía', 'structure' => ['subjective' => '  ']])
            ->assertSessionHasErrors(['structure' => 'Escribe el texto de al menos un apartado.']);

        $this->post(route('catalogs.templates.store'), [
            'name' => 'Control de diabetes',
            'structure' => ['subjective' => "Apego a tratamiento:\nHipoglucemias:", 'plan' => 'Control en 3 meses'],
        ])->assertSessionHasNoErrors();

        $template = NoteTemplate::query()->where('name', 'Control de diabetes')->firstOrFail();
        $this->assertSame(['subjective' => "Apego a tratamiento:\nHipoglucemias:", 'plan' => 'Control en 3 meses'], $template->structure);

        $this->delete(route('catalogs.templates.destroy', $template))->assertRedirect();
        $this->assertModelMissing($template);
    }

    public function test_cie10_import_accepts_dgis_headers_latin1_and_semicolons(): void
    {
        $csv = mb_convert_encoding("CATALOG_KEY;NOMBRE;CAPITULO\nA000;CÓLERA DEBIDO A VIBRIO CHOLERAE 01;I\nJ029;FARINGITIS AGUDA, NO ESPECIFICADA (actualizada);X\n;sin código;I\n", 'Windows-1252', 'UTF-8');
        $file = UploadedFile::fake()->createWithContent('cie10.csv', $csv);

        $this->actingAs($this->medico)
            ->post(route('catalogs.diagnoses.import'), ['file' => $file])
            ->assertSessionHasNoErrors();

        $this->assertSame('CÓLERA DEBIDO A VIBRIO CHOLERAE 01', Cie10Code::query()->where('code', 'A000')->value('description'));
        $this->assertTrue(Cie10Code::query()->where('code', 'J029')->exists());
    }

    public function test_cie10_import_rejects_files_without_expected_columns(): void
    {
        $file = UploadedFile::fake()->createWithContent('otro.csv', "foo,bar\n1,2\n");

        $this->actingAs($this->medico)
            ->post(route('catalogs.diagnoses.import'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_hidden_cie10_codes_leave_autocomplete(): void
    {
        $code = Cie10Code::query()->where('code', 'J02.9')->firstOrFail();

        $this->actingAs($this->medico)->patch(route('catalogs.diagnoses.toggle', $code))->assertRedirect();

        $this->getJson(route('catalogs.cie10', ['q' => 'J02']))
            ->assertOk()
            ->assertJsonMissing(['code' => 'J02.9']);
    }

    public function test_schedule_is_validated_and_used_by_the_agenda(): void
    {
        $this->actingAs($this->medico);

        $base = ['mon' => [], 'tue' => [], 'wed' => [], 'thu' => [], 'fri' => [], 'sat' => [], 'sun' => []];

        $this->put(route('settings.schedule.update'), [
            'slot_minutes' => 20,
            'working_hours' => [...$base, 'mon' => [['09:00', '14:00'], ['13:00', '18:00']]],
        ])->assertSessionHasErrors(['working_hours.mon' => 'Los horarios del día se empalman.']);

        $this->put(route('settings.schedule.update'), [
            'slot_minutes' => 20,
            'working_hours' => [...$base, 'mon' => [['16:00', '19:00'], ['08:00', '12:00']]],
        ])->assertSessionHasNoErrors();

        $hours = app(WorkingHours::class);
        $this->assertSame([['08:00', '12:00'], ['16:00', '19:00']], $hours->weekly()['mon']);
        $this->assertSame([], $hours->weekly()['sat']);
        $this->assertSame(20, $hours->slotMinutes());
    }
}
