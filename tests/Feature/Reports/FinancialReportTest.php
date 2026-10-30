<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Charge;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\Reports\ReportPeriod;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private User $medico;

    private User $recepcion;

    private Patient $ana;

    private Patient $luis;

    private Service $consulta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class, ServiceSeeder::class]);

        $this->medico = User::factory()->create()->assignRole(Role::Medico->value);
        $this->recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);
        $this->ana = Patient::query()->create(['folio' => 'P-000001', 'first_name' => 'Ana', 'last_name' => 'López']);
        $this->luis = Patient::query()->create(['folio' => 'P-000002', 'first_name' => 'Luis', 'last_name' => 'Soto', 'phone' => '5512345678']);
        $this->consulta = Service::query()->where('name', 'Consulta subsecuente')->firstOrFail();

        // Octubre: Ana paga completo (600 - 100 de descuento = 500) en efectivo y tarjeta.
        Carbon::setTestNow('2026-10-05 10:00');
        $this->actingAs($this->recepcion)->post(route('cash.open'), ['opening_amount' => '0']);
        $this->post(route('charges.store'), [
            'patient_id' => $this->ana->id,
            'items' => [['service_id' => $this->consulta->id, 'description' => 'Consulta subsecuente', 'quantity' => 1, 'unit_price' => '600.00', 'discount' => '100.00']],
            'pay_now' => true,
            'payment' => ['amount' => '300', 'method' => 'efectivo'],
        ])->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-10-20 12:00');
        $this->post(route('charges.pay', Charge::query()->firstOrFail()), ['payment' => ['amount' => '200', 'method' => 'tarjeta', 'reference' => '9876']])
            ->assertSessionHasNoErrors();

        // Luis: cargo de agosto que sigue sin pagar (concepto libre).
        Carbon::setTestNow('2026-08-10 09:00');
        $this->post(route('charges.store'), [
            'patient_id' => $this->luis->id,
            'items' => [['service_id' => null, 'description' => 'Curación', 'quantity' => 2, 'unit_price' => '150.00']],
            'pay_now' => false,
        ])->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-10-30 18:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reception_cannot_see_reports(): void
    {
        $this->actingAs($this->recepcion)->get(route('reports.finance'))->assertForbidden();
        $this->actingAs($this->recepcion)->get(route('reports.finance.export'))->assertForbidden();
    }

    public function test_summary_methods_services_and_trend_for_the_period(): void
    {
        $this->actingAs($this->medico)
            ->get(route('reports.finance', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/Finance')
                ->where('filters.group', 'day')
                ->where('previous.from', '2026-08-31')
                ->where('previous.to', '2026-09-30')
                ->where('report.summary.income', '500.00')
                ->where('report.summary.payments_count', 2)
                ->where('report.summary.billed', '500.00')
                ->where('report.summary.charges_count', 1)
                ->where('report.summary.discounts', '100.00')
                ->where('report.summary.change_pct', null)
                ->where('report.methods.0.amount', '300.00')
                ->where('report.methods.0.share', 60)
                ->where('report.methods.2.amount', '200.00')
                ->has('report.services', 1)
                ->where('report.services.0.name', 'Consulta subsecuente')
                ->where('report.services.0.amount', '500.00')
                ->has('report.trend', 31)
                ->where('report.trend.4.amount', '300.00')
                ->where('report.trend.19.amount', '200.00'));
    }

    public function test_grouping_by_month_and_comparison_with_previous_period(): void
    {
        $this->actingAs($this->medico)
            ->get(route('reports.finance', ['from' => '2026-08-01', 'to' => '2026-10-31', 'group' => 'month']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('report.trend', 3)
                ->where('report.trend.2.amount', '500.00')
                ->where('report.summary.billed', '800.00')
                ->where('report.services.1.name', 'Otros conceptos')
                ->where('report.services.1.quantity', '2'));

        $this->assertSame('week', ReportPeriod::make('2026-08-01', '2026-10-15', null)->group);
    }

    public function test_aging_of_outstanding_balances(): void
    {
        $this->actingAs($this->medico)
            ->get(route('reports.finance'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.aging.total', '300.00')
                ->where('report.aging.patients_count', 1)
                ->where('report.aging.buckets.2.key', 'd61')
                ->where('report.aging.buckets.2.amount', '300.00')
                ->where('report.aging.debtors.0.full_name', 'Luis Soto')
                ->where('report.aging.debtors.0.oldest_days', 81));
    }

    public function test_invalid_periods_are_rejected(): void
    {
        $this->actingAs($this->medico)
            ->get(route('reports.finance', ['from' => '2026-10-10', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('to');

        $this->actingAs($this->medico)
            ->get(route('reports.finance', ['from' => '2023-01-01', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_csv_exports(): void
    {
        $this->actingAs($this->medico);

        $payments = $this->get(route('reports.finance.export', ['dataset' => 'payments', 'from' => '2026-10-01', 'to' => '2026-10-31']));
        $payments->assertOk()->assertDownload('ingresos_2026-10-01_2026-10-31.csv');
        $csv = $payments->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('2026-10-05,10:00,P-000001,"Ana López","Consulta subsecuente",Efectivo,,300.00', $csv);
        $this->assertStringContainsString('Tarjeta,9876,200.00', $csv);

        $debts = $this->get(route('reports.finance.export', ['dataset' => 'debts']))->streamedContent();
        $this->assertStringContainsString('P-000002,"Luis Soto",5512345678,2026-08-10,81,Curación,300.00,0.00,300.00', $debts);

        $services = $this->get(route('reports.finance.export', ['dataset' => 'services', 'from' => '2026-10-01', 'to' => '2026-10-31']))->streamedContent();
        $this->assertStringContainsString('"Consulta subsecuente",1,500.00,100', $services);

        $this->assertSame(3, $this->countExports());
        $this->assertSame(2, Payment::query()->count());
    }

    private function countExports(): int
    {
        return AuditLog::query()->where('action', 'exported')->count();
    }
}
