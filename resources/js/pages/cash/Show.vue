<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { index } from '@/routes/cash';
import type { CashSummary, PaymentRow, SelectOption } from '@/types';

const props = defineProps<{
    register: CashSummary;
    payments: PaymentRow[];
    paymentMethods: SelectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Caja', href: index() }],
    },
});

const difference = computed(() => Number(props.register.difference ?? 0));

function print(): void {
    window.print();
}
</script>

<template>
    <Head :title="`Corte de caja #${register.id}`" />

    <div class="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Corte de caja #{{ register.id }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Apertura {{ formatDateTime(register.opened_at) }} ({{
                        register.opened_by
                    }})
                    <template v-if="register.closed_at">
                        · Cierre {{ formatDateTime(register.closed_at) }} ({{
                            register.closed_by
                        }})</template
                    >
                    <template v-else> · Abierta</template>
                </p>
            </div>
            <div class="flex gap-2 print:hidden">
                <Button variant="ghost" as-child
                    ><Link :href="index()"><ArrowLeft /> Caja</Link></Button
                >
                <Button variant="outline" @click="print"
                    ><Printer /> Imprimir</Button
                >
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <section class="rounded-xl border p-5">
                <h2
                    class="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Cobrado por método
                </h2>
                <dl class="space-y-2 text-sm">
                    <div
                        v-for="m in paymentMethods"
                        :key="m.value"
                        class="flex justify-between"
                    >
                        <dt>{{ m.label }}</dt>
                        <dd class="tabular-nums">
                            {{ formatCurrency(register.totals[m.value]) }}
                        </dd>
                    </div>
                    <div
                        class="flex justify-between border-t pt-2 font-semibold"
                    >
                        <dt>Total</dt>
                        <dd class="tabular-nums">
                            {{ formatCurrency(register.total_collected) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border p-5">
                <h2
                    class="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Arqueo de efectivo
                </h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt>Fondo inicial</dt>
                        <dd class="tabular-nums">
                            {{ formatCurrency(register.opening_amount) }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Efectivo esperado</dt>
                        <dd class="tabular-nums">
                            {{ formatCurrency(register.expected_cash) }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Efectivo contado</dt>
                        <dd class="tabular-nums">
                            {{
                                register.counted_cash !== null
                                    ? formatCurrency(register.counted_cash)
                                    : '—'
                            }}
                        </dd>
                    </div>
                    <div
                        v-if="register.difference !== null"
                        class="flex justify-between border-t pt-2 font-semibold"
                        :class="
                            difference < 0
                                ? 'text-destructive'
                                : difference > 0
                                  ? 'text-amber-600'
                                  : 'text-emerald-600'
                        "
                    >
                        <dt>
                            {{
                                difference === 0
                                    ? 'Cuadra exacto'
                                    : difference < 0
                                      ? 'Faltante'
                                      : 'Sobrante'
                            }}
                        </dt>
                        <dd class="tabular-nums">
                            {{ formatCurrency(register.difference) }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="register.notes"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    {{ register.notes }}
                </p>
            </section>
        </div>

        <section class="space-y-2">
            <h2
                class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Detalle de pagos ({{ payments.length }})
            </h2>
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Hora</th>
                            <th class="px-3 py-2 font-medium">Paciente</th>
                            <th class="px-3 py-2 font-medium">Concepto</th>
                            <th class="px-3 py-2 font-medium">Método</th>
                            <th class="px-3 py-2 font-medium">Recibió</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Monto
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="p in payments" :key="p.id">
                            <td class="px-3 py-2 tabular-nums">
                                {{ p.paid_at.slice(11, 16) }}
                            </td>
                            <td class="px-3 py-2">{{ p.patient }}</td>
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ p.concept }}
                            </td>
                            <td class="px-3 py-2">
                                {{ p.method_label
                                }}<span
                                    v-if="p.reference"
                                    class="text-xs text-muted-foreground"
                                >
                                    · {{ p.reference }}</span
                                >
                            </td>
                            <td class="px-3 py-2">{{ p.received_by }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ formatCurrency(p.amount) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
