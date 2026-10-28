<?php

declare(strict_types=1);

namespace App\Services\Prescriptions;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Datos del médico y del consultorio que exige la receta
 * (nombre, cédula profesional, institución, domicilio).
 */
final class PrescriberProfile
{
    /** Campos mínimos para emitir una receta válida. */
    private const array REQUIRED = [
        'doctor.name' => 'nombre del médico',
        'doctor.professional_license' => 'cédula profesional',
        'doctor.university' => 'institución que expidió el título',
        'clinic.address' => 'domicilio del consultorio',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return list<string> Etiquetas de los datos que faltan.
     */
    public function missing(): array
    {
        $missing = [];

        foreach (self::REQUIRED as $key => $label) {
            if (blank($this->settings->get($key))) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function isComplete(): bool
    {
        return $this->missing() === [];
    }

    /**
     * @return array{clinic: array<string, mixed>, doctor: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'clinic' => $this->settings->group('clinic'),
            'doctor' => $this->settings->group('doctor'),
        ];
    }

    /**
     * Imagen (firma o sello) como data URI para incrustarla en el PDF.
     */
    public function imageDataUri(string $key): ?string
    {
        $path = $this->settings->get($key);

        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            $disk = Storage::disk((string) config('medical.files_disk'));

            if (! $disk->exists($path)) {
                return null;
            }

            $mime = $disk->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
        } catch (Throwable $e) {
            Log::warning('No se pudo leer la imagen de firma/sello.', ['key' => $key, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
