<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ChargeRequest;
use App\Http\Requests\Billing\PaymentRequest;
use App\Http\Requests\Billing\ReasonRequest;
use App\Models\Charge;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\ChargeService;
use App\Services\Billing\PaymentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ChargeController extends Controller
{
    public function __construct(
        private readonly ChargeService $charges,
        private readonly PaymentService $payments,
    ) {}

    public function store(ChargeRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $patient = Patient::query()->findOrFail((int) $request->validated('patient_id'));

        $charge = $this->charges->create($patient, $request->chargeData(), $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $charge->balance === '0.00' ? 'Cobro registrado y pagado.' : 'Cobro registrado. Saldo pendiente: $'.$charge->balance,
        ]);

        return back();
    }

    public function pay(PaymentRequest $request, Charge $charge): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->payments->register(
            $charge,
            (string) $request->validated('payment.amount'),
            (string) $request->validated('payment.method'),
            $request->validated('payment.reference'),
            $user,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pago registrado.']);

        return back();
    }

    public function cancel(ReasonRequest $request, Charge $charge): RedirectResponse
    {
        $this->charges->cancel($charge, (string) $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cobro cancelado.']);

        return back();
    }

    public function voidPayment(ReasonRequest $request, Payment $payment): RedirectResponse
    {
        $this->payments->void($payment, (string) $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pago cancelado.']);

        return back();
    }
}
