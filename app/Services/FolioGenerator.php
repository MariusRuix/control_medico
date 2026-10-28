<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Genera folios consecutivos (pacientes, recetas) a partir de los contadores
 * guardados en settings. Usa bloqueo de fila para evitar folios duplicados
 * cuando dos usuarios guardan al mismo tiempo.
 */
final class FolioGenerator
{
    public function __construct(private readonly SettingsService $settings) {}

    public function nextPatientFolio(): string
    {
        return $this->next('folios.patient_prefix', 'folios.patient_next', 'P-', 6);
    }

    public function nextPrescriptionFolio(): string
    {
        return $this->next('folios.prescription_prefix', 'folios.prescription_next', 'R-', 6);
    }

    private function next(string $prefixKey, string $counterKey, string $defaultPrefix, int $padding): string
    {
        try {
            $folio = DB::transaction(function () use ($prefixKey, $counterKey, $defaultPrefix, $padding): string {
                $counter = Setting::query()->where('key', $counterKey)->lockForUpdate()->first();

                if ($counter === null) {
                    $counter = Setting::query()->create([
                        'group' => strstr($counterKey, '.', true),
                        'key' => $counterKey,
                        'value' => 1,
                    ]);
                }

                $number = max(1, (int) $counter->value);
                $counter->update(['value' => $number + 1]);

                $prefix = (string) (Setting::query()->where('key', $prefixKey)->first()?->value ?? $defaultPrefix);

                return $prefix.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
            });
        } catch (Throwable $e) {
            Log::error('No se pudo generar el folio.', ['counter' => $counterKey, 'error' => $e->getMessage()]);

            throw $e;
        }

        $this->settings->flush();

        return $folio;
    }
}
