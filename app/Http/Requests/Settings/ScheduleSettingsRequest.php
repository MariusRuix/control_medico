<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Services\Agenda\WorkingHours;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Horario de atención: por día, hasta 4 rangos "HH:MM–HH:MM" sin empalmes.
 */
class ScheduleSettingsRequest extends FormRequest
{
    public const array SLOT_OPTIONS = [10, 15, 20, 30, 45, 60];

    private const int MAX_RANGES = 4;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'slot_minutes' => ['required', 'integer', 'in:'.implode(',', self::SLOT_OPTIONS)],
            'working_hours' => ['required', 'array'],
        ];

        foreach (WorkingHours::DAYS as $day) {
            $rules["working_hours.{$day}"] = ['present', 'array', 'max:'.self::MAX_RANGES];
            $rules["working_hours.{$day}.*"] = ['array', 'size:2'];
            $rules["working_hours.{$day}.*.*"] = ['required', 'date_format:H:i'];
        }

        return $rules;
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

                foreach (WorkingHours::DAYS as $day) {
                    $ranges = $this->input("working_hours.{$day}", []);
                    usort($ranges, fn (array $a, array $b): int => strcmp($a[0], $b[0]));

                    foreach ($ranges as $i => [$from, $to]) {
                        if ($from >= $to) {
                            $validator->errors()->add("working_hours.{$day}", "{$from}–{$to}: la hora de cierre debe ser posterior a la de apertura.");
                        }

                        if ($i > 0 && $from < $ranges[$i - 1][1]) {
                            $validator->errors()->add("working_hours.{$day}", 'Los horarios del día se empalman.');
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, list<array{0: string, 1: string}>>
     */
    public function workingHours(): array
    {
        $hours = [];

        foreach (WorkingHours::DAYS as $day) {
            $ranges = array_map(
                static fn (array $r): array => [(string) $r[0], (string) $r[1]],
                (array) $this->validated("working_hours.{$day}", []),
            );
            usort($ranges, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));
            $hours[$day] = array_values($ranges);
        }

        return $hours;
    }
}
