<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Configuración global del consultorio (datos de la clínica, del médico,
 * horarios, recordatorios y folios). Se guarda en la tabla settings con
 * llaves tipo "grupo.nombre" y se cachea completa.
 */
final class SettingsService
{
    private const string CACHE_KEY = 'settings.all';

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /**
     * Todos los valores de un grupo, p. ej. group('clinic') => ['name' => ..., ...].
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $values = Arr::get($this->all(), $group, []);

        return is_array($values) ? $values : [];
    }

    /**
     * Arreglo anidado: ['clinic' => ['name' => ...], 'doctor' => [...]].
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        try {
            /** @var array<string, mixed> */
            return Cache::rememberForever(self::CACHE_KEY, function (): array {
                $nested = [];

                Setting::query()
                    ->orderBy('key')
                    ->get(['key', 'value'])
                    ->each(function (Setting $setting) use (&$nested): void {
                        Arr::set($nested, $setting->key, $setting->value);
                    });

                return $nested;
            });
        } catch (Throwable $e) {
            Log::error('No se pudo leer la configuración.', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    /**
     * @param  array<string, mixed>  $values  ['clinic.name' => 'Consultorio X', ...]
     */
    public function setMany(array $values): void
    {
        try {
            DB::transaction(function () use ($values): void {
                foreach ($values as $key => $value) {
                    $this->assertValidKey($key);

                    Setting::query()->updateOrCreate(
                        ['key' => $key],
                        ['group' => strstr($key, '.', true), 'value' => $value],
                    );
                }
            });
        } catch (Throwable $e) {
            Log::error('No se pudo guardar la configuración.', [
                'keys' => array_keys($values),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $this->flush();
        }
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function assertValidKey(string $key): void
    {
        if (preg_match('/^[a-z0-9_]+\.[a-z0-9_.]+$/', $key) !== 1) {
            throw new RuntimeException("Llave de configuración inválida: {$key}. Usa el formato grupo.nombre");
        }
    }
}
