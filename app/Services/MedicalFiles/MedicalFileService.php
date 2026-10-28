<?php

declare(strict_types=1);

namespace App\Services\MedicalFiles;

use App\Enums\MedicalFileType;
use App\Models\MedicalFile;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Estudios y archivos del expediente. Se guardan en un disco privado con
 * nombre aleatorio (nunca el nombre original) y solo se sirven por controlador.
 */
final class MedicalFileService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  array{type: string, title?: string|null, study_date?: string|null, consultation_id?: int|null, notes?: string|null}  $meta
     * @return list<MedicalFile>
     */
    public function store(Patient $patient, array $files, array $meta, User $uploader): array
    {
        $disk = $this->disk();
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($patient, $files, $meta, $uploader, $disk, &$storedPaths): array {
                $created = [];

                foreach ($files as $file) {
                    $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
                    $path = $file->storeAs(
                        "patients/{$patient->id}",
                        Str::uuid()->toString().'.'.$extension,
                        ['disk' => $disk],
                    );

                    if ($path === false) {
                        throw new RuntimeException('No se pudo guardar el archivo en el disco.');
                    }

                    $storedPaths[] = $path;
                    $originalName = Str::limit($file->getClientOriginalName(), 250, '');

                    $created[] = $patient->medicalFiles()->create([
                        'consultation_id' => $meta['consultation_id'] ?? null,
                        'type' => MedicalFileType::from($meta['type']),
                        'title' => $this->title($meta['title'] ?? null, $originalName, count($files)),
                        'disk' => $disk,
                        'path' => $path,
                        'original_name' => $originalName,
                        'mime_type' => (string) $file->getMimeType(),
                        'size_bytes' => (int) $file->getSize(),
                        'study_date' => $meta['study_date'] ?? null,
                        'notes' => $meta['notes'] ?? null,
                        'uploaded_by' => $uploader->id,
                    ]);
                }

                return $created;
            });
        } catch (Throwable $e) {
            // Si falla la base de datos, no dejar archivos huérfanos.
            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }

            Log::error('No se pudieron guardar los estudios del paciente.', [
                'patient_id' => $patient->id,
                'files' => count($files),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Respuesta para ver (inline) o descargar el archivo. Registra la consulta en la bitácora.
     */
    public function response(MedicalFile $file, bool $download): StreamedResponse
    {
        $storage = Storage::disk($file->disk);

        abort_unless($storage->exists($file->path), 404, 'El archivo ya no está disponible.');

        $this->audit->log($download ? 'downloaded' : 'viewed', $file);

        $headers = [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        return $download
            ? $storage->download($file->path, $this->downloadName($file), $headers)
            : $storage->response($file->path, $this->downloadName($file), $headers, 'inline');
    }

    /**
     * Baja lógica: el archivo físico se conserva (NOM-004: mínimo 5 años).
     */
    public function delete(MedicalFile $file, string $reason): void
    {
        try {
            DB::transaction(function () use ($file, $reason): void {
                $this->audit->log('voided', $file, [], ['reason' => $reason]);
                $file->delete();
            });
        } catch (Throwable $e) {
            Log::error('No se pudo eliminar el estudio.', ['file_id' => $file->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    private function title(?string $title, string $originalName, int $count): string
    {
        $base = pathinfo($originalName, PATHINFO_FILENAME) ?: 'Archivo';

        if ($title === null || trim($title) === '') {
            return Str::limit($base, 150, '');
        }

        // Con varios archivos, el título común se complementa con el nombre de cada uno.
        return Str::limit($count > 1 ? "{$title} · {$base}" : $title, 150, '');
    }

    private function downloadName(MedicalFile $file): string
    {
        $extension = pathinfo($file->path, PATHINFO_EXTENSION);
        $date = $file->study_date?->format('Y-m-d') ?? $file->created_at?->format('Y-m-d');

        return Str::slug(trim("{$date} {$file->title}")).'.'.$extension;
    }

    private function disk(): string
    {
        return (string) config('medical.files_disk');
    }
}
