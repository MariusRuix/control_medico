<?php

declare(strict_types=1);

namespace App\Http\Requests\Agenda;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'ignore' => ['nullable', 'integer'],
        ];
    }

    public function targetDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', (string) $this->validated('date'))->startOfDay();
    }
}
