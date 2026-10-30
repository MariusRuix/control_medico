<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Respaldo completo del consultorio en un ZIP (cifrado con AES-256 si hay
 * contraseña): volcado de la base de datos + archivos privados (estudios,
 * recetas en PDF, firma, sello y logotipo) + manifest.json.
 */
final class BackupService
{
    public const string PREFIX = 'respaldo-control-medico-';

    private const string NAME_PATTERN = '/^respaldo-control-medico-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/';

    public function __construct(
        private readonly DatabaseDumper $dumper,
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function directory(): string
    {
        return rtrim((string) config('backup.path'), '\\/');
    }

    public function encrypted(): bool
    {
        return filled(config('backup.password'));
    }

    /**
     * @return array{file: string, size: int, files: int, pruned: int}
     */
    public function run(?User $actor = null): array
    {
        $lock = Cache::lock('backups:run', 1800);

        if (! $lock->get()) {
            throw new RuntimeException('Ya hay un respaldo en curso. Espera a que termine.');
        }

        $workDir = storage_path('app/backup-tmp/'.Str::random(16));
        $target = null;

        try {
            File::ensureDirectoryExists($this->directory());

            if (! is_writable($this->directory())) {
                throw new RuntimeException("No se puede escribir en la carpeta de respaldos ({$this->directory()}).");
            }

            File::ensureDirectoryExists($workDir);
            $dump = $this->dumper->dump($workDir);

            $name = self::PREFIX.Date::now()->format('Y-m-d-His').'.zip';
            $target = $this->directory().DIRECTORY_SEPARATOR.$name;
            $files = $this->writeZip($target.'.part', $dump);

            if (! rename($target.'.part', $target)) {
                throw new RuntimeException('No se pudo guardar el archivo del respaldo.');
            }

            $this->verify($target, $files + 2);
            $pruned = $this->prune();
            $size = (int) filesize($target);

            $this->settings->setMany([
                'backup.last_attempt_at' => Date::now()->toIso8601String(),
                'backup.last_success_at' => Date::now()->toIso8601String(),
                'backup.last_status' => 'ok',
                'backup.last_error' => null,
                'backup.last_file' => $name,
                'backup.last_size' => $size,
            ]);
            $this->audit->log('backup_created', null, [], ['file' => $name, 'size' => $size, 'files' => $files], $actor);

            return ['file' => $name, 'size' => $size, 'files' => $files, 'pruned' => $pruned];
        } catch (Throwable $e) {
            Log::error('Falló el respaldo.', ['error' => $e->getMessage()]);

            if ($target !== null) {
                @unlink($target.'.part');
            }

            try {
                $this->settings->setMany([
                    'backup.last_attempt_at' => Date::now()->toIso8601String(),
                    'backup.last_status' => 'failed',
                    'backup.last_error' => Str::limit($e->getMessage(), 480),
                ]);
                $this->audit->log('backup_failed', null, [], ['error' => Str::limit($e->getMessage(), 300)], $actor);
            } catch (Throwable) {
                // Si la base de datos no responde, el error ya quedó en el log.
            }

            throw $e;
        } finally {
            File::deleteDirectory($workDir);
            $lock->release();
        }
    }

    /**
     * Respaldos existentes, del más reciente al más antiguo.
     *
     * @return list<array{name: string, size: int, created_at: string}>
     */
    public function list(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.self::PREFIX.'*.zip') ?: [];

        $backups = [];

        foreach ($files as $path) {
            if (preg_match(self::NAME_PATTERN, basename($path)) !== 1) {
                continue;
            }

            $backups[] = [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'created_at' => Date::createFromTimestamp((int) filemtime($path))->toIso8601String(),
            ];
        }

        usort($backups, fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $backups;
    }

    /**
     * Ruta segura de un respaldo por su nombre (evita salir de la carpeta).
     */
    public function pathFor(string $name): ?string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        return is_file($path) ? $path : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $lastSuccess = $this->settings->get('backup.last_success_at');

        return [
            'last_attempt_at' => $this->settings->get('backup.last_attempt_at'),
            'last_success_at' => $lastSuccess,
            'last_status' => $this->settings->get('backup.last_status'),
            'last_error' => $this->settings->get('backup.last_error'),
            'last_file' => $this->settings->get('backup.last_file'),
            'last_size' => $this->settings->get('backup.last_size'),
            'warning' => $this->warning(),
        ];
    }

    /**
     * Aviso para Inicio cuando no hay un respaldo reciente.
     */
    public function warning(): ?string
    {
        $last = $this->settings->get('backup.last_success_at');

        if (! is_string($last) || $last === '') {
            return 'Aún no se ha hecho ningún respaldo.';
        }

        $hours = (int) Date::parse($last)->diffInHours(Date::now());

        if ($hours >= (int) config('backup.warn_after_hours', 48)) {
            return 'El último respaldo exitoso fue hace '.intdiv($hours, 24).' día(s).';
        }

        return $this->settings->get('backup.last_status') === 'failed'
            ? 'El último intento de respaldo falló.'
            : null;
    }

    /**
     * @return int número de archivos privados incluidos
     */
    private function writeZip(string $path, string $dump): int
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP del respaldo.');
        }

        $zip->addFile($dump, 'database/'.basename($dump));

        $files = 0;
        $disk = Storage::disk((string) config('medical.files_disk'));

        if ($this->isLocal($disk)) {
            /** @var FilesystemAdapter $disk */
            foreach ($disk->allFiles() as $relative) {
                if (str_starts_with(basename($relative), '.')) {
                    continue;
                }

                $zip->addFile($disk->path($relative), 'files/'.$relative);
                $files++;
            }
        }

        $zip->addFromString('manifest.json', (string) json_encode([
            'app' => config('app.name'),
            'created_at' => Date::now()->toIso8601String(),
            'database' => [
                'driver' => config('database.connections.'.DatabaseDumper::connection().'.driver'),
                'file' => 'database/'.basename($dump),
                'sha256' => hash_file('sha256', $dump),
            ],
            'files' => [
                'disk' => config('medical.files_disk'),
                'count' => $files,
                'restore_to' => 'storage/app/private',
            ],
            'encrypted' => $this->encrypted(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($this->encrypted()) {
            $password = (string) config('backup.password');

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zip->setEncryptionIndex($i, ZipArchive::EM_AES_256, $password);
            }
        }

        if (! $zip->close()) {
            throw new RuntimeException('No se pudo terminar de escribir el ZIP del respaldo.');
        }

        return $files;
    }

    /**
     * Abre el ZIP terminado y comprueba que esté completo y se pueda descifrar.
     */
    private function verify(string $path, int $expectedEntries): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('El respaldo se generó dañado.');
        }

        try {
            if ($this->encrypted()) {
                $zip->setPassword((string) config('backup.password'));
            }

            $manifest = $zip->getFromName('manifest.json');

            if ($zip->numFiles !== $expectedEntries || $manifest === false || json_decode($manifest) === null) {
                throw new RuntimeException('El respaldo está incompleto.');
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Borra los respaldos más antiguos que `keep_days`, conservando siempre los últimos `keep_min`.
     */
    private function prune(): int
    {
        $limit = Date::now()->subDays((int) config('backup.keep_days', 30))->getTimestamp();
        $deleted = 0;

        foreach (array_slice($this->list(), (int) config('backup.keep_min', 3)) as $backup) {
            $path = $this->directory().DIRECTORY_SEPARATOR.$backup['name'];

            if ((int) filemtime($path) < $limit && @unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function isLocal(Filesystem $disk): bool
    {
        return $disk instanceof FilesystemAdapter
            && $disk->getAdapter() instanceof LocalFilesystemAdapter;
    }
}
