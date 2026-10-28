<?php

declare(strict_types=1);

namespace App\Http\Requests\Agenda;

use App\Enums\AppointmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
            'cancel_reason' => ['nullable', 'required_if:status,'.AppointmentStatus::Cancelled->value, 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancel_reason.required_if' => 'Indica el motivo de la cancelación.',
        ];
    }

    public function newStatus(): AppointmentStatus
    {
        return AppointmentStatus::from((string) $this->validated('status'));
    }
}
