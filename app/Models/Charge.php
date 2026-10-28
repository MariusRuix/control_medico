<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChargeStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta por cobrar. Los adeudos son los cargos con balance > 0.
 *
 * @property int $id
 * @property int $patient_id
 * @property int|null $consultation_id
 * @property string $total
 * @property string $paid
 * @property string $balance
 * @property ChargeStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 */
#[Fillable(['patient_id', 'consultation_id', 'total', 'paid', 'balance', 'status', 'notes', 'created_by'])]
class Charge extends Model
{
    use Auditable;

    protected $attributes = [
        'status' => 'pendiente',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'status' => ChargeStatus::class,
        ];
    }

    /** @param Builder<Charge> $query */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereIn('status', [ChargeStatus::Pending, ChargeStatus::Partial])->where('balance', '>', 0);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Consultation, $this> */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /** @return HasMany<ChargeItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ChargeItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
