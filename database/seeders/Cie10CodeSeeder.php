<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Importa el catálogo CIE-10 desde database/data/cie10.csv
 * (columnas: code,description,chapter). El archivo incluido trae solo
 * diagnósticos frecuentes; reemplázalo por el catálogo completo de la
 * DGIS (Secretaría de Salud) con las mismas columnas.
 */
class Cie10CodeSeeder extends Seeder
{
    private const int CHUNK = 1000;

    public function run(): void
    {
        $path = database_path('data/cie10.csv');

        if (! is_readable($path)) {
            $this->command->warn('No se encontró database/data/cie10.csv; se omite el catálogo CIE-10.');

            return;
        }

        try {
            $handle = fopen($path, 'rb');

            if ($handle === false) {
                throw new RuntimeException("No se pudo abrir {$path}");
            }

            $header = fgetcsv($handle, escape: '');
            $total = 0;
            $batch = [];
            $now = now();

            while (($row = fgetcsv($handle, escape: '')) !== false) {
                if ($header === false || count($row) < 2 || trim((string) $row[0]) === '') {
                    continue;
                }

                $batch[] = [
                    'code' => strtoupper(trim((string) $row[0])),
                    'description' => trim((string) $row[1]),
                    'chapter' => isset($row[2]) && trim((string) $row[2]) !== '' ? trim((string) $row[2]) : null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) === self::CHUNK) {
                    $total += $this->flush($batch);
                    $batch = [];
                }
            }

            $total += $this->flush($batch);
            fclose($handle);

            $this->command->info("CIE-10: {$total} códigos importados/actualizados.");
        } catch (Throwable $e) {
            Log::error('Falló la importación del catálogo CIE-10.', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function flush(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        DB::table('cie10_codes')->upsert($rows, ['code'], ['description', 'chapter', 'updated_at']);

        return count($rows);
    }
}
