<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PatientHistoryType: string
{
    use HasOptions;

    case Allergy = 'alergia';
    case HeredoFamilial = 'heredo_familiar';
    case Pathological = 'patologico';
    case NonPathological = 'no_patologico';
    case Surgical = 'quirurgico';
    case GynecoObstetric = 'gineco_obstetrico';

    public function label(): string
    {
        return match ($this) {
            self::Allergy => 'Alergia',
            self::HeredoFamilial => 'Heredo-familiar',
            self::Pathological => 'Personal patológico',
            self::NonPathological => 'Personal no patológico',
            self::Surgical => 'Quirúrgico',
            self::GynecoObstetric => 'Gineco-obstétrico',
        };
    }
}
