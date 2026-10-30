<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { Download, TrendingDown, TrendingUp } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import FinancialReportController from '@/actions/App/Http/Controllers/Reports/FinancialReportController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatCurrency, formatDate } from '@/lib/format';
import { show as patientShow } from '@/routes/patients';
import { finance } from '@/routes/reports';

type TrendPoint = { key: string; label: string; amount: string; count: number };
type MethodRow = {
    method: string;
    label: string;
    amount: string;
    count: number;
    share: number;
};
type ServiceRow = {
    service_id: number | null;
    name: string;
    quantity: string;
    amount: string;
    share: number;
};
type AgingBucket = {
    key: string;
    label: string;
    amount: string;
    count: number;
    share: number;
};
type Debtor = {
    patient_id: number;
    full_name: string | null;
    folio: string | null;
    phone: string | null;
    amount: string;
    count: number;
    oldest_days: number;
};

const props = defineProps<{
    filters: { from: string; to: string; group: 'day' | 'week' | 'month' };
    previous: { from: string; to: string };
    report: {
        summary: {
            income: string;
            payments_count: number;
            previous_income: string;
            change_pct: number | null;
            billed: string;
            charges_count: number;
            discounts: string;
            average_ticket: string;
        };
        trend: TrendPoint[];
        methods: MethodRow[];
        services: ServiceRow[];
        aging: {
            total: string;
            patients_count: number;
            buckets: AgingBucket[];
            debtors: Debtor[];
        };
    };
    errors?: Record<string, string>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reportes', href: finance() }],
    },
});

const inputClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

// Filtros
const form = reactive({ ...props.filters });

// El servidor puede ajustar la agrupación (p. ej. al elegir "Este año").
watch(
    () => props.filters,
    (filters) => Object.assign(form, filters),
);

function apply(withGroup = true): void {
    router.get(
        finance.url(),
        withGroup ? { ...form } : { from: form.from, to: form.to }, // al cambiar el periodo, la agrupación se elige sola
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function iso(d: Date): string {
    return [
        d.getFullYear(),
        String(d.getMonth() + 1).padStart(2, '0'),
        String(d.getDate()).padStart(2, '0'),
    ].join('-');
}

const PRESETS: { label: string; range: () => [Date, Date] }[] = [
    {
        label: 'Este mes',
        range: () => {
            const now = new Date();

            return [new Date(now.getFullYear(), now.getMonth(), 1), now];
        },
    },
    {
        label: 'Mes anterior',
        range: () => {
            const now = new Date();

            return [
                new Date(now.getFullYear(), now.getMonth() - 1, 1),
                new Date(now.getFullYear(), now.getMonth(), 0),
            ];
        },
    },
    {
        label: 'Últimos 30 días',
        range: () => {
            const now = new Date();
            const from = new Date(now);
            from.setDate(now.getDate() - 29);

            return [from, now];
        },
    },
    {
        label: 'Este año',
        range: () => {
            const now = new Date();

            return [new Date(now.getFullYear(), 0, 1), now];
        },
    },
];

function preset(range: () => [Date, Date]): void {
    const [from, to] = range();
    form.from = iso(from);
    form.to = iso(to);
    apply(false);
}

function exportUrl(dataset: string): string {
    return FinancialReportController.export.url({
        query: { ...props.filters, dataset },
    });
}

// Gráfica de ingresos (una sola serie: sin leyenda, el título la nombra)
const GROUP_LABEL = { day: 'día', week: 'semana', month: 'mes' } as const;
const trendMax = computed(() =>
    Math.max(...props.report.trend.map((p) => Number(p.amount)), 0),
);
const niceMax = computed(() => {
    const max = trendMax.value;

    if (max <= 0) {
        return 1000;
    }

    const magnitude = 10 ** Math.floor(Math.log10(max));
    const step = [1, 2, 2.5, 5, 10].find((s) => s * magnitude >= max) ?? 10;

    return step * magnitude;
});
const gridLines = computed(() => [1, 0.5].map((f) => niceMax.value * f));
const isWide = useMediaQuery('(min-width: 768px)');
const labelEvery = computed(() =>
    Math.max(1, Math.ceil(props.report.trend.length / (isWide.value ? 12 : 4))),
);
const hovered = ref<number | null>(null);

function barHeight(amount: string): string {
    const value = Number(amount);

    return value > 0 ? `${Math.max((value / niceMax.value) * 100, 1.5)}%` : '0';
}

const compactCurrency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    notation: 'compact',
    maximumFractionDigits: 1,
});

const change = computed(() => props.report.summary.change_pct);
</script>

<template>
    <Head title="Reportes financieros" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Reportes financieros"
                description="Ingresos, servicios y adeudos del consultorio"
            />
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="outline"
                        ><Download /> Exportar a Excel (CSV)</Button
                    >
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem as-child>
                        <a :href="exportUrl('payments')"
                            >Pagos del periodo (detalle)</a
                        >
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <a :href="exportUrl('services')"
                            >Ingresos por servicio</a
                        >
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <a :href="exportUrl('debts')">Adeudos a hoy</a>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <!-- Filtros: una sola fila -->
        <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1.5">
                <label for="r_from" class="text-sm font-medium">Desde</label>
                <input
                    id="r_from"
                    v-model="form.from"
                    type="date"
                    :class="inputClass"
                    @change="apply(false)"
                />
            </div>
            <div class="grid gap-1.5">
                <label for="r_to" class="text-sm font-medium">Hasta</label>
                <input
                    id="r_to"
                    v-model="form.to"
                    type="date"
                    :class="inputClass"
                    @change="apply(false)"
                />
            </div>
            <div class="flex flex-wrap gap-1.5">
                <Button
                    v-for="p in PRESETS"
                    :key="p.label"
                    size="sm"
                    variant="secondary"
                    @click="preset(p.range)"
                    >{{ p.label }}</Button
                >
            </div>
            <div class="grid gap-1.5 sm:ml-auto">
                <label for="r_group" class="text-sm font-medium"
                    >Agrupar por</label
                >
                <select
                    id="r_group"
                    v-model="form.group"
                    :class="inputClass"
                    @change="apply()"
                >
                    <option value="day">Día</option>
                    <option value="week">Semana</option>
                    <option value="month">Mes</option>
                </select>
            </div>
            <InputError class="w-full" :message="errors?.to ?? errors?.from" />
        </div>

        <!-- Indicadores -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">Ingresos cobrados</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatCurrency(report.summary.income) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ report.summary.payments_count }} pago(s) ·
                    <template v-if="change !== null">
                        <span
                            class="inline-flex items-center gap-0.5 font-medium text-foreground"
                        >
                            <TrendingUp v-if="change >= 0" class="size-3.5" />
                            <TrendingDown v-else class="size-3.5" />
                            {{ change >= 0 ? '+' : '' }}{{ change }}%
                        </span>
                        vs. {{ formatDate(previous.from) }} –
                        {{ formatDate(previous.to) }}
                    </template>
                    <template v-else
                        >sin ingresos en el periodo anterior</template
                    >
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">Facturado</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatCurrency(report.summary.billed) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ report.summary.charges_count }} cobro(s) · promedio
                    {{ formatCurrency(report.summary.average_ticket) }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">
                    Descuentos otorgados
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatCurrency(report.summary.discounts) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    En los cobros del periodo
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">Adeudos a hoy</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatCurrency(report.aging.total) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ report.aging.patients_count }} paciente(s) con saldo
                </p>
            </div>
        </div>

        <!-- Ingresos en el tiempo -->
        <section class="rounded-xl border p-4">
            <h2 class="font-medium">
                Ingresos por {{ GROUP_LABEL[filters.group] }}
            </h2>
            <p class="text-xs text-muted-foreground">
                Pagos recibidos, sin importar la fecha del cobro.
            </p>

            <div class="mt-6 flex gap-2">
                <!-- Eje Y -->
                <div
                    class="relative h-56 w-14 shrink-0 text-right text-[11px] text-muted-foreground tabular-nums"
                    aria-hidden="true"
                >
                    <span
                        v-for="g in gridLines"
                        :key="g"
                        class="absolute right-0 -translate-y-1/2"
                        :style="{ bottom: `${(g / niceMax) * 100}%` }"
                        >{{ compactCurrency.format(g) }}</span
                    >
                    <span class="absolute right-0 bottom-0">$0</span>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="relative h-56 border-b border-border">
                        <div
                            v-for="g in gridLines"
                            :key="g"
                            class="absolute inset-x-0 border-t border-dashed border-border/70"
                            :style="{ bottom: `${(g / niceMax) * 100}%` }"
                        />
                        <div
                            class="absolute inset-0 flex items-end"
                            role="list"
                            :aria-label="`Ingresos por ${GROUP_LABEL[filters.group]}`"
                        >
                            <div
                                v-for="(p, i) in report.trend"
                                :key="p.key"
                                role="listitem"
                                tabindex="0"
                                class="relative flex h-full flex-1 items-end justify-center outline-none focus-visible:bg-muted/60"
                                :class="hovered === i ? 'bg-muted/50' : ''"
                                :aria-label="`${p.label}: ${formatCurrency(p.amount)}, ${p.count} pago(s)`"
                                @mouseenter="hovered = i"
                                @mouseleave="hovered = null"
                                @focus="hovered = i"
                                @blur="hovered = null"
                            >
                                <div
                                    class="w-[70%] max-w-6 rounded-t bg-[#2a78d6] dark:bg-[#3987e5]"
                                    :style="{ height: barHeight(p.amount) }"
                                />
                                <div
                                    v-if="hovered === i"
                                    class="pointer-events-none absolute bottom-full z-10 mb-1 rounded-md border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap text-popover-foreground shadow-md"
                                    :class="
                                        i < report.trend.length / 2
                                            ? 'left-0'
                                            : 'right-0'
                                    "
                                >
                                    <p class="font-medium">{{ p.label }}</p>
                                    <p class="tabular-nums">
                                        {{ formatCurrency(p.amount) }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ p.count }} pago(s)
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Eje X -->
                    <div
                        class="mt-1 flex text-[11px] text-muted-foreground"
                        aria-hidden="true"
                    >
                        <span
                            v-for="(p, i) in report.trend"
                            :key="p.key"
                            class="flex-1 overflow-visible text-center whitespace-nowrap"
                            >{{ i % labelEvery === 0 ? p.label : '' }}</span
                        >
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Por método de pago -->
            <section class="rounded-xl border p-4">
                <h2 class="font-medium">Por método de pago</h2>
                <ul class="mt-4 space-y-4">
                    <li v-for="m in report.methods" :key="m.method">
                        <div
                            class="flex items-baseline justify-between gap-3 text-sm"
                        >
                            <span>{{ m.label }}</span>
                            <span class="tabular-nums">
                                <span class="font-medium">{{
                                    formatCurrency(m.amount)
                                }}</span>
                                <span class="ml-2 text-muted-foreground"
                                    >{{ m.share }}% · {{ m.count }}</span
                                >
                            </span>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full bg-[#2a78d6] dark:bg-[#3987e5]"
                                :style="{ width: `${m.share}%` }"
                            />
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Por servicio -->
            <section class="rounded-xl border p-4">
                <h2 class="font-medium">Por servicio</h2>
                <p class="text-xs text-muted-foreground">
                    Importe de los cobros del periodo (sin cancelados), pagados
                    o no.
                </p>
                <table
                    v-if="report.services.length"
                    class="mt-3 w-full text-sm"
                >
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="py-1.5 font-medium">Servicio</th>
                            <th
                                class="hidden py-1.5 pl-3 text-right font-medium sm:table-cell"
                            >
                                Cant.
                            </th>
                            <th class="py-1.5 text-right font-medium">
                                Importe
                            </th>
                            <th class="w-24 py-1.5 pl-3 font-medium">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="s in report.services" :key="s.name">
                            <td class="py-2">{{ s.name }}</td>
                            <td
                                class="hidden py-2 pl-3 text-right tabular-nums sm:table-cell"
                            >
                                {{ s.quantity }}
                            </td>
                            <td
                                class="py-2 text-right font-medium tabular-nums"
                            >
                                {{ formatCurrency(s.amount) }}
                            </td>
                            <td class="py-2 pl-3">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-1.5 flex-1 rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-1.5 rounded-full bg-[#2a78d6] dark:bg-[#3987e5]"
                                            :style="{ width: `${s.share}%` }"
                                        />
                                    </div>
                                    <span
                                        class="w-9 text-right text-xs text-muted-foreground tabular-nums"
                                        >{{ Math.round(s.share) }}%</span
                                    >
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="mt-6 text-sm text-muted-foreground">
                    No hay cobros en este periodo.
                </p>
            </section>
        </div>

        <!-- Antigüedad de adeudos -->
        <section class="rounded-xl border p-4">
            <h2 class="font-medium">Antigüedad de adeudos</h2>
            <p class="text-xs text-muted-foreground">
                Saldos pendientes a hoy según la fecha del cobro. No depende del
                periodo seleccionado.
            </p>

            <div
                class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]"
            >
                <ul class="space-y-4">
                    <li v-for="b in report.aging.buckets" :key="b.key">
                        <div
                            class="flex items-baseline justify-between gap-3 text-sm"
                        >
                            <span>{{ b.label }}</span>
                            <span class="tabular-nums">
                                <span class="font-medium">{{
                                    formatCurrency(b.amount)
                                }}</span>
                                <span class="ml-2 text-muted-foreground"
                                    >{{ b.count }} cobro(s)</span
                                >
                            </span>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full bg-[#2a78d6] dark:bg-[#3987e5]"
                                :style="{ width: `${b.share}%` }"
                            />
                        </div>
                    </li>
                </ul>

                <div>
                    <h3 class="text-sm font-medium">
                        Pacientes con mayor adeudo
                    </h3>
                    <table
                        v-if="report.aging.debtors.length"
                        class="mt-2 w-full text-sm"
                    >
                        <thead class="text-left text-xs text-muted-foreground">
                            <tr>
                                <th class="py-1.5 font-medium">Paciente</th>
                                <th
                                    class="hidden py-1.5 font-medium sm:table-cell"
                                >
                                    Teléfono
                                </th>
                                <th class="py-1.5 pl-3 text-right font-medium">
                                    Antigüedad
                                </th>
                                <th class="py-1.5 pl-3 text-right font-medium">
                                    Saldo
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="d in report.aging.debtors"
                                :key="d.patient_id"
                            >
                                <td class="py-2">
                                    <Link
                                        :href="patientShow(d.patient_id)"
                                        class="hover:underline"
                                        >{{ d.full_name }}</Link
                                    >
                                    <span
                                        class="ml-1 text-xs text-muted-foreground"
                                        >{{ d.folio }} ·
                                        {{ d.count }} cobro(s)</span
                                    >
                                </td>
                                <td
                                    class="hidden py-2 text-muted-foreground sm:table-cell"
                                >
                                    {{ d.phone ?? '—' }}
                                </td>
                                <td
                                    class="py-2 pl-3 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ d.oldest_days }}
                                    {{ d.oldest_days === 1 ? 'día' : 'días' }}
                                </td>
                                <td
                                    class="py-2 pl-3 text-right font-medium whitespace-nowrap tabular-nums"
                                >
                                    {{ formatCurrency(d.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        No hay adeudos pendientes.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
