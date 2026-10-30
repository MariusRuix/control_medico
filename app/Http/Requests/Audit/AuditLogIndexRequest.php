<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use App\Services\Audit\AuditCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogIndexRequest extends FormRequest
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
            'patient' => ['nullable', 'integer', 'min:1'],
            'user' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', 'string', Rule::in(array_keys(AuditCatalog::ACTIONS))],
            'type' => ['nullable', 'string', Rule::in(array_keys(AuditCatalog::TYPES))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array{patient: int|null, user: int|null, action: string|null, type: string|null, from: string|null, to: string|null}
     */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'patient' => isset($data['patient']) ? (int) $data['patient'] : null,
            'user' => isset($data['user']) ? (int) $data['user'] : null,
            'action' => $data['action'] ?? null,
            'type' => $data['type'] ?? null,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['from' => 'desde', 'to' => 'hasta'];
    }
}
