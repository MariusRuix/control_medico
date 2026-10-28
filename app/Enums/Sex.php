<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Sex: string
{
    use HasOptions;

    case Female = 'F';
    case Male = 'M';
    case Other = 'X';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Femenino',
            self::Male => 'Masculino',
            self::Other => 'Otro / No especificado',
        };
    }
}
