<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CashRegisterRequest;
use App\Models\CashRegister;
use App\Models\User;
use App\Services\Billing\BillingOptions;
use App\Services\Billing\BillingQuery;
use App\Services\Billing\CashRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashController extends Controller
{
    public function __construct(
        private readonly CashRegisterService $cash,
        private readonly BillingQuery $billing,
        private readonly BillingOptions $options,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $register = $this->cash->current()?->load(['opener:id,name']);

        return Inertia::render('cash/Index', [
            'register' => $register !== null ? $this->cash->summary($register) : null,
            'payments' => $register !== null ? $this->billing->payments($register) : [],
            'outstanding' => $this->billing->outstanding(),
            'history' => $this->billing->history($this->cash),
            'billing' => $this->options->toArray(),
            'can' => [
                'manageCash' => $user->can(Permission::CashRegisterManage->value),
                'charge' => $user->can(Permission::ChargesManage->value),
            ],
        ]);
    }

    public function show(CashRegister $register): Response
    {
        $register->load(['opener:id,name', 'closer:id,name']);

        return Inertia::render('cash/Show', [
            'register' => $this->cash->summary($register),
            'payments' => $this->billing->payments($register),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function open(CashRegisterRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->cash->open((string) $request->validated('opening_amount'), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Caja abierta.']);

        return to_route('cash.index');
    }

    public function close(CashRegisterRequest $request, CashRegister $register): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->cash->close($register, (string) $request->validated('counted_cash'), $request->validated('notes'), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Corte de caja realizado.']);

        return to_route('cash.show', $register);
    }
}
