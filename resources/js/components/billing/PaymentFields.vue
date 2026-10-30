<script setup lang="ts">
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { formatCurrency } from '@/lib/format';
import { toCents } from '@/lib/money';
import type { SelectOption } from '@/types';

/** Monto, método y referencia de un pago, con cálculo de cambio para efectivo. */
const amount = defineModel<string>('amount', { required: true });
const method = defineModel<string>('method', { required: true });
const reference = defineModel<string>('reference', { required: true });

defineProps<{
    methods: SelectOption[];
    errors: Record<string, string | undefined>;
}>();

const receivedWith = ref('');

const change = computed(() => {
    const diff = toCents(receivedWith.value) - toCents(amount.value);

    return receivedWith.value !== '' && diff >= 0 ? diff / 100 : null;
});
</script>

<template>
    <div class="space-y-3">
        <div
            class="grid grid-cols-3 gap-1.5"
            role="radiogroup"
            aria-label="Método de pago"
        >
            <button
                v-for="m in methods"
                :key="m.value"
                type="button"
                role="radio"
                :aria-checked="method === m.value"
                class="rounded-md border px-3 py-2 text-sm transition-colors"
                :class="
                    method === m.value
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-accent'
                "
                @click="method = m.value"
            >
                {{ m.label }}
            </button>
        </div>
        <InputError :message="errors['payment.method']" />

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="grid gap-1 text-sm font-medium">
                Monto a pagar
                <Input
                    v-model="amount"
                    type="number"
                    inputmode="decimal"
                    min="0.01"
                    step="0.01"
                />
                <InputError :message="errors['payment.amount']" />
            </label>

            <label
                v-if="method === 'efectivo'"
                class="grid gap-1 text-sm font-medium"
            >
                Paga con
                <Input
                    v-model="receivedWith"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="0.01"
                    placeholder="Opcional"
                />
                <span
                    v-if="change !== null"
                    class="text-xs font-normal text-muted-foreground"
                >
                    Cambio:
                    <strong class="text-foreground">{{
                        formatCurrency(change)
                    }}</strong>
                </span>
            </label>

            <label v-else class="grid gap-1 text-sm font-medium">
                Referencia
                <Input
                    v-model="reference"
                    maxlength="100"
                    :placeholder="
                        method === 'tarjeta'
                            ? 'Últimos 4 dígitos / autorización'
                            : 'Folio de la transferencia'
                    "
                />
                <InputError :message="errors['payment.reference']" />
            </label>
        </div>
    </div>
</template>
