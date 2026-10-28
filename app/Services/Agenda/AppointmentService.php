<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AppointmentService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  array{patient_id: int, service_id?: int|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable, reason?: string|null, notes?: string|null, allow_outside_hours?: bool}  $data
     */
    public function create(array $data, User $author): Appointment
    {
        return $this->persist(new Appointment([
            'status' => AppointmentStatus::Scheduled,
            'created_by' => $author->id,
        ]), $data);
    }

    /**
     * @param  array{patient_id: int, service_id?: int|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable, reason?: string|null, notes?: string|null, allow_outside_hours?: bool}  $data
     */
    public function update(Appointment $appointment, array $data): Appointment
    {
        if ($appointment->status->isFinal()) {
            throw ValidationException::withMessages([
                'starts_at' => "No se puede modificar una cita {$appointment->status->label()}.",
            ]);
        }

        return $this->persist($appointment, $data);
    }

    public function changeStatus(Appointment $appointment, AppointmentStatus $status, ?string $cancelReason = null): Appointment
    {
        if (! $appointment->status->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => "No se puede pasar de {$appointment->status->label()} a {$status->label()}.",
            ]);
        }

        try {
            $appointment->update([
                'status' => $status,
                'cancel_reason' => $status === AppointmentStatus::Cancelled ? $cancelReason : $appointment->cancel_reason,
            ]);

            return $appointment;
        } catch (Throwable $e) {
            Log::error('No se pudo cambiar el estatus de la cita.', [
                'appointment_id' => $appointment->id,
                'status' => $status->value,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array{patient_id: int, service_id?: int|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable, reason?: string|null, notes?: string|null, allow_outside_hours?: bool}  $data
     */
    private function persist(Appointment $appointment, array $data): Appointment
    {
        try {
            return DB::transaction(function () use ($appointment, $data): Appointment {
                // Se vuelve a validar dentro de la transacción (con bloqueo) para evitar
                // que dos personas agenden el mismo horario al mismo tiempo.
                $conflict = $this->availability->conflict(
                    $data['starts_at'],
                    $data['ends_at'],
                    $appointment->exists ? $appointment->id : null,
                    $data['allow_outside_hours'] ?? false,
                    lock: true,
                );

                if ($conflict !== null) {
                    throw ValidationException::withMessages(['starts_at' => $conflict]);
                }

                $appointment->fill([
                    'patient_id' => $data['patient_id'],
                    'service_id' => $data['service_id'] ?? null,
                    'starts_at' => $data['starts_at'],
                    'ends_at' => $data['ends_at'],
                    'reason' => $data['reason'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                if ($appointment->isDirty('starts_at')) {
                    $appointment->reminder_sent_at = null;
                }

                $appointment->save();

                return $appointment;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('No se pudo guardar la cita.', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
