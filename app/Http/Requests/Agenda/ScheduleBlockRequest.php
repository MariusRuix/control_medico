<?php

declare(strict_types=1);

namespace App\Http\Requests\Agenda;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScheduleBlockRequest extends FormRequest
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
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'starts_at' => 'inicio',
            'ends_at' => 'fin',
            'reason' => 'motivo',
        ];
    }

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, reason: string|null}
     */
    public function blockData(): array
    {
        return [
            'starts_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $this->validated('starts_at'))->second(0),
            'ends_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $this->validated('ends_at'))->second(0),
            'reason' => $this->validated('reason'),
        ];
    }
}
