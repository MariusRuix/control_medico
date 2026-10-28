<?php

declare(strict_types=1);

namespace App\Services\Patients;

use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PatientHistoryService
{
    /**
     * @param  array<string, mixed>  $data  Datos ya validados.
     */
    public function create(Patient $patient, array $data, User $author): PatientHistory
    {
        try {
            return $patient->histories()->create([
                ...$data,
                'created_by' => $author->id,
            ]);
        } catch (Throwable $e) {
            Log::error('No se pudo registrar el antecedente.', ['patient_id' => $patient->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data  Datos ya validados.
     */
    public function update(PatientHistory $history, array $data): PatientHistory
    {
        try {
            $history->fill($data)->save();

            return $history;
        } catch (Throwable $e) {
            Log::error('No se pudo actualizar el antecedente.', ['history_id' => $history->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    public function delete(PatientHistory $history): void
    {
        try {
            $history->delete();
        } catch (Throwable $e) {
            Log::error('No se pudo eliminar el antecedente.', ['history_id' => $history->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }
}
