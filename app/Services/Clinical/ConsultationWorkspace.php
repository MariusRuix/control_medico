<?php

declare(strict_types=1);

namespace App\Services\Clinical;

use App\Enums\PatientHistoryType;
use App\Http\Resources\MedicationOptionResource;
use App\Http\Resources\PatientHistoryResource;
use App\Models\Consultation;
use App\Models\ConsultationDiagnosis;
use App\Models\Medication;
use App\Models\NoteTemplate;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Services\Prescriptions\PrescriberProfile;

/**
 * Datos para la pantalla de consulta: la nota, el contexto clínico del
 * paciente y la evolución de sus signos vitales.
 */
final class ConsultationWorkspace
{
    private const int HISTORY_LIMIT = 5;

    private const int TREND_LIMIT = 8;

    public function __construct(private readonly PrescriberProfile $prescriber) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Consultation $consultation, bool $editable): array
    {
        $consultation->loadMissing(['diagnoses.code', 'appointment.service', 'author:id,name']);

        /** @var Patient $patient */
        $patient = Patient::withTrashed()->findOrFail($consultation->patient_id);

        return [
            'consultation' => $this->consultation($consultation),
            'patient' => [
                'id' => $patient->id,
                'folio' => $patient->folio,
                'full_name' => $patient->full_name,
                'age' => $patient->age,
                'sex_label' => $patient->sex?->label(),
                'blood_type' => $patient->blood_type?->value,
                'is_archived' => $patient->trashed(),
                'has_email' => filled($patient->email),
            ],
            'criticalAllergies' => $patient->criticalAllergies()->get()->map(fn ($h): string => $h->description)->values()->all(),
            'histories' => PatientHistoryResource::collection($patient->histories()->latest()->get())->resolve(),
            'historyTypes' => PatientHistoryType::options(),
            'previous' => $this->previous($patient, $consultation),
            'trend' => $this->trend($patient, $consultation),
            'prescriptions' => $this->prescriptions($consultation),
            'favorites' => MedicationOptionResource::collection(
                Medication::query()->favorites()->orderBy('generic_name')->get(),
            )->resolve(),
            'prescriberMissing' => $this->prescriber->missing(),
            'templates' => $editable
                ? NoteTemplate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'structure'])
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consultation(Consultation $c): array
    {
        return [
            'id' => $c->id,
            'consulted_at' => $c->consulted_at->format('Y-m-d\TH:i:s'),
            'signed_at' => $c->signed_at?->format('Y-m-d\TH:i:s'),
            'author' => $c->author?->name,
            'note_template_id' => $c->note_template_id,
            'appointment' => $c->appointment !== null ? [
                'id' => $c->appointment->id,
                'starts_at' => $c->appointment->starts_at->format('Y-m-d\TH:i:s'),
                'service' => $c->appointment->service?->name,
            ] : null,
            'weight_kg' => $c->weight_kg,
            'height_cm' => $c->height_cm,
            'bmi' => $c->bmi,
            'systolic_bp' => $c->systolic_bp,
            'diastolic_bp' => $c->diastolic_bp,
            'heart_rate' => $c->heart_rate,
            'respiratory_rate' => $c->respiratory_rate,
            'temperature_c' => $c->temperature_c,
            'oxygen_saturation' => $c->oxygen_saturation,
            'glucose_mg_dl' => $c->glucose_mg_dl,
            'reason' => $c->reason,
            'subjective' => $c->subjective,
            'objective' => $c->objective,
            'assessment' => $c->assessment,
            'plan' => $c->plan,
            'diagnoses' => $c->diagnoses->map(fn (ConsultationDiagnosis $d): array => [
                'cie10_code_id' => $d->cie10_code_id,
                'code' => $d->code->code,
                'description' => $d->code->description,
                'type' => $d->type->value,
                'notes' => $d->notes,
            ])->values()->all(),
        ];
    }

    /**
     * Recetas emitidas en esta consulta (las anuladas no se muestran).
     *
     * @return list<array<string, mixed>>
     */
    private function prescriptions(Consultation $consultation): array
    {
        return $consultation->prescriptions()
            ->with('items')
            ->latest('issued_at')
            ->get()
            ->map(fn (Prescription $p): array => [
                'id' => $p->id,
                'folio' => $p->folio,
                'issued_at' => $p->issued_at?->format('Y-m-d\TH:i:s'),
                'general_instructions' => $p->general_instructions,
                'items' => $p->items->map(fn (PrescriptionItem $i): array => [
                    'medication_name' => $i->medication_name,
                    'dose' => $i->dose,
                    'route' => $i->route,
                    'frequency' => $i->frequency,
                    'duration' => $i->duration,
                    'quantity' => $i->quantity,
                    'instructions' => $i->instructions,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Últimas notas firmadas, para tener el contexto a la mano.
     *
     * @return list<array<string, mixed>>
     */
    private function previous(Patient $patient, Consultation $current): array
    {
        return $patient->consultations()
            ->whereKeyNot($current->id)
            ->whereNotNull('signed_at')
            ->where('consulted_at', '<=', $current->consulted_at)
            ->with('diagnoses.code:id,code,description')
            ->latest('consulted_at')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->map(fn (Consultation $c): array => [
                'id' => $c->id,
                'consulted_at' => $c->consulted_at->format('Y-m-d\TH:i:s'),
                'reason' => $c->reason,
                'assessment' => $c->assessment,
                'plan' => $c->plan,
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
     * Evolución de signos vitales (de la más antigua a la más reciente, sin la actual).
     *
     * @return list<array<string, mixed>>
     */
    private function trend(Patient $patient, Consultation $current): array
    {
        return $patient->consultations()
            ->whereKeyNot($current->id)
            ->where('consulted_at', '<=', $current->consulted_at)
            ->where(fn ($q) => $q->whereNotNull('weight_kg')->orWhereNotNull('systolic_bp')->orWhereNotNull('glucose_mg_dl'))
            ->latest('consulted_at')
            ->limit(self::TREND_LIMIT)
            ->get(['id', 'consulted_at', 'weight_kg', 'bmi', 'systolic_bp', 'diastolic_bp', 'heart_rate', 'glucose_mg_dl'])
            ->reverse()
            ->map(fn (Consultation $c): array => [
                'date' => $c->consulted_at->toDateString(),
                'weight_kg' => $c->weight_kg,
                'bmi' => $c->bmi,
                'systolic_bp' => $c->systolic_bp,
                'diastolic_bp' => $c->diastolic_bp,
                'heart_rate' => $c->heart_rate,
                'glucose_mg_dl' => $c->glucose_mg_dl,
            ])
            ->values()
            ->all();
    }
}
