<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Services\FolioGenerator;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAndFoliosTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_read_as_nested_groups(): void
    {
        $this->seed(SettingsSeeder::class);
        $settings = app(SettingsService::class);

        $settings->set('clinic.name', 'Consultorio Prueba');

        $this->assertSame('Consultorio Prueba', $settings->get('clinic.name'));
        $this->assertSame('Consultorio Prueba', $settings->group('clinic')['name']);
        $this->assertSame([['09:00', '13:00']], $settings->get('schedule.working_hours.sat'));
    }

    public function test_folios_are_consecutive(): void
    {
        $this->seed(SettingsSeeder::class);
        $folios = app(FolioGenerator::class);

        $this->assertSame('P-000001', $folios->nextPatientFolio());
        $this->assertSame('P-000002', $folios->nextPatientFolio());
        $this->assertSame('R-000001', $folios->nextPrescriptionFolio());
    }
}
