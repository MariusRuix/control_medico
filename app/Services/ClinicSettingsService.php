<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Guarda los datos del consultorio y las imágenes (logo, firma, sello)
 * en el disco privado.
 */
final class ClinicSettingsService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  array<string, string|null>  $values
     * @param  array<string, UploadedFile>  $images
     * @param  list<string>  $remove
     */
    public function update(array $values, array $images, array $remove): void
    {
        $disk = Storage::disk((string) config('medical.files_disk'));
        $stored = [];

        try {
            foreach ($remove as $key) {
                $values[$key] = null;
            }

            foreach ($images as $key => $file) {
                $path = $file->storeAs('settings', Str::slug(str_replace('.', '-', $key)).'-'.Str::random(8).'.'.$file->extension(), ['disk' => config('medical.files_disk')]);

                if ($path === false) {
                    throw new \RuntimeException("No se pudo guardar la imagen {$key}.");
                }

                $stored[] = $path;
                $values[$key] = $path;
            }

            $previous = [];

            foreach (array_merge(array_keys($images), $remove) as $key) {
                $previous[] = $this->settings->get($key);
            }

            $this->settings->setMany($values);

            // Las imágenes anteriores se borran solo después de guardar con éxito.
            foreach (array_filter($previous, 'is_string') as $old) {
                $disk->delete($old);
            }
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                $disk->delete($path);
            }

            Log::error('No se pudieron guardar los datos del consultorio.', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Respuesta con la imagen para previsualizarla en la configuración.
     */
    public function imagePath(string $key): ?string
    {
        $path = $this->settings->get($key);

        return is_string($path) && Storage::disk((string) config('medical.files_disk'))->exists($path) ? $path : null;
    }
}
