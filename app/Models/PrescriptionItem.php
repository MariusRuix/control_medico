<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $prescription_id
 * @property int|null $medication_id
 * @property string $medication_name
 * @property string|null $dose
 * @property string|null $route
 * @property string|null $frequency
 * @property string|null $duration
 * @property string|null $quantity
 * @property string|null $instructions
 * @property int $sort_order
 */
#[Fillable([
    'prescription_id', 'medication_id', 'medication_name', 'dose', 'route',
    'frequency', 'duration', 'quantity', 'instructions', 'sort_order',
])]
class PrescriptionItem extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Prescription, $this> */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /** @return BelongsTo<Medication, $this> */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }
}
