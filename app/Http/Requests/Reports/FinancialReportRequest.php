<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Services\Reports\FinancialReportExporter;
use App\Services\Reports\ReportPeriod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class FinancialReportRequest extends FormRequest
{
    /** Máximo de días por consulta (para que el reporte siga siendo rápido). */
    private const int MAX_DAYS = 731;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group' => ['nullable', Rule::in(ReportPeriod::GROUPS)],
            'dataset' => ['nullable', Rule::in(FinancialReportExporter::DATASETS)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $from = $this->input('from');
                $to = $this->input('to');

                if (is_string($from) && is_string($to) && $validator->errors()->isEmpty()
                    && Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1 > self::MAX_DAYS) {
                    $validator->errors()->add('to', 'El periodo no puede ser mayor a dos años.');
                }
            },
        ];
    }

    public function period(): ReportPeriod
    {
        /** @var string|null $from */
        $from = $this->validated('from');
        /** @var string|null $to */
        $to = $this->validated('to');
        /** @var string|null $group */
        $group = $this->validated('group');

        return ReportPeriod::make($from, $to, $group);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['from' => 'desde', 'to' => 'hasta'];
    }
}
