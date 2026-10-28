<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/lib/format';
import { show as consultationShow } from '@/routes/consultations';
import type {
    PatientHistory,
    PreviousConsultation,
    SelectOption,
    VitalTrendPoint,
} from '@/types';

const props = defineProps<{
    histories: PatientHistory[];
    historyTypes: SelectOption[];
    previous: PreviousConsultation[];
    trend: VitalTrendPoint[];
}>();

const grouped = computed(() =>
    props.historyTypes
        .map((t) => ({
            ...t,
            items: props.histories.filter((h) => h.type === t.value),
        }))
        .filter((g) => g.items.length > 0),
);
</script>

<template>
    <div class="space-y-5 text-sm">
        <section class="space-y-2">
            <h3 class="font-medium">Antecedentes</h3>
            <p v-if="grouped.length === 0" class="text-muted-foreground">
                Sin antecedentes registrados.
            </p>
            <div v-for="group in grouped" :key="group.value">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ group.label }}
                </p>
                <ul class="mt-1 list-inside list-disc space-y-0.5">
                    <li
                        v-for="item in group.items"
                        :key="item.id"
                        :class="
                            item.is_critical
                                ? 'font-medium text-destructive'
                                : ''
                        "
                    >
                        {{ item.description }}
                        <span v-if="item.relative" class="text-muted-foreground"
                            >({{ item.relative }})</span
                        >
                    </li>
                </ul>
            </div>
        </section>

        <section v-if="trend.length" class="space-y-2">
            <h3 class="font-medium">Evolución de signos vitales</h3>
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-xs tabular-nums">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-2 py-1.5 text-left font-medium">
                                Fecha
                            </th>
                            <th class="px-2 py-1.5 text-right font-medium">
                                Peso
                            </th>
                            <th class="px-2 py-1.5 text-right font-medium">
                                IMC
                            </th>
                            <th class="px-2 py-1.5 text-right font-medium">
                                TA
                            </th>
                            <th class="px-2 py-1.5 text-right font-medium">
                                Gluc.
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="point in trend" :key="point.date">
                            <td class="px-2 py-1.5">
                                {{ formatDate(point.date) }}
                            </td>
                            <td class="px-2 py-1.5 text-right">
                                {{ point.weight_kg ?? '—' }}
                            </td>
                            <td class="px-2 py-1.5 text-right">
                                {{ point.bmi ?? '—' }}
                            </td>
                            <td class="px-2 py-1.5 text-right">
                                {{
                                    point.systolic_bp
                                        ? `${point.systolic_bp}/${point.diastolic_bp}`
                                        : '—'
                                }}
                            </td>
                            <td class="px-2 py-1.5 text-right">
                                {{ point.glucose_mg_dl ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-2">
            <h3 class="font-medium">Notas anteriores</h3>
            <p v-if="previous.length === 0" class="text-muted-foreground">
                Primera consulta.
            </p>
            <article
                v-for="note in previous"
                :key="note.id"
                class="space-y-1 rounded-lg border p-3"
            >
                <div class="flex items-center justify-between gap-2">
                    <Link
                        :href="consultationShow(note.id)"
                        class="font-medium hover:underline"
                    >
                        {{ formatDate(note.consulted_at) }}
                    </Link>
                    <div class="flex flex-wrap justify-end gap-1">
                        <span
                            v-for="dx in note.diagnoses"
                            :key="dx.code"
                            class="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px]"
                            :title="dx.description"
                        >
                            {{ dx.code }}
                        </span>
                    </div>
                </div>
                <p v-if="note.reason" class="text-muted-foreground">
                    {{ note.reason }}
                </p>
                <p v-if="note.plan" class="line-clamp-3 whitespace-pre-line">
                    <span class="font-medium">Plan:</span> {{ note.plan }}
                </p>
            </article>
        </section>
    </div>
</template>
