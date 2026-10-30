<?php

declare(strict_types=1);

namespace App\Services\Patients;

use App\Enums\ChargeStatus;
use App\Enums\Permission;
use App\Http\Resources\MedicalFileResource;
use App\Http\Resources\PatientHistoryResource;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\ConsultationDiagnosis;
use App\Models\Patient;
use App\Models\User;
use App\Services\Billing\BillingQuery;

/**
 * Arma los datos de la vista de detalle del paciente según los permisos
 * del usuario: recepción no recibe información clínica.
 */
final class PatientProfileQuery
{
    private const int RECENT_LIMIT = 20;

    public function __construct(private readonly BillingQuery $billing) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(Patient $patient, User $user): array
    {
        $canSeeClinical = $user->can(Permission::ConsultationsView->value);

        return [
            'histories' => $canSeeClinical ? $this->histories($patient) : null,
            'criticalAllergies' => $this->criticalAllergies($patient),
            'consultations' => $canSeeClinical ? $this->consultations($patient) : null,
            'appointments' => $user->can(Permission::AppointmentsView->value) ? $this->appointments($patient) : null,
            'balance' => $user->can(Permission::ChargesView->value) ? $this->balance($patient) : null,
            'charges' => $user->can(Permission::ChargesView->value) ? $this->billing->patientCharges($patient->id) : null,
            'files' => $user->can(Permission::MedicalFilesView->value) || $user->can(Permission::MedicalFilesUpload->value)
                ? $this->files($patient)
                : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function histories(Patient $patient): array
    {
        $histories = $patient->histories()->latest()->get();

        return PatientHistoryResource::collection($histories)->resolve();
    }

    /**
     * Las alergias críticas se muestran a todo el personal como alerta de seguridad.
     *
     * @return list<string>
     */
    private function criticalAllergies(Patient $patient): array
    {
        return $patient->criticalAllergies()
            ->get()
            ->map(fn ($history): string => $history->description)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function consultations(Patient $patient): array
    {
        return $patient->consultations()
            ->with('diagnoses.code:id,code,description')
            ->latest('consulted_at')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (Consultation $c): array => [
                'id' => $c->id,
                'consulted_at' => $c->consulted_at->toIso8601String(),
                'reason' => $c->reason,
                'weight_kg' => $c->weight_kg,
                'bmi' => $c->bmi,
                'blood_pressure' => $c->systolic_bp !== null && $c->diastolic_bp !== null
                    ? "{$c->systolic_bp}/{$c->diastolic_bp}"
                    : null,
                'is_signed' => $c->isSigned(),
                'diagnoses' => $c->diagnoses->map(fn (ConsultationDiagnosis $d): array => [
                    'code' => $d->code->code,
                    'description' => $d->code->description,
                    'type' => $d->type->value,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function appointments(Patient $patient): array
    {
        return $patient->appointments()
            ->with('service:id,name')
            ->latest('starts_at')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (Appointment $a): array => [
                'id' => $a->id,
                'starts_at' => $a->starts_at->toIso8601String(),
                'service' => $a->service?->name,
                'reason' => $a->reason,
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'status_color' => $a->status->color(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function files(Patient $patient): array
    {
        return MedicalFileResource::collection(
            $patient->medicalFiles()->with('uploader:id,name')->orderByDesc('study_date')->latest()->get(),
        )->resolve();
    }

    private function balance(Patient $patient): string
    {
        $total = $patient->charges()
            ->whereIn('status', [ChargeStatus::Pending, ChargeStatus::Partial])
            ->sum('balance');

        return number_format((float) $total, 2, '.', '');
    }
}
