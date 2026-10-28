<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de medicamentos + vademécum personal (is_favorite y posología predeterminada).
 *
 * @property int $id
 * @property string $generic_name
 * @property string|null $brand_name
 * @property string|null $presentation
 * @property string|null $concentration
 * @property string|null $route
 * @property bool $is_favorite
 * @property string|null $default_dose
 * @property string|null $default_frequency
 * @property string|null $default_duration
 * @property string|null $default_instructions
 * @property bool $is_active
 */
#[Fillable([
    'generic_name', 'brand_name', 'presentation', 'concentration', 'route',
    'is_favorite', 'default_dose', 'default_frequency', 'default_duration', 'default_instructions', 'is_active',
])]
class Medication extends Model
{
    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<Medication> $query */
    public function scopeFavorites(Builder $query): void
    {
        $query->where('is_favorite', true)->where('is_active', true);
    }

    /** @param Builder<Medication> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where('is_active', true)->where(function (Builder $q) use ($like): void {
            $q->where('generic_name', 'like', $like)->orWhere('brand_name', 'like', $like);
        });
    }

    /**
     * Nombre para imprimir en la receta.
     */
    public function displayName(): string
    {
        return trim(implode(' ', array_filter([
            $this->generic_name,
            $this->concentration,
            $this->presentation,
            $this->brand_name !== null ? "({$this->brand_name})" : null,
        ])));
    }
}
