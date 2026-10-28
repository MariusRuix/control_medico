<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Consulta de primera vez', 'price' => 800, 'is_appointable' => true, 'duration_minutes' => 45, 'color' => 'sky'],
            ['name' => 'Consulta subsecuente', 'price' => 600, 'is_appointable' => true, 'duration_minutes' => 30, 'color' => 'indigo'],
            ['name' => 'Revisión de estudios', 'price' => 300, 'is_appointable' => true, 'duration_minutes' => 15, 'color' => 'teal'],
            ['name' => 'Certificado médico', 'price' => 250, 'is_appointable' => false, 'duration_minutes' => null, 'color' => null],
        ];

        foreach ($services as $service) {
            Service::query()->firstOrCreate(['name' => $service['name']], $service);
        }
    }
}
