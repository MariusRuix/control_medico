<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Catalogs\Cie10Importer;
use Illuminate\Database\Seeder;

/**
 * Importa el catálogo CIE-10 desde database/data/cie10.csv. El archivo
 * incluido trae solo diagnósticos frecuentes; el catálogo completo de la
 * DGIS se puede cargar desde Catálogos → CIE-10.
 */
class Cie10CodeSeeder extends Seeder
{
    public function run(Cie10Importer $importer): void
    {
        $path = database_path('data/cie10.csv');

        if (! is_readable($path)) {
            $this->command->warn('No se encontró database/data/cie10.csv; se omite el catálogo CIE-10.');

            return;
        }

        $result = $importer->import($path);

        $this->command->info("CIE-10: {$result['imported']} códigos importados/actualizados.");
    }
}
