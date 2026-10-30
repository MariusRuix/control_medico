<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Nota aclaratoria de una consulta firmada. Es de solo escritura: una vez
 * creada no se modifica ni se elimina.
 *
 * @property int $id
 * @property int $consultation_id
 * @property string $body
 * @property int|null $created_by
 */
#[Fillable(['consultation_id', 'body', 'created_by'])]
class ConsultationAddendum extends Model
{
    use Auditable;

    protected $table = 'consultation_addenda';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las notas aclaratorias no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Las notas aclaratorias no se pueden eliminar.'));
    }

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Consultation, $this> */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
