<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Copy, Plus, X } from '@lucide/vue';
import { computed } from 'vue';
import ScheduleSettingsController from '@/actions/App/Http/Controllers/Settings/ScheduleSettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/settings/schedule';
import type { DayKey, WorkingHours } from '@/types';

const props = defineProps<{
    workingHours: WorkingHours;
    slotMinutes: number;
    slotOptions: number[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Horario de atención', href: edit() }],
    },
});

const DAYS: { key: DayKey; label: string }[] = [
    { key: 'mon', label: 'Lunes' },
    { key: 'tue', label: 'Martes' },
    { key: 'wed', label: 'Miércoles' },
    { key: 'thu', label: 'Jueves' },
    { key: 'fri', label: 'Viernes' },
    { key: 'sat', label: 'Sábado' },
    { key: 'sun', label: 'Domingo' },
];

const form = useForm({
    slot_minutes: props.slotMinutes,
    working_hours: JSON.parse(
        JSON.stringify(props.workingHours),
    ) as WorkingHours,
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function addRange(day: DayKey): void {
    const ranges = form.working_hours[day];
    const last = ranges[ranges.length - 1];
    ranges.push(
        last
            ? [last[1], last[1] < '20:00' ? '20:00' : '23:00']
            : ['09:00', '14:00'],
    );
}

function removeRange(day: DayKey, index: number): void {
    form.working_hours[day].splice(index, 1);
}

function copyMondayToWeekdays(): void {
    for (const day of ['tue', 'wed', 'thu', 'fri'] as DayKey[]) {
        form.working_hours[day] = form.working_hours.mon.map(
            (r) => [r[0], r[1]] as [string, string],
        );
    }
}

function rangeError(day: DayKey): string | undefined {
    return Object.entries(errors.value).find(([key]) =>
        key.startsWith(`working_hours.${day}`),
    )?.[1];
}

function submit(): void {
    form.put(ScheduleSettingsController.update.url(), { preserveScroll: true });
}

const timeClass =
    'h-9 rounded-md border border-input bg-transparent px-2 text-sm tabular-nums shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head title="Horario de atención" />

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Horario de atención"
            description="Define cuándo se pueden agendar citas. Para vacaciones o días festivos usa 'Bloquear' en la agenda."
        />

        <form class="space-y-6" @submit.prevent="submit">
            <div class="divide-y rounded-lg border">
                <div
                    v-for="day in DAYS"
                    :key="day.key"
                    class="flex flex-wrap items-start gap-3 p-3"
                >
                    <span class="w-24 pt-2 text-sm font-medium">{{
                        day.label
                    }}</span>
                    <div class="flex flex-1 flex-col gap-2">
                        <p
                            v-if="form.working_hours[day.key].length === 0"
                            class="pt-2 text-sm text-muted-foreground"
                        >
                            Cerrado
                        </p>
                        <div
                            v-for="(range, i) in form.working_hours[day.key]"
                            :key="i"
                            class="flex items-center gap-2"
                        >
                            <input
                                v-model="range[0]"
                                type="time"
                                step="300"
                                :class="timeClass"
                                :aria-label="`${day.label}: apertura`"
                            />
                            <span class="text-muted-foreground">a</span>
                            <input
                                v-model="range[1]"
                                type="time"
                                step="300"
                                :class="timeClass"
                                :aria-label="`${day.label}: cierre`"
                            />
                            <Button
                                type="button"
                                size="icon-sm"
                                variant="ghost"
                                aria-label="Quitar horario"
                                @click="removeRange(day.key, i)"
                            >
                                <X />
                            </Button>
                        </div>
                        <InputError :message="rangeError(day.key)" />
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        :disabled="form.working_hours[day.key].length >= 4"
                        @click="addRange(day.key)"
                    >
                        <Plus /> Horario
                    </Button>
                </div>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="copyMondayToWeekdays"
            >
                <Copy /> Copiar el horario del lunes a martes–viernes
            </Button>

            <div class="grid max-w-xs gap-2">
                <label for="slot_minutes" class="text-sm font-medium"
                    >Intervalo de la agenda</label
                >
                <select
                    id="slot_minutes"
                    v-model.number="form.slot_minutes"
                    :class="timeClass"
                >
                    <option v-for="m in slotOptions" :key="m" :value="m">
                        Cada {{ m }} minutos
                    </option>
                </select>
                <p class="text-xs text-muted-foreground">
                    Cada cuánto se ofrecen horarios libres al agendar.
                </p>
                <InputError :message="errors.slot_minutes" />
            </div>

            <Button type="submit" :disabled="form.processing"
                >Guardar horario</Button
            >
        </form>
    </div>
</template>
