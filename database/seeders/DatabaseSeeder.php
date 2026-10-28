<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Todos los seeders son idempotentes: se pueden ejecutar varias veces.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
            UserSeeder::class,
            ServiceSeeder::class,
            NoteTemplateSeeder::class,
            Cie10CodeSeeder::class,
            MedicationSeeder::class,
        ]);
    }
}
