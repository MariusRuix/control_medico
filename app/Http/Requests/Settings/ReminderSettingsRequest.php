<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Services\Reminders\ReminderService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReminderSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['enabled' => $this->boolean('enabled')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'hours_before' => ['required', 'integer', Rule::in(ReminderService::HOURS_OPTIONS)],
            'channel' => ['required', Rule::in(ReminderService::CHANNELS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['hours_before' => 'anticipación', 'channel' => 'canal'];
    }
}
