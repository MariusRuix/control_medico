<?php

declare(strict_types=1);

namespace App\Services\Clinical;

use App\Enums\AppointmentStatus;
use App\Enums\DiagnosisType;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Ciclo de vida de la nota médica: inicio (borrador) → guardado → firma.
 * Una nota firmada ya no se modifica (NOM-004-SSA3-2012).
 */
final class ConsultationService
{
    /**
     * Inicia (o reabre) la consulta de una cita y la pasa a "En consultorio".
     */
    public function startFromAppointment(Appointment $appointment, User $author): Consultation
    {
        $existing = $appointment->consultation()->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed, AppointmentStatus::InProgress], true)) {
            throw ValidationException::withMessages([
                'appointment' => "No se puede iniciar consulta de una cita {$appointment->status->label()}.",
            ]);
        }

        if ($appointment->patient()->doesntExist()) {
            throw ValidationException::withMessages(['appointment' => 'El paciente de esta cita está archivado.']);
        }

        try {
            return DB::transaction(function () use ($appointment, $author): Consultation {
                if ($appointment->status !== AppointmentStatus::InProgress) {
                    $appointment->update(['status' => AppointmentStatus::InProgress]);
                }

                return Consultation::query()->create([
                    'patient_id' => $appointment->patient_id,
                    'appointment_id' => $appointment->id,
                    'consulted_at' => now(),
                    'reason' => $appointment->reason,
                    'created_by' => $author->id,
                ]);
            });
        } catch (Throwable $e) {
            Log::error('No se pudo iniciar la consulta desde la cita.', ['appointment_id' => $appointment->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Consulta sin cita (paciente de paso). Si ya hay un borrador abierto, lo reutiliza.
     */
    public function startWalkIn(Patient $patient, User $author): Consultation
    {
        $draft = $patient->consultations()->whereNull('signed_at')->whereNull('appointment_id')->latest('consulted_at')->first();

        if ($draft !== null) {
            return $draft;
        }

        try {
            return Consultation::query()->create([
                'patient_id' => $patient->id,
                'consulted_at' => now(),
                'created_by' => $author->id,
            ]);
        } catch (Throwable $e) {
            Log::error('No se pudo iniciar la consulta.', ['patient_id' => $patient->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data  Datos validados (campos de la nota + diagnoses).
     */
    public function saveDraft(Consultation $consultation, array $data): Consultation
    {
        $this->assertEditable($consultation);

        try {
            return DB::transaction(function () use ($consultation, $data): Consultation {
                $this->fillNote($consultation, $data);
                $consultation->save();

                return $consultation;
            });
        } catch (Throwable $e) {
            Log::error('No se pudo guardar la consulta.', ['consultation_id' => $consultation->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Guarda y firma la nota. Cierra la cita asociada.
     *
     * @param  array<string, mixed>  $data
     */
    public function sign(Consultation $consultation, array $data): Consultation
    {
        $this->assertEditable($consultation);

        try {
            return DB::transaction(function () use ($consultation, $data): Consultation {
                $this->fillNote($consultation, $data);
                $consultation->signed_at = now();
                $consultation->save();

                $appointment = $consultation->appointment;

                if ($appointment !== null && $appointment->status->canTransitionTo(AppointmentStatus::Completed)) {
                    $appointment->update(['status' => AppointmentStatus::Completed]);
                }

                return $consultation;
            });
        } catch (Throwable $e) {
            Log::error('No se pudo firmar la consulta.', ['consultation_id' => $consultation->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Descarta un borrador (soft delete). Las notas firmadas no se pueden descartar.
     */
    public function discard(Consultation $consultation): void
    {
        $this->assertEditable($consultation);

        try {
            DB::transaction(function () use ($consultation): void {
                $appointment = $consultation->appointment;

                $consultation->diagnoses()->delete();
                // Libera la cita (appointment_id es único) para poder reiniciar la consulta.
                $consultation->appointment_id = null;
                $consultation->save();
                $consultation->delete();

                // La cita regresa a "Confirmada" para poder reiniciar la consulta.
                if ($appointment !== null && $appointment->status === AppointmentStatus::InProgress) {
                    $appointment->update(['status' => AppointmentStatus::Confirmed]);
                }
            });
        } catch (Throwable $e) {
            Log::error('No se pudo descartar la consulta.', ['consultation_id' => $consultation->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    private function assertEditable(Consultation $consultation): void
    {
        if ($consultation->isSigned()) {
            throw ValidationException::withMessages([
                'consultation' => 'La nota ya está firmada y no se puede modificar.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillNote(Consultation $consultation, array $data): void
    {
        $diagnoses = $data['diagnoses'] ?? [];
        unset($data['diagnoses']);

        $consultation->fill($data);

        if (! is_array($diagnoses)) {
            return;
        }

        $consultation->diagnoses()->delete();

        foreach (array_values($diagnoses) as $diagnosis) {
            $consultation->diagnoses()->create([
                'cie10_code_id' => (int) $diagnosis['cie10_code_id'],
                'type' => DiagnosisType::from((string) $diagnosis['type']),
                'notes' => $diagnosis['notes'] ?? null,
            ]);
        }
    }
}
