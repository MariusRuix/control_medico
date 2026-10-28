<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $consultation_id
 * @property string $folio
 * @property string|null $general_instructions
 * @property string|null $pdf_path
 * @property string|null $signature_hash
 * @property Carbon|null $issued_at
 * @property int|null $created_by
 */
#[Fillable(['consultation_id', 'folio', 'general_instructions', 'pdf_path', 'signature_hash', 'issued_at', 'created_by'])]
class Prescription extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Consultation, $this> */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /** @return HasMany<PrescriptionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
