<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Payment;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Exportación del reporte financiero a CSV (UTF-8 con BOM, se abre en Excel).
 */
final class FinancialReportExporter
{
    public const array DATASETS = ['payments', 'services', 'debts'];

    public function __construct(
        private readonly FinancialReport $report,
        private readonly AuditLogger $audit,
    ) {}

    public function download(string $dataset, ReportPeriod $period): StreamedResponse
    {
        $this->audit->log('exported', null, [], [
            'report' => 'financial',
            'dataset' => $dataset,
            'from' => $period->from->toDateString(),
            'to' => $period->to->toDateString(),
        ]);

        $suffix = $dataset === 'debts'
            ? Date::today()->toDateString()
            : $period->from->toDateString().'_'.$period->to->toDateString();

        $names = ['payments' => 'ingresos', 'services' => 'servicios', 'debts' => 'adeudos'];

        return response()->streamDownload(function () use ($dataset, $period): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                throw new RuntimeException('No se pudo abrir la salida del CSV.');
            }

            try {
                fwrite($out, "\xEF\xBB\xBF");

                match ($dataset) {
                    'payments' => $this->payments($out, $period),
                    'services' => $this->services($out, $period),
                    default => $this->debts($out),
                };
            } catch (Throwable $e) {
                Log::error('Falló la exportación del reporte financiero.', ['dataset' => $dataset, 'error' => $e->getMessage()]);

                throw $e;
            } finally {
                fclose($out);
            }
        }, "{$names[$dataset]}_{$suffix}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  resource  $out
     */
    private function payments($out, ReportPeriod $period): void
    {
        fputcsv($out, ['Fecha', 'Hora', 'Folio paciente', 'Paciente', 'Concepto', 'Método', 'Referencia', 'Monto', 'Recibió', 'Caja', 'Cargo'], escape: '');

        Payment::query()
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->with(['charge.patient:id,folio,first_name,last_name,second_last_name', 'charge.items:id,charge_id,description', 'receiver:id,name'])
            ->orderBy('paid_at')
            ->orderBy('id')
            ->chunk(500, function ($payments) use ($out): void {
                foreach ($payments as $p) {
                    /** @var Payment $p */
                    fputcsv($out, [
                        $p->paid_at->format('Y-m-d'),
                        $p->paid_at->format('H:i'),
                        $p->charge?->patient?->folio ?? '',
                        $p->charge?->patient?->full_name ?? '',
                        $p->charge?->items->pluck('description')->implode(', ') ?? '',
                        $p->method->label(),
                        $p->reference ?? '',
                        (string) $p->amount,
                        $p->receiver?->name ?? '',
                        $p->cash_register_id ?? '',
                        $p->charge_id,
                    ], escape: '');
                }
            });
    }

    /**
     * @param  resource  $out
     */
    private function services($out, ReportPeriod $period): void
    {
        fputcsv($out, ['Servicio', 'Cantidad', 'Importe facturado', '% del total'], escape: '');

        foreach ($this->report->byService($period) as $row) {
            fputcsv($out, [$row['name'], $row['quantity'], $row['amount'], $row['share']], escape: '');
        }
    }

    /**
     * @param  resource  $out
     */
    private function debts($out): void
    {
        fputcsv($out, ['Folio paciente', 'Paciente', 'Teléfono', 'Fecha del cargo', 'Días', 'Concepto', 'Total', 'Pagado', 'Saldo', 'Estatus', 'Cargo'], escape: '');

        $today = Date::today();

        Charge::query()
            ->outstanding()
            ->whereHas('patient')
            ->with(['patient:id,folio,first_name,last_name,second_last_name,phone', 'items:id,charge_id,description'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->chunk(500, function ($charges) use ($out, $today): void {
                foreach ($charges as $c) {
                    /** @var Charge $c */
                    fputcsv($out, [
                        $c->patient?->folio ?? '',
                        $c->patient?->full_name ?? '',
                        $c->patient?->phone ?? '',
                        $c->created_at?->format('Y-m-d') ?? '',
                        (int) $c->created_at?->copy()->startOfDay()->diffInDays($today),
                        $c->items->pluck('description')->implode(', '),
                        (string) $c->total,
                        (string) $c->paid,
                        (string) $c->balance,
                        $c->status instanceof ChargeStatus ? $c->status->label() : (string) $c->status,
                        $c->id,
                    ], escape: '');
                }
            });
    }
}
