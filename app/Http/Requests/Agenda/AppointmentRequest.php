<?php

declare(strict_types=1);

namespace App\Http\Requests\Agenda;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y reprogramación de citas. Los empalmes, bloqueos y horario de
 * atención se validan en AppointmentService (dentro de la transacción).
 */
class AppointmentRequest extends FormRequest
{
    /** Margen para registrar una cita que empieza "ahorita". */
    private const int PAST_GRACE_MINUTES = 15;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['allow_outside_hours' => $this->boolean('allow_outside_hours')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('is_appointable', true)->where('is_active', true)],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'allow_outside_hours' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $start = $this->startsAt();
                $current = $this->route('appointment');
                $isReschedule = $current instanceof Appointment && ! $current->starts_at->eq($start);

                if (($current === null || $isReschedule) && $start->lt(now()->subMinutes(self::PAST_GRACE_MINUTES))) {
                    $validator->errors()->add('time', 'No se pueden agendar citas en el pasado.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'patient_id' => 'paciente',
            'service_id' => 'tipo de consulta',
            'date' => 'fecha',
            'time' => 'hora',
            'duration_minutes' => 'duración',
            'reason' => 'motivo',
            'notes' => 'notas',
        ];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->input('date').' '.$this->input('time'))->second(0);
    }

    /**
     * @return array{patient_id: int, service_id: int|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable, reason: string|null, notes: string|null, allow_outside_hours: bool}
     */
    public function appointmentData(): array
    {
        $validated = $this->validated();
        $start = $this->startsAt();

        return [
            'patient_id' => (int) $validated['patient_id'],
            'service_id' => isset($validated['service_id']) ? (int) $validated['service_id'] : null,
            'starts_at' => $start,
            'ends_at' => $start->addMinutes((int) $validated['duration_minutes']),
            'reason' => $validated['reason'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'allow_outside_hours' => (bool) $validated['allow_outside_hours'],
        ];
    }
}
