<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Corte de caja.
 *
 * @property int $id
 * @property int $opened_by
 * @property int|null $closed_by
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property string $opening_amount
 * @property string $expected_cash
 * @property string $expected_transfer
 * @property string $expected_card
 * @property string|null $counted_cash
 * @property string|null $difference
 * @property string|null $notes
 */
#[Fillable([
    'opened_by', 'closed_by', 'opened_at', 'closed_at', 'opening_amount', 'expected_cash',
    'expected_transfer', 'expected_card', 'counted_cash', 'difference', 'notes',
])]
class CashRegister extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'expected_transfer' => 'decimal:2',
            'expected_card' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /** @param Builder<CashRegister> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
