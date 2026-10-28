<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Consultas, procedimientos e insumos que se cobran. Los marcados como
 * is_appointable se pueden agendar y definen la duración y color de la cita.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $price
 * @property bool $is_appointable
 * @property int|null $duration_minutes
 * @property string|null $color
 * @property bool $is_active
 */
#[Fillable(['name', 'description', 'price', 'is_appointable', 'duration_minutes', 'color', 'is_active'])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_appointable' => 'boolean',
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<Service> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Service> $query */
    public function scopeAppointable(Builder $query): void
    {
        $query->where('is_appointable', true)->where('is_active', true);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
