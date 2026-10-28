<?php

declare(strict_types=1);

namespace App\Enums;

enum DiagnosisType: string
{
    case Primary = 'principal';
    case Secondary = 'secundario';
}
