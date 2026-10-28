<?php

declare(strict_types=1);

namespace App\Http\Requests\Agenda;

use App\Services\Agenda\CalendarQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarRequest extends FormRequest
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
            'view' => ['nullable', Rule::in(CalendarQuery::VIEWS)],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function calendarView(): string
    {
        return (string) ($this->validated('view') ?? 'week');
    }

    public function targetDate(): CarbonImmutable
    {
        $date = $this->validated('date');

        return is_string($date) ? CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay() : CarbonImmutable::today();
    }
}
