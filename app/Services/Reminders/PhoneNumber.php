<?php

declare(strict_types=1);

namespace App\Services\Reminders;

/**
 * Normaliza teléfonos al formato internacional sin "+" que usan WhatsApp y wa.me.
 * "55 1234 5678" → "525512345678".
 */
final class PhoneNumber
{
    public static function toInternational(?string $phone, string $defaultCountryCode = '52'): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            return $defaultCountryCode.$digits;
        }

        // 52 1 XXXXXXXXXX (formato móvil antiguo de México) → 52 XXXXXXXXXX
        if (strlen($digits) === 13 && str_starts_with($digits, '521')) {
            return '52'.substr($digits, 3);
        }

        return strlen($digits) >= 11 && strlen($digits) <= 15 ? $digits : null;
    }
}
