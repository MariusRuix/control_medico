<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Ban, Wallet } from '@lucide/vue';
import { ref } from 'vue';
import ChargeController from '@/actions/App/Http/Controllers/Billing/ChargeController';
import ReasonDialog from '@/components/ReasonDialog.vue';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate } from '@/lib/format';
import { show as patientShow } from '@/routes/patients';
import type { BillingOptions, ChargeSummary } from '@/types';
import PaymentDialog from './PaymentDialog.vue';

defineProps<{
    charges: ChargeSummary[];
    billing: BillingOptions | null;
    showPatient?: boolean;
    emptyText?: string;
}>();

const statusClasses: Record<ChargeSummary['status'], string> = {
    pendiente:
        'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    parcial: 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
    pagado: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    cancelado:
        'bg-zinc-100 text-zinc-600 line-through dark:bg-zinc-500/15 dark:text-zinc-400',
};

const paying = ref<ChargeSummary | null>(null);
const payOpen = ref(false);
const cancelling = ref<ChargeSummary | null>(null);
const cancelOpen = ref(false);
const cancelError = ref<string | undefined>();

function pay(charge: ChargeSummary): void {
    paying.value = charge;
    payOpen.value = true;
}

function askCancel(charge: ChargeSummary): void {
    cancelling.value = charge;
    cancelError.value = undefined;
    cancelOpen.value = true;
}

function cancel(reason: string): void {
    if (!cancelling.value) {
        return;
    }

    router.post(
        ChargeController.cancel.url(cancelling.value.id),
        { reason },
        {
            preserveScroll: true,
            onSuccess: () => (cancelOpen.value = false),
            onError: (e) => (cancelError.value = e.reason),
        },
    );
}

const isOpen = (c: ChargeSummary) =>
    c.status === 'pendiente' || c.status === 'parcial';
const canCancel = (c: ChargeSummary) => c.status === 'pendiente';
</script>

<template>
    <p v-if="charges.length === 0" class="text-sm text-muted-foreground">
        {{ emptyText ?? 'Sin cobros.' }}
    </p>
    <ul v-else class="divide-y rounded-lg border">
        <li
            v-for="charge in charges"
            :key="charge.id"
            class="flex flex-wrap items-center gap-3 p-3"
        >
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">
                    <Link
                        v-if="showPatient && charge.patient"
                        :href="patientShow(charge.patient.id)"
                        class="hover:underline"
                    >
                        {{ charge.patient.full_name }}
                    </Link>
                    <template v-else>{{
                        charge.items.map((i) => i.description).join(', ')
                    }}</template>
                </p>
                <p class="text-xs text-muted-foreground">
                    <template v-if="showPatient"
                        >{{
                            charge.items.map((i) => i.description).join(', ')
                        }}
                        ·
                    </template>
                    {{ formatDate(charge.created_at) }}
                    <span
                        class="ml-1 rounded-full px-1.5 py-0.5 font-medium"
                        :class="statusClasses[charge.status]"
                        >{{ charge.status_label }}</span
                    >
                </p>
            </div>
            <div class="text-right text-sm tabular-nums">
                <p
                    class="font-semibold"
                    :class="isOpen(charge) ? 'text-destructive' : ''"
                >
                    {{
                        formatCurrency(
                            isOpen(charge) ? charge.balance : charge.total,
                        )
                    }}
                </p>
                <p
                    v-if="charge.status === 'parcial'"
                    class="text-xs text-muted-foreground"
                >
                    de {{ formatCurrency(charge.total) }}
                </p>
            </div>
            <div v-if="billing" class="flex gap-1">
                <Button
                    v-if="isOpen(charge)"
                    size="sm"
                    variant="outline"
                    @click="pay(charge)"
                    ><Wallet /> Pagar</Button
                >
                <Button
                    v-if="canCancel(charge)"
                    size="icon-sm"
                    variant="ghost"
                    class="text-destructive"
                    aria-label="Cancelar cobro"
                    @click="askCancel(charge)"
                >
                    <Ban />
                </Button>
            </div>
        </li>
    </ul>

    <template v-if="billing">
        <PaymentDialog
            v-model:open="payOpen"
            :charge="paying"
            :billing="billing"
        />
        <ReasonDialog
            v-model:open="cancelOpen"
            title="¿Cancelar este cobro?"
            description="El adeudo deja de contar en el saldo del paciente. El motivo queda en la bitácora."
            confirm-label="Cancelar cobro"
            placeholder="Ej. Se registró dos veces"
            :error="cancelError"
            @confirm="cancel"
        />
    </template>
</template>
