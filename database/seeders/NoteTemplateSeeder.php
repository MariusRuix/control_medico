<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\NoteTemplate;
use Illuminate\Database\Seeder;

class NoteTemplateSeeder extends Seeder
{
    public function run(): void
    {
        NoteTemplate::query()->firstOrCreate(
            ['name' => 'Consulta general'],
            [
                'structure' => [
                    'subjective' => "Padecimiento actual:\nInicio:\nEvolución:\nSíntomas asociados:",
                    'objective' => "Exploración física:\nCabeza y cuello:\nTórax:\nAbdomen:\nExtremidades:",
                    'assessment' => '',
                    'plan' => "Tratamiento:\nEstudios solicitados:\nPróxima cita:",
                ],
                'is_active' => true,
            ],
        );
    }
}
