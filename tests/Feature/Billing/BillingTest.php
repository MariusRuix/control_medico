<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\ChargeStatus;
use App\Enums\Role;
use App\Models\CashRegister;
use App\Models\Charge;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $recepcion;

    private Patient $patient;

    private Service $consulta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class, ServiceSeeder::class]);

        $this->recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);
        $this->patient = Patient::query()->create(['folio' => 'P-000001', 'first_name' => 'Ana', 'last_name' => 'López']);
        $this->consulta = Service::query()->where('name', 'Consulta subsecuente')->firstOrFail(); // $600
    }

    private function openCash(string $amount = '500'): void
    {
        $this->actingAs($this->recepcion)->post(route('cash.open'), ['opening_amount' => $amount])->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, mixed>
     */
    private function charge(array $overrides = []): array
    {
        return [
            'patient_id' => $this->patient->id,
            'items' => [
                ['service_id' => $this->consulta->id, 'description' => 'Consulta subsecuente', 'quantity' => 1, 'unit_price' => '600.00', 'discount' => '100.00'],
                ['service_id' => null, 'description' => 'Curación', 'quantity' => 2, 'unit_price' => '150.50'],
            ],
            'pay_now' => false,
            ...$overrides,
        ];
    }

    public function test_charge_totals_are_calculated_on_the_server(): void
    {
        $this->actingAs($this->recepcion)->post(route('charges.store'), $this->charge())->assertSessionHasNoErrors();

        $charge = Charge::query()->with('items')->firstOrFail();

        $this->assertSame('801.00', $charge->total); // 600 - 100 + 2 × 150.50
        $this->assertSame('801.00', $charge->balance);
        $this->assertSame(ChargeStatus::Pending, $charge->status);
        $this->assertSame('500.00', $charge->items[0]->subtotal);
    }

    public function test_paying_requires_an_open_cash_register(): void
    {
        $this->actingAs($this->recepcion)
            ->post(route('charges.store'), $this->charge(['pay_now' => true, 'payment' => ['amount' => '801', 'method' => 'efectivo']]))
            ->assertSessionHasErrors(['payment.amount' => 'Abre la caja antes de registrar pagos.']);

        $this->assertSame(0, Charge::query()->count());
    }

    public function test_partial_payments_update_balance_and_status(): void
    {
        $this->openCash();
        $this->post(route('charges.store'), $this->charge(['pay_now' => true, 'payment' => ['amount' => '300', 'method' => 'efectivo']]))
            ->assertSessionHasNoErrors();

        $charge = Charge::query()->firstOrFail();
        $this->assertSame(ChargeStatus::Partial, $charge->status);
        $this->assertSame('501.00', $charge->balance);

        $this->post(route('charges.pay', $charge), ['payment' => ['amount' => '600', 'method' => 'tarjeta']])
            ->assertSessionHasErrors(['payment.amount' => 'El monto excede el saldo pendiente ($501.00).']);

        $this->post(route('charges.pay', $charge), ['payment' => ['amount' => '501', 'method' => 'tarjeta', 'reference' => '1234']])
            ->assertSessionHasNoErrors();

        $charge->refresh();
        $this->assertSame(ChargeStatus::Paid, $charge->status);
        $this->assertSame('0.00', $charge->balance);
        $this->assertSame('801.00', $charge->paid);
    }

    public function test_cash_close_computes_expected_amounts_and_difference(): void
    {
        $this->openCash('500');
        $this->post(route('charges.store'), $this->charge(['pay_now' => true, 'payment' => ['amount' => '301', 'method' => 'efectivo']]));
        $charge = Charge::query()->firstOrFail();
        $this->post(route('charges.pay', $charge), ['payment' => ['amount' => '500', 'method' => 'transferencia']]);

        $register = CashRegister::query()->firstOrFail();

        $this->post(route('cash.close', $register), ['counted_cash' => '790', 'notes' => 'Faltan 11 pesos'])
            ->assertRedirect(route('cash.show', $register));

        $register->refresh();
        $this->assertSame('801.00', $register->expected_cash);      // 500 fondo + 301 efectivo
        $this->assertSame('500.00', $register->expected_transfer);
        $this->assertSame('0.00', $register->expected_card);
        $this->assertSame('-11.00', $register->difference);
        $this->assertFalse($register->isOpen());

        // Con la caja cerrada ya no se aceptan pagos.
        $this->post(route('charges.pay', $charge), ['payment' => ['amount' => '1', 'method' => 'efectivo']])
            ->assertSessionHasErrors('payment.amount');
    }

    public function test_only_one_register_can_be_open(): void
    {
        $this->openCash();

        $this->post(route('cash.open'), ['opening_amount' => '100'])
            ->assertSessionHasErrors(['opening_amount' => 'Ya hay una caja abierta.']);
    }

    public function test_voiding_a_payment_restores_the_balance_only_while_cash_is_open(): void
    {
        $this->openCash();
        $this->post(route('charges.store'), $this->charge(['pay_now' => true, 'payment' => ['amount' => '801', 'method' => 'efectivo']]));
        $payment = Payment::query()->firstOrFail();

        $this->delete(route('payments.destroy', $payment))->assertSessionHasErrors('reason');
        $this->delete(route('payments.destroy', $payment), ['reason' => 'Monto equivocado'])->assertSessionHasNoErrors();

        $charge = Charge::query()->firstOrFail();
        $this->assertSame(ChargeStatus::Pending, $charge->status);
        $this->assertSame('801.00', $charge->balance);
        $this->assertModelMissing($payment);
    }

    public function test_charges_with_payments_cannot_be_cancelled(): void
    {
        $this->openCash();
        $this->post(route('charges.store'), $this->charge(['pay_now' => true, 'payment' => ['amount' => '100', 'method' => 'efectivo']]));
        $charge = Charge::query()->firstOrFail();

        $this->post(route('charges.cancel', $charge), ['reason' => 'Error'])->assertSessionHasErrors('reason');

        $this->post(route('charges.store'), $this->charge());
        $unpaid = Charge::query()->latest('id')->firstOrFail();
        $this->post(route('charges.cancel', $unpaid), ['reason' => 'Se registró dos veces'])->assertSessionHasNoErrors();

        $this->assertSame(ChargeStatus::Cancelled, $unpaid->fresh()?->status);
        $this->assertSame('0.00', $unpaid->fresh()?->balance);
    }

    public function test_discount_cannot_exceed_item_amount(): void
    {
        $this->actingAs($this->recepcion)
            ->post(route('charges.store'), $this->charge(['items' => [
                ['description' => 'Consulta', 'quantity' => 1, 'unit_price' => '100', 'discount' => '150'],
            ]]))
            ->assertSessionHasErrors('items');
    }

    public function test_cash_page_shows_register_payments_and_outstanding_balances(): void
    {
        $this->openCash();
        $this->post(route('charges.store'), $this->charge(['pay_now' => true, 'payment' => ['amount' => '300', 'method' => 'efectivo']]));

        $this->get(route('cash.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('cash/Index')
                ->where('register.totals.efectivo', '300.00')
                ->where('register.expected_cash', '800.00')
                ->has('payments', 1)
                ->has('outstanding', 1)
                ->where('outstanding.0.balance', '501.00')
                ->where('can.manageCash', true));
    }

    public function test_patient_page_lists_charges_and_billing_options(): void
    {
        $this->actingAs($this->recepcion)->post(route('charges.store'), $this->charge());

        $this->get(route('patients.show', $this->patient))
            ->assertInertia(fn (Assert $page) => $page
                ->has('charges', 1)
                ->where('charges.0.status', 'pendiente')
                ->where('balance', '801.00')
                ->where('billing.cashOpen', false)
                ->has('billing.services'));
    }

    public function test_doctor_without_cash_permission_cannot_open_register(): void
    {
        // El médico tiene todos los permisos; un usuario sin rol no.
        $nobody = User::factory()->create();

        $this->actingAs($nobody)->get(route('cash.index'))->assertForbidden();
        $this->actingAs($nobody)->post(route('cash.open'), ['opening_amount' => '0'])->assertForbidden();
    }
}
