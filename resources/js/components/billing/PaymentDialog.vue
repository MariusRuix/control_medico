<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed, watch } from 'vue';
import ChargeController from '@/actions/App/Http/Controllers/Billing/ChargeController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCurrency } from '@/lib/format';
import type { BillingOptions, ChargeSummary } from '@/types';
import PaymentFields from './PaymentFields.vue';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    charge: ChargeSummary | null;
    billing: BillingOptions;
}>();

const form = useForm({
    payment: { amount: '', method: 'efectivo', reference: '' },
});

watch(open, (isOpen) => {
    if (isOpen && props.charge) {
        form.reset();
        form.clearErrors();
        form.payment.amount = props.charge.balance;
    }
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function submit(): void {
    if (!props.charge) {
        return;
    }

    form.post(ChargeController.pay.url(props.charge.id), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="charge" class="sm:max-w-lg">
            <form class="space-y-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Registrar pago</DialogTitle>
                    <DialogDescription>
                        {{ charge.patient?.full_name }} ·
                        {{ charge.items.map((i) => i.description).join(', ') }}
                    </DialogDescription>
                </DialogHeader>

                <dl
                    class="grid grid-cols-3 gap-2 rounded-lg bg-muted/50 p-3 text-center text-sm"
                >
                    <div>
                        <dt class="text-xs text-muted-foreground">Total</dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatCurrency(charge.total) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Pagado</dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatCurrency(charge.paid) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Saldo</dt>
                        <dd class="font-semibold text-destructive tabular-nums">
                            {{ formatCurrency(charge.balance) }}
                        </dd>
                    </div>
                </dl>

                <p
                    v-if="!billing.cashOpen"
                    class="flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    <TriangleAlert class="size-4" /> Abre la caja para registrar
                    pagos.
                </p>

                <PaymentFields
                    v-else
                    v-model:amount="form.payment.amount"
                    v-model:method="form.payment.method"
                    v-model:reference="form.payment.reference"
                    :methods="billing.paymentMethods"
                    :errors="errors"
                />

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        :disabled="form.processing || !billing.cashOpen"
                    >
                        Registrar
                        {{ formatCurrency(Number(form.payment.amount || 0)) }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
