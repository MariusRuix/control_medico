<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Medication;
use Illuminate\Database\Seeder;

/**
 * Algunos medicamentos de uso común como ejemplo del vademécum personal.
 */
class MedicationSeeder extends Seeder
{
    public function run(): void
    {
        $medications = [
            ['generic_name' => 'Paracetamol', 'presentation' => 'Tabletas', 'concentration' => '500 mg', 'route' => 'Oral', 'is_favorite' => true, 'default_dose' => '1 tableta', 'default_frequency' => 'Cada 8 horas', 'default_duration' => '3 días'],
            ['generic_name' => 'Ibuprofeno', 'presentation' => 'Tabletas', 'concentration' => '400 mg', 'route' => 'Oral', 'is_favorite' => true, 'default_dose' => '1 tableta', 'default_frequency' => 'Cada 8 horas', 'default_duration' => '5 días', 'default_instructions' => 'Tomar con alimentos'],
            ['generic_name' => 'Amoxicilina', 'presentation' => 'Cápsulas', 'concentration' => '500 mg', 'route' => 'Oral', 'is_favorite' => true, 'default_dose' => '1 cápsula', 'default_frequency' => 'Cada 8 horas', 'default_duration' => '7 días'],
            ['generic_name' => 'Omeprazol', 'presentation' => 'Cápsulas', 'concentration' => '20 mg', 'route' => 'Oral', 'is_favorite' => true, 'default_dose' => '1 cápsula', 'default_frequency' => 'Cada 24 horas', 'default_duration' => '14 días', 'default_instructions' => 'En ayunas'],
            ['generic_name' => 'Loratadina', 'presentation' => 'Tabletas', 'concentration' => '10 mg', 'route' => 'Oral', 'is_favorite' => false, 'default_dose' => '1 tableta', 'default_frequency' => 'Cada 24 horas', 'default_duration' => '5 días'],
            ['generic_name' => 'Metformina', 'presentation' => 'Tabletas', 'concentration' => '850 mg', 'route' => 'Oral', 'is_favorite' => false, 'default_dose' => '1 tableta', 'default_frequency' => 'Cada 12 horas', 'default_duration' => 'Continuo', 'default_instructions' => 'Con los alimentos'],
            ['generic_name' => 'Losartán', 'presentation' => 'Tabletas', 'concentration' => '50 mg', 'route' => 'Oral', 'is_favorite' => false, 'default_dose' => '1 tableta', 'default_frequency' => 'Cada 24 horas', 'default_duration' => 'Continuo'],
        ];

        foreach ($medications as $medication) {
            Medication::query()->firstOrCreate(
                ['generic_name' => $medication['generic_name'], 'concentration' => $medication['concentration']],
                $medication,
            );
        }
    }
}
