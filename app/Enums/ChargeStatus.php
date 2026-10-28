<?php

declare(strict_types=1);

namespace App\Enums;

enum ChargeStatus: string
{
    case Pending = 'pendiente';
    case Partial = 'parcial';
    case Paid = 'pagado';
    case Cancelled = 'cancelado';
}
