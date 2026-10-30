<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ChargeController from '@/actions/App/Http/Controllers/Billing/ChargeController';
import PatientPicker from '@/components/agenda/PatientPicker.vue';
import InputError from '@/components/InputError.vue';
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
import { fromCents, toCents } from '@/lib/money';
import type { BillingOptions, ChargePatient } from '@/types';
import PaymentFields from './PaymentFields.vue';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    billing: BillingOptions;
    /** Paciente fijo (expediente, agenda). Si es null se elige en el diálogo. */
    patient?: ChargePatient | null;
    consultationId?: number | null;
    /** Servicio con el que se precarga el primer concepto (p. ej. el de la cita). */
    initialServiceId?: number | null;
}>();

type Item = {
    service_id: number | null;
    description: string;
    quantity: string;
    unit_price: string;
    discount: string;
};

const selectedPatient = ref<ChargePatient | null>(null);
const amountTouched = ref(false);

const form = useForm({
    patient_id: null as number | null,
    consultation_id: null as number | null,
    notes: '',
    items: [] as Item[],
    pay_now: true,
    payment: { amount: '', method: 'efectivo', reference: '' },
});

function itemFromService(id: number | null): Item {
    const service = props.billing.services.find((s) => s.id === id);

    return {
        service_id: service?.id ?? null,
        description: service?.name ?? '',
        quantity: '1',
        unit_price: service?.price ?? '',
        discount: '',
    };
}

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.reset();
    form.clearErrors();
    selectedPatient.value = props.patient ?? null;
    form.consultation_id = props.consultationId ?? null;
    form.items = [itemFromService(props.initialServiceId ?? null)];
    form.pay_now = props.billing.cashOpen;
    amountTouched.value = false;
});

watch(selectedPatient, (p) => (form.patient_id = p?.id ?? null), {
    immediate: true,
});

function subtotalCents(item: Item): number {
    return (
        Math.round(Number(item.quantity || 0) * toCents(item.unit_price)) -
        toCents(item.discount)
    );
}

const totalCents = computed(() =>
    form.items.reduce((sum, item) => sum + subtotalCents(item), 0),
);

// El monto a pagar sigue al total mientras no se edite a mano.
watch(
    totalCents,
    (cents) => {
        if (!amountTouched.value) {
            form.payment.amount = cents > 0 ? fromCents(cents) : '';
        }
    },
    { immediate: true },
);

const paymentAmount = computed({
    get: () => form.payment.amount,
    set: (value: string) => {
        amountTouched.value = true;
        form.payment.amount = value;
    },
});

function onServiceChange(index: number, event: Event): void {
    const id = Number((event.target as HTMLSelectElement).value) || null;
    form.items[index] = {
        ...itemFromService(id),
        quantity: form.items[index].quantity,
    };
}

function addItem(): void {
    form.items = [...form.items, itemFromService(null)];
}

function removeItem(index: number): void {
    form.items = form.items.filter((_, i) => i !== index);
}

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function itemError(i: number): string | undefined {
    return ['description', 'quantity', 'unit_price', 'discount']
        .map((f) => errors.value[`items.${i}.${f}`])
        .find(Boolean);
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        payment: data.pay_now ? data.payment : null,
    })).post(ChargeController.store.url(), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}

const fieldClass =
    'h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Nuevo cobro</DialogTitle>
                    <DialogDescription
                        >Consultas, procedimientos o insumos. Puedes cobrar
                        ahora o dejarlo como adeudo.</DialogDescription
                    >
                </DialogHeader>

                <div class="grid gap-2">
                    <span class="text-sm font-medium">Paciente *</span>
                    <PatientPicker
                        v-model="selectedPatient"
                        :disabled="!!patient"
                        :invalid="!!errors.patient_id"
                    />
                    <InputError :message="errors.patient_id" />
                </div>

                <div class="space-y-2">
                    <div
                        class="hidden grid-cols-[1fr_64px_96px_88px_88px_32px] gap-2 px-1 text-xs text-muted-foreground sm:grid"
                    >
                        <span>Concepto</span><span>Cant.</span
                        ><span>Precio</span><span>Descuento</span
                        ><span class="text-right">Importe</span><span />
                    </div>
                    <div
                        v-for="(item, i) in form.items"
                        :key="i"
                        class="grid grid-cols-2 gap-2 rounded-lg border p-2 sm:grid-cols-[1fr_64px_96px_88px_88px_32px] sm:items-center sm:border-0 sm:p-0"
                    >
                        <div class="col-span-2 grid gap-1 sm:col-span-1">
                            <select
                                :class="fieldClass"
                                :value="item.service_id ?? ''"
                                aria-label="Servicio"
                                @change="onServiceChange(i, $event)"
                            >
                                <option value="">Otro concepto…</option>
                                <option
                                    v-for="s in billing.services"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.name }}
                                </option>
                            </select>
                            <input
                                v-if="item.service_id === null"
                                v-model="item.description"
                                :class="fieldClass"
                                placeholder="Descripción del concepto"
                                maxlength="255"
                                aria-label="Descripción"
                            />
                        </div>
                        <input
                            v-model="item.quantity"
                            :class="fieldClass"
                            type="number"
                            min="0.01"
                            step="any"
                            aria-label="Cantidad"
                        />
                        <input
                            v-model="item.unit_price"
                            :class="fieldClass"
                            type="number"
                            min="0"
                            step="0.01"
                            aria-label="Precio"
                        />
                        <input
                            v-model="item.discount"
                            :class="fieldClass"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            aria-label="Descuento"
                        />
                        <span
                            class="text-right text-sm font-medium tabular-nums"
                            :class="
                                subtotalCents(item) < 0
                                    ? 'text-destructive'
                                    : ''
                            "
                        >
                            {{ formatCurrency(subtotalCents(item) / 100) }}
                        </span>
                        <Button
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Quitar concepto"
                            :disabled="form.items.length === 1"
                            @click="removeItem(i)"
                        >
                            <Trash2 />
                        </Button>
                        <div class="col-span-2 sm:col-span-6">
                            <InputError :message="itemError(i)" />
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="addItem"
                        ><Plus /> Agregar concepto</Button
                    >
                    <InputError :message="errors.items" />
                </div>

                <div
                    class="flex items-center justify-between rounded-lg bg-muted/50 px-4 py-3"
                >
                    <span class="text-sm text-muted-foreground">Total</span>
                    <span class="text-xl font-semibold tabular-nums">{{
                        formatCurrency(totalCents / 100)
                    }}</span>
                </div>

                <div class="space-y-3 rounded-lg border p-3">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input
                            v-model="form.pay_now"
                            type="checkbox"
                            class="size-4"
                            :disabled="!billing.cashOpen"
                        />
                        Cobrar ahora
                    </label>
                    <p
                        v-if="!billing.cashOpen"
                        class="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-300"
                    >
                        <TriangleAlert class="size-3.5" /> La caja está cerrada:
                        el cobro quedará como adeudo.
                    </p>
                    <PaymentFields
                        v-if="form.pay_now"
                        v-model:amount="paymentAmount"
                        v-model:method="form.payment.method"
                        v-model:reference="form.payment.reference"
                        :methods="billing.paymentMethods"
                        :errors="errors"
                    />
                </div>

                <label class="grid gap-1 text-sm font-medium">
                    Notas
                    <input
                        v-model="form.notes"
                        :class="fieldClass"
                        maxlength="500"
                        placeholder="Opcional"
                    />
                </label>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        :disabled="
                            form.processing ||
                            !form.patient_id ||
                            totalCents <= 0
                        "
                    >
                        {{
                            form.pay_now
                                ? `Cobrar ${formatCurrency(Number(form.payment.amount || 0))}`
                                : 'Registrar adeudo'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
