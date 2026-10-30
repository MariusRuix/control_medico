<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Operaciones con dinero en centavos (enteros) para evitar errores de
 * redondeo de punto flotante. En la base de datos se guardan como DECIMAL(10,2).
 */
final class Money
{
    public static function toCents(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
