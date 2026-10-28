<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MedicalFileType: string
{
    use HasOptions;

    case Laboratory = 'laboratorio';
    case XRay = 'rayos_x';
    case Ultrasound = 'ultrasonido';
    case Tomography = 'tomografia';
    case Report = 'informe';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Laboratory => 'Laboratorio',
            self::XRay => 'Rayos X',
            self::Ultrasound => 'Ultrasonido',
            self::Tomography => 'Tomografía',
            self::Report => 'Informe médico',
            self::Other => 'Otro',
        };
    }
}
