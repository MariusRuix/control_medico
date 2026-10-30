<?php

declare(strict_types=1);

namespace Tests\Feature\Backups;

use App\Enums\Role;
use App\Models\User;
use App\Services\Backups\BackupService;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private User $medico;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
        $this->medico = User::factory()->create()->assignRole(Role::Medico->value);

        $this->dir = sys_get_temp_dir().'/cm-backups-'.uniqid();
        config([
            'backup.path' => $this->dir,
            'backup.password' => 'clave-respaldo',
            'database.connections.mariadb_test' => [
                'driver' => 'mariadb', 'host' => '127.0.0.1', 'port' => 3306,
                'database' => 'control_medico', 'username' => 'root', 'password' => 'secreta',
            ],
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('patients/1/estudio.pdf', '%PDF-1.4 prueba');
        Storage::disk('local')->put('settings/firma.png', 'png');

        $this->fakeMysqldump();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Simula mysqldump escribiendo el archivo de --result-file.
     */
    private function fakeMysqldump(bool $fail = false): void
    {
        // El volcado usa la conexión "mariadb_test" (la de pruebas es SQLite en memoria).
        config(['backup.connection' => 'mariadb_test']);

        Process::fake(function (PendingProcess $process) use ($fail) {
            if ($fail) {
                return Process::result(errorOutput: 'Access denied for user', exitCode: 2);
            }

            foreach ((array) $process->command as $arg) {
                if (str_starts_with((string) $arg, '--result-file=')) {
                    file_put_contents(substr((string) $arg, 14), "-- MariaDB dump\nCREATE TABLE patients (id int);\n");
                }
            }

            return Process::result();
        });
    }

    private function asMedico(): static
    {
        return $this->actingAs($this->medico)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_backup_contains_database_files_and_is_encrypted(): void
    {
        $result = app(BackupService::class)->run();

        $this->assertSame(2, $result['files']);
        $path = $this->dir.'/'.$result['file'];
        $this->assertFileExists($path);

        $zip = new ZipArchive;
        $zip->open($path);
        $this->assertSame(4, $zip->numFiles);
        $this->assertFalse($zip->getFromName('database/database.sql'), 'Sin contraseña no se debe poder leer.');
        $zip->setPassword('clave-respaldo');
        $this->assertStringContainsString('CREATE TABLE patients', (string) $zip->getFromName('database/database.sql'));
        $this->assertSame('%PDF-1.4 prueba', $zip->getFromName('files/patients/1/estudio.pdf'));
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertTrue($manifest['encrypted']);
        $this->assertSame(2, $manifest['files']['count']);
        $zip->close();

        // La contraseña de la base de datos no viaja en la línea de comandos.
        Process::assertRan(fn (PendingProcess $p): bool => $p->environment === ['MYSQL_PWD' => 'secreta']
            && ! str_contains(implode(' ', (array) $p->command), 'secreta')
            && in_array('--single-transaction', (array) $p->command, true));

        $this->assertDatabaseHas('audit_logs', ['action' => 'backup_created']);
        $this->assertNull(app(BackupService::class)->warning());
    }

    public function test_failures_are_recorded_and_shown(): void
    {
        $this->fakeMysqldump(fail: true);

        $this->expectExceptionMessage('mysqldump falló: Access denied for user');

        try {
            app(BackupService::class)->run();
        } finally {
                $this->assertSame('Aún no se ha hecho ningún respaldo.', app(BackupService::class)->warning());
            $this->assertDatabaseHas('settings', ['key' => 'backup.last_status']);
            $this->assertSame([], glob($this->dir.'/*') ?: []);
        }
    }

    public function test_old_backups_are_pruned_but_the_latest_are_kept(): void
    {
        File::ensureDirectoryExists($this->dir);

        foreach (['2026-08-01-213000', '2026-08-02-213000', '2026-08-03-213000', '2026-08-04-213000', '2026-10-01-213000'] as $stamp) {
            $file = $this->dir.'/respaldo-control-medico-'.$stamp.'.zip';
            file_put_contents($file, 'x');
            touch($file, Carbon::createFromFormat('Y-m-d-His', $stamp)->getTimestamp());
        }
        file_put_contents($this->dir.'/otra-cosa.zip', 'x');

        Carbon::setTestNow('2026-10-30 21:30:00');
        $result = app(BackupService::class)->run();

        $this->assertSame(3, $result['pruned']); // quedan: el nuevo, 2026-10-01 y 2026-08-04 (mínimo 3)
        $names = array_column(app(BackupService::class)->list(), 'name');
        $this->assertSame([$result['file'], 'respaldo-control-medico-2026-10-01-213000.zip', 'respaldo-control-medico-2026-08-04-213000.zip'], $names);
        $this->assertFileExists($this->dir.'/otra-cosa.zip');
    }

    public function test_screen_run_now_and_download_are_for_the_doctor_only(): void
    {
        $this->actingAs($this->medico)->get(route('settings.backups.index'))->assertRedirect(route('password.confirm'));

        $recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);
        $this->actingAs($recepcion)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('settings.backups.index'))->assertForbidden();

        $this->asMedico()->post(route('settings.backups.store'))->assertSessionHasNoErrors();

        $this->asMedico()->get(route('settings.backups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Backups')
                ->has('backups', 1)
                ->where('status.last_status', 'ok')
                ->where('status.warning', null)
                ->where('config.encrypted', true)
                ->where('config.inside_project', false));

        $name = app(BackupService::class)->list()[0]['name'];
        $this->asMedico()->get(route('settings.backups.download', $name))->assertOk()->assertDownload($name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup_downloaded']);

        $this->asMedico()->get('/settings/backups/..%2F.env')->assertNotFound();
        $this->asMedico()->get(route('settings.backups.download', 'respaldo-control-medico-2020-01-01-000000.zip'))->assertNotFound();
    }

    public function test_dashboard_warns_when_there_is_no_recent_backup(): void
    {

        $this->actingAs($this->medico)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('backupWarning', 'Aún no se ha hecho ningún respaldo.'));

        app(SettingsService::class)->setMany([
            'backup.last_success_at' => now()->subDays(3)->toIso8601String(),
            'backup.last_status' => 'ok',
        ]);

        $this->actingAs($this->medico)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('backupWarning', 'El último respaldo exitoso fue hace 3 día(s).'));
    }
}
