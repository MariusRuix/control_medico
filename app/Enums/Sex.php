<?php

declare(strict_types=1);

namespace App\Enums;

enum Sex: string
{
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
