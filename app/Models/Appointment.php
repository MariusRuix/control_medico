<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $service_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property AppointmentStatus $status
 * @property string|null $reason
 * @property string|null $notes
 * @property string|null $cancel_reason
 * @property Carbon|null $reminder_sent_at
 * @property int|null $created_by
 */
#[Fillable([
    'patient_id', 'service_id', 'starts_at', 'ends_at', 'status', 'reason', 'notes',
    'cancel_reason', 'reminder_sent_at', 'created_by',
])]
class Appointment extends Model
{
    use Auditable;

    protected $attributes = [
        'status' => 'agendada',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * Citas que ocupan el horario indicado (para evitar empalmes).
     *
     * @param  Builder<Appointment>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): void
    {
        $query->whereIn('status', AppointmentStatus::blocking())
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId));
    }

    /** @param Builder<Appointment> $query */
    public function scopeBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('starts_at', '>=', $from)->where('starts_at', '<', $to);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return HasOne<Consultation, $this> */
    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
