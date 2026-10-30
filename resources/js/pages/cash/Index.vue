<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Ban, Lock, LockOpen, Plus, Wallet } from '@lucide/vue';
import { computed, ref } from 'vue';
import CashController from '@/actions/App/Http/Controllers/Billing/CashController';
import ChargeController from '@/actions/App/Http/Controllers/Billing/ChargeController';
import ChargeDialog from '@/components/billing/ChargeDialog.vue';
import ChargesList from '@/components/billing/ChargesList.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ReasonDialog from '@/components/ReasonDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { toCents } from '@/lib/money';
import { index, show } from '@/routes/cash';
import { show as patientShow } from '@/routes/patients';
import type {
    BillingOptions,
    CashSummary,
    ChargeSummary,
    PaymentRow,
} from '@/types';

const props = defineProps<{
    register: CashSummary | null;
    payments: PaymentRow[];
    outstanding: ChargeSummary[];
    history: CashSummary[];
    billing: BillingOptions;
    can: { manageCash: boolean; charge: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Caja', href: index() }],
    },
});

const chargeOpen = ref(false);
const closeOpen = ref(false);
const countedCash = ref('');

const outstandingTotal = computed(
    () =>
        props.outstanding.reduce((sum, c) => sum + toCents(c.balance), 0) / 100,
);

const closeDifference = computed(() => {
    if (!props.register || countedCash.value === '') {
        return null;
    }

    return (
        (toCents(countedCash.value) - toCents(props.register.expected_cash)) /
        100
    );
});

const cards = computed(() => {
    const r = props.register;

    if (!r) {
        return [];
    }

    return [
        { label: 'Efectivo', value: r.totals.efectivo },
        { label: 'Transferencia', value: r.totals.transferencia },
        { label: 'Tarjeta', value: r.totals.tarjeta },
        { label: 'Total cobrado', value: r.total_collected, strong: true },
    ];
});

// Cancelar pago
const voiding = ref<PaymentRow | null>(null);
const voidOpen = ref(false);
const voidError = ref<string | undefined>();

function askVoid(payment: PaymentRow): void {
    voiding.value = payment;
    voidError.value = undefined;
    voidOpen.value = true;
}

function voidPayment(reason: string): void {
    if (!voiding.value) {
        return;
    }

    router.delete(ChargeController.voidPayment.url(voiding.value.id), {
        data: { reason },
        preserveScroll: true,
        onSuccess: () => (voidOpen.value = false),
        onError: (e) => (voidError.value = e.reason),
    });
}
</script>

<template>
    <Head title="Caja" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Caja"
                :description="
                    register
                        ? `Abierta desde ${formatDateTime(register.opened_at)} por ${register.opened_by ?? '—'}`
                        : 'La caja está cerrada'
                "
            />
            <div class="flex flex-wrap gap-2">
                <Button v-if="can.charge" @click="chargeOpen = true"
                    ><Plus /> Nuevo cobro</Button
                >
                <Button
                    v-if="register && can.manageCash"
                    variant="outline"
                    @click="closeOpen = true"
                >
                    <Lock /> Cerrar caja
                </Button>
            </div>
        </div>

        <!-- Abrir caja -->
        <section
            v-if="!register && can.manageCash"
            class="max-w-md space-y-3 rounded-xl border p-5"
        >
            <h2 class="flex items-center gap-2 text-base font-medium">
                <LockOpen class="size-4" /> Abrir caja
            </h2>
            <p class="text-sm text-muted-foreground">
                Indica el efectivo con el que inicia la caja (fondo para dar
                cambio).
            </p>
            <Form
                v-bind="CashController.open.form()"
                class="flex items-start gap-2"
                v-slot="{ errors, processing }"
            >
                <div class="grid flex-1 gap-1">
                    <Input
                        name="opening_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        default-value="0"
                        aria-label="Fondo inicial"
                    />
                    <InputError :message="errors.opening_amount" />
                </div>
                <Button type="submit" :disabled="processing">Abrir</Button>
            </Form>
        </section>

        <!-- Resumen -->
        <template v-if="register">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div
                    v-for="card in cards"
                    :key="card.label"
                    class="rounded-xl border p-4"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ card.label }}
                    </p>
                    <p
                        class="mt-1 tabular-nums"
                        :class="
                            card.strong
                                ? 'text-2xl font-semibold'
                                : 'text-xl font-medium'
                        "
                    >
                        {{ formatCurrency(card.value) }}
                    </p>
                </div>
                <div
                    class="col-span-2 rounded-xl border border-primary/30 bg-primary/5 p-4 lg:col-span-1"
                >
                    <p class="text-xs text-muted-foreground">
                        Efectivo esperado en caja
                    </p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ formatCurrency(register.expected_cash) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Fondo {{ formatCurrency(register.opening_amount) }} +
                        efectivo
                    </p>
                </div>
            </div>

            <section class="space-y-3">
                <h2 class="text-base font-medium">Pagos de esta caja</h2>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-2.5 font-medium">Hora</th>
                                <th class="px-4 py-2.5 font-medium">
                                    Paciente
                                </th>
                                <th
                                    class="hidden px-4 py-2.5 font-medium md:table-cell"
                                >
                                    Concepto
                                </th>
                                <th class="px-4 py-2.5 font-medium">Método</th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    Monto
                                </th>
                                <th v-if="can.manageCash" class="w-10" />
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="p in payments" :key="p.id">
                                <td class="px-4 py-2.5 tabular-nums">
                                    {{ p.paid_at.slice(11, 16) }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <Link
                                        v-if="p.patient_id"
                                        :href="patientShow(p.patient_id)"
                                        class="hover:underline"
                                        >{{ p.patient }}</Link
                                    >
                                </td>
                                <td
                                    class="hidden max-w-xs truncate px-4 py-2.5 text-muted-foreground md:table-cell"
                                >
                                    {{ p.concept }}
                                </td>
                                <td class="px-4 py-2.5">
                                    {{ p.method_label }}
                                    <span
                                        v-if="p.reference"
                                        class="text-xs text-muted-foreground"
                                        >· {{ p.reference }}</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right font-medium tabular-nums"
                                >
                                    {{ formatCurrency(p.amount) }}
                                </td>
                                <td v-if="can.manageCash" class="px-2">
                                    <Button
                                        size="icon-sm"
                                        variant="ghost"
                                        class="text-destructive"
                                        aria-label="Cancelar pago"
                                        @click="askVoid(p)"
                                    >
                                        <Ban />
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="payments.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Aún no hay pagos en esta caja.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>

        <!-- Adeudos -->
        <section class="space-y-3">
            <div class="flex items-baseline justify-between gap-2">
                <h2 class="flex items-center gap-2 text-base font-medium">
                    <Wallet class="size-4" /> Adeudos
                </h2>
                <span
                    v-if="outstanding.length"
                    class="text-sm text-muted-foreground"
                >
                    Total pendiente:
                    <strong class="text-destructive tabular-nums">{{
                        formatCurrency(outstandingTotal)
                    }}</strong>
                </span>
            </div>
            <ChargesList
                :charges="outstanding"
                :billing="can.charge ? billing : null"
                show-patient
                empty-text="No hay saldos pendientes."
            />
        </section>

        <!-- Cortes anteriores -->
        <section v-if="can.manageCash && history.length" class="space-y-3">
            <h2 class="text-base font-medium">Cortes anteriores</h2>
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Cierre</th>
                            <th
                                class="hidden px-4 py-2.5 font-medium sm:table-cell"
                            >
                                Cerró
                            </th>
                            <th class="px-4 py-2.5 text-right font-medium">
                                Cobrado
                            </th>
                            <th class="px-4 py-2.5 text-right font-medium">
                                Diferencia
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="h in history"
                            :key="h.id"
                            class="hover:bg-muted/40"
                        >
                            <td class="px-4 py-2.5">
                                <Link
                                    :href="show(h.id)"
                                    class="font-medium hover:underline"
                                    >{{ formatDateTime(h.closed_at) }}</Link
                                >
                            </td>
                            <td class="hidden px-4 py-2.5 sm:table-cell">
                                {{ h.closed_by }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums">
                                {{ formatCurrency(h.total_collected) }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right font-medium tabular-nums"
                                :class="
                                    Number(h.difference) < 0
                                        ? 'text-destructive'
                                        : Number(h.difference) > 0
                                          ? 'text-amber-600'
                                          : 'text-emerald-600'
                                "
                            >
                                {{ formatCurrency(h.difference) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <ChargeDialog
        v-if="can.charge"
        v-model:open="chargeOpen"
        :billing="billing"
    />

    <ReasonDialog
        v-model:open="voidOpen"
        :title="`¿Cancelar el pago de ${voiding ? formatCurrency(voiding.amount) : ''}?`"
        description="El saldo del cobro se restablece. Solo es posible mientras la caja esté abierta."
        confirm-label="Cancelar pago"
        placeholder="Ej. Se capturó un monto equivocado"
        :error="voidError"
        @confirm="voidPayment"
    />

    <Dialog v-if="register" v-model:open="closeOpen">
        <DialogContent>
            <Form
                v-bind="CashController.close.form(register.id)"
                class="space-y-4"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>Corte de caja</DialogTitle>
                    <DialogDescription
                        >Cuenta el efectivo físico y regístralo. Las
                        transferencias y tarjetas se concilian
                        aparte.</DialogDescription
                    >
                </DialogHeader>
                <dl
                    class="grid grid-cols-2 gap-2 rounded-lg bg-muted/50 p-3 text-sm"
                >
                    <dt class="text-muted-foreground">Fondo inicial</dt>
                    <dd class="text-right tabular-nums">
                        {{ formatCurrency(register.opening_amount) }}
                    </dd>
                    <dt class="text-muted-foreground">Cobrado en efectivo</dt>
                    <dd class="text-right tabular-nums">
                        {{ formatCurrency(register.totals.efectivo) }}
                    </dd>
                    <dt class="font-medium">Efectivo esperado</dt>
                    <dd class="text-right font-semibold tabular-nums">
                        {{ formatCurrency(register.expected_cash) }}
                    </dd>
                </dl>
                <div class="grid gap-2">
                    <label for="counted_cash" class="text-sm font-medium"
                        >Efectivo contado *</label
                    >
                    <Input
                        id="counted_cash"
                        v-model="countedCash"
                        name="counted_cash"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                    />
                    <InputError :message="errors.counted_cash" />
                    <p
                        v-if="closeDifference !== null"
                        class="text-sm font-medium"
                        :class="
                            closeDifference < 0
                                ? 'text-destructive'
                                : closeDifference > 0
                                  ? 'text-amber-600'
                                  : 'text-emerald-600'
                        "
                    >
                        {{
                            closeDifference === 0
                                ? 'Cuadra exacto'
                                : closeDifference < 0
                                  ? `Faltan ${formatCurrency(-closeDifference)}`
                                  : `Sobran ${formatCurrency(closeDifference)}`
                        }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <label for="close_notes" class="text-sm font-medium"
                        >Notas</label
                    >
                    <Input
                        id="close_notes"
                        name="notes"
                        maxlength="500"
                        placeholder="Opcional (p. ej. explicación de la diferencia)"
                    />
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary"
                            >Cancelar</Button
                        >
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing || countedCash === ''"
                        ><Lock /> Cerrar caja</Button
                    >
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
