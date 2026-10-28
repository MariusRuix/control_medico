<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * Convierte un enum respaldado en opciones para un <select> del frontend.
 *
 * @mixin \BackedEnum
 */
trait HasOptions
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => (string) $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
            ],
            self::cases(),
        );
    }
}
