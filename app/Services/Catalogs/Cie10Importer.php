<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Importa el catálogo CIE-10 desde un CSV. Reconoce los encabezados del
 * archivo propio (code, description, chapter) y los del catálogo de la DGIS
 * (CATALOG_KEY, NOMBRE, CAPITULO…), separador coma, punto y coma, tabulador
 * o barra, y archivos en UTF-8 o Latin-1. Actualiza los códigos existentes.
 */
final class Cie10Importer
{
    private const int CHUNK = 1000;

    private const array CODE_HEADERS = ['code', 'codigo', 'clave', 'catalog_key', 'cve', 'letra'];

    private const array DESCRIPTION_HEADERS = ['description', 'descripcion', 'nombre', 'name'];

    private const array CHAPTER_HEADERS = ['chapter', 'capitulo', 'cap'];

    /**
     * @return array{imported: int, skipped: int}
     */
    public function import(string $path): array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo del catálogo.');
        }

        try {
            $firstLine = (string) fgets($handle);
            rewind($handle);

            $delimiter = $this->detectDelimiter($firstLine);
            $header = fgetcsv($handle, null, $delimiter, '"', '');

            if ($header === false) {
                throw new RuntimeException('El archivo está vacío.');
            }

            $columns = $this->mapColumns(array_map(fn ($h): string => $this->normalizeHeader((string) $h), $header));

            $imported = 0;
            $skipped = 0;
            $batch = [];
            $now = now();

            while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                $code = $this->clean($row[$columns['code']] ?? '');
                $description = $this->clean($row[$columns['description']] ?? '');

                if ($code === '' || $description === '' || strlen($code) > 10) {
                    $skipped++;

                    continue;
                }

                $chapter = $columns['chapter'] !== null ? $this->clean($row[$columns['chapter']] ?? '') : '';

                $batch[$code] = [
                    'code' => strtoupper($code),
                    'description' => mb_substr($description, 0, 500),
                    'chapter' => $chapter !== '' ? mb_substr($chapter, 0, 10) : null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) === self::CHUNK) {
                    $imported += $this->flush($batch);
                    $batch = [];
                }
            }

            $imported += $this->flush($batch);

            return ['imported' => $imported, 'skipped' => $skipped];
        } catch (Throwable $e) {
            Log::error('Falló la importación del catálogo CIE-10.', ['error' => $e->getMessage()]);

            throw $e;
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [];

        foreach ([',', ';', "\t", '|'] as $candidate) {
            $counts[$candidate] = substr_count($line, $candidate);
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @param  list<string>  $headers
     * @return array{code: int, description: int, chapter: int|null}
     */
    private function mapColumns(array $headers): array
    {
        $find = static function (array $names) use ($headers): ?int {
            foreach ($headers as $index => $header) {
                if (in_array($header, $names, true)) {
                    return $index;
                }
            }

            return null;
        };

        $code = $find(self::CODE_HEADERS);
        $description = $find(self::DESCRIPTION_HEADERS);

        if ($code === null || $description === null) {
            throw new RuntimeException('No se encontraron las columnas de código y descripción (p. ej. "code,description" o "CATALOG_KEY,NOMBRE").');
        }

        return ['code' => $code, 'description' => $description, 'chapter' => $find(self::CHAPTER_HEADERS)];
    }

    private function normalizeHeader(string $value): string
    {
        $value = $this->clean($value);
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return strtolower(trim((string) ($ascii !== false ? $ascii : $value)));
    }

    /**
     * Texto limpio en UTF-8 (convierte desde Latin-1 si hace falta).
     */
    private function clean(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function flush(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        DB::table('cie10_codes')->upsert(array_values($rows), ['code'], ['description', 'chapter', 'is_active', 'updated_at']);

        return count($rows);
    }
}
