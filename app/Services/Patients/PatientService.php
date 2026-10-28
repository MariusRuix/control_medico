<?php

declare(strict_types=1);

namespace App\Services\Patients;

use App\Enums\AppointmentStatus;
use App\Models\Patient;
use App\Services\FolioGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PatientService
{
    public function __construct(private readonly FolioGenerator $folios) {}

    /**
     * @param  array<string, mixed>  $data  Datos ya validados.
     */
    public function create(array $data): Patient
    {
        try {
            return DB::transaction(function () use ($data): Patient {
                $patient = new Patient($data);
                $patient->folio = $this->folios->nextPatientFolio();
                $patient->save();

                return $patient;
            });
        } catch (Throwable $e) {
            Log::error('No se pudo registrar el paciente.', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data  Datos ya validados.
     */
    public function update(Patient $patient, array $data): Patient
    {
        try {
            $patient->fill($data)->save();

            return $patient;
        } catch (Throwable $e) {
            Log::error('No se pudo actualizar el paciente.', ['patient_id' => $patient->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Archiva (soft delete) al paciente y cancela sus citas futuras.
     * El expediente se conserva: la NOM-004 exige guardarlo al menos 5 años.
     */
    public function archive(Patient $patient): void
    {
        try {
            DB::transaction(function () use ($patient): void {
                $patient->appointments()
                    ->where('starts_at', '>', now())
                    ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed])
                    ->get()
                    ->each(fn ($appointment) => $appointment->update([
                        'status' => AppointmentStatus::Cancelled,
                        'cancel_reason' => 'Paciente archivado',
                    ]));

                $patient->delete();
            });
        } catch (Throwable $e) {
            Log::error('No se pudo archivar el paciente.', ['patient_id' => $patient->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }
}
