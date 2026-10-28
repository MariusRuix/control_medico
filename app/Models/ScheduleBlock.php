<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Días festivos, vacaciones o bloqueos de agenda.
 *
 * @property int $id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $reason
 */
#[Fillable(['starts_at', 'ends_at', 'reason'])]
class ScheduleBlock extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @param Builder<ScheduleBlock> $query */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }
}
