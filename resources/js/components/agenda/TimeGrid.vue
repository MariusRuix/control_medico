<script setup lang="ts">
import { Ban } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    capitalize,
    dayKey,
    formatters,
    minutesFromDayStart,
    minutesToTime,
    parseLocal,
    statusEventClasses,
    timeOf,
    timeToMinutes,
} from '@/lib/agenda';
import type {
    AppointmentStatusDefinition,
    CalendarAppointment,
    CalendarBlock,
    WorkingHours,
} from '@/types';

const props = defineProps<{
    days: string[];
    today: string;
    appointments: CalendarAppointment[];
    blocks: CalendarBlock[];
    workingHours: WorkingHours;
    slotMinutes: number;
    statuses: Record<string, AppointmentStatusDefinition>;
    canManage: boolean;
}>();

const emit = defineEmits<{
    slot: [date: string, time: string];
    select: [appointment: CalendarAppointment];
    selectBlock: [block: CalendarBlock];
}>();

const PX_PER_MINUTE = 1.4;

/** Rango visible: del primer inicio al último cierre de la semana (mínimo 08:00–20:00). */
const bounds = computed(() => {
    let start = 8 * 60;
    let end = 20 * 60;

    for (const day of props.days) {
        for (const [from, to] of props.workingHours[dayKey(day)] ?? []) {
            start = Math.min(start, timeToMinutes(from));
            end = Math.max(end, timeToMinutes(to));
        }
    }

    for (const a of props.appointments) {
        const day = a.starts_at.slice(0, 10);
        start = Math.min(start, minutesFromDayStart(a.starts_at, day));
        end = Math.max(end, minutesFromDayStart(a.ends_at, day));
    }

    return {
        start: Math.max(0, Math.floor(start / 60) * 60),
        end: Math.min(24 * 60, Math.ceil(end / 60) * 60),
    };
});

const height = computed(
    () => (bounds.value.end - bounds.value.start) * PX_PER_MINUTE,
);

const hours = computed(() => {
    const list: number[] = [];

    for (let m = bounds.value.start; m < bounds.value.end; m += 60) {
        list.push(m);
    }

    return list;
});

function top(minutes: number): number {
    return (minutes - bounds.value.start) * PX_PER_MINUTE;
}

type PositionedEvent = {
    appointment: CalendarAppointment;
    top: number;
    height: number;
    column: number;
    columns: number;
};

/** Coloca las citas del día; si se encimaran (p. ej. una cancelada), se reparten en columnas. */
function eventsFor(day: string): PositionedEvent[] {
    const items = props.appointments
        .filter((a) => a.starts_at.slice(0, 10) === day)
        .map((a) => ({
            appointment: a,
            start: minutesFromDayStart(a.starts_at, day),
            end: minutesFromDayStart(a.ends_at, day),
        }))
        .sort((a, b) => a.start - b.start || b.end - a.end);

    const result: PositionedEvent[] = [];
    let group: ((typeof items)[number] & { column: number })[] = [];
    let groupEnd = -1;

    const flush = () => {
        const columns = Math.max(...group.map((g) => g.column)) + 1;

        for (const g of group) {
            result.push({
                appointment: g.appointment,
                top: top(g.start),
                height: Math.max((g.end - g.start) * PX_PER_MINUTE, 18),
                column: g.column,
                columns,
            });
        }

        group = [];
    };

    for (const item of items) {
        if (group.length && item.start >= groupEnd) {
            flush();
            groupEnd = -1;
        }

        const used = new Set(
            group.filter((g) => g.end > item.start).map((g) => g.column),
        );
        let column = 0;

        while (used.has(column)) {
            column++;
        }

        group.push({ ...item, column });
        groupEnd = Math.max(groupEnd, item.end);
    }

    if (group.length) {
        flush();
    }

    return result;
}

function workingRanges(day: string) {
    return (props.workingHours[dayKey(day)] ?? []).map(([from, to]) => ({
        top: top(timeToMinutes(from)),
        height: (timeToMinutes(to) - timeToMinutes(from)) * PX_PER_MINUTE,
    }));
}

function blocksFor(day: string) {
    return props.blocks
        .map((block) => {
            const start = Math.max(
                minutesFromDayStart(block.starts_at, day),
                bounds.value.start,
            );
            const end = Math.min(
                minutesFromDayStart(block.ends_at, day),
                bounds.value.end,
            );

            return { block, start, end };
        })
        .filter(({ start, end }) => end > start)
        .map(({ block, start, end }) => ({
            block,
            top: top(start),
            height: (end - start) * PX_PER_MINUTE,
        }));
}

function onColumnClick(day: string, event: MouseEvent): void {
    if (!props.canManage) {
        return;
    }

    const target = event.currentTarget as HTMLElement;
    const offset = event.clientY - target.getBoundingClientRect().top;
    const minutes = bounds.value.start + offset / PX_PER_MINUTE;
    const snapped = Math.floor(minutes / props.slotMinutes) * props.slotMinutes;

    emit('slot', day, minutesToTime(snapped));
}

// Línea de "ahora"
const now = ref(new Date());
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    timer = setInterval(() => (now.value = new Date()), 60_000);
});

onBeforeUnmount(() => clearInterval(timer));

const nowTop = computed(() => {
    const minutes = now.value.getHours() * 60 + now.value.getMinutes();

    return minutes >= bounds.value.start && minutes <= bounds.value.end
        ? top(minutes)
        : null;
});

function statusColor(status: string): string {
    return props.statuses[status]?.color ?? 'zinc';
}
</script>

<template>
    <div class="overflow-x-auto rounded-xl border">
        <div :class="days.length > 1 ? 'min-w-[760px]' : ''">
            <!-- Encabezado de días -->
            <div
                class="sticky top-0 z-20 grid border-b bg-background"
                :style="{
                    gridTemplateColumns: `56px repeat(${days.length}, minmax(0, 1fr))`,
                }"
            >
                <div />
                <div
                    v-for="day in days"
                    :key="day"
                    class="border-l px-2 py-2 text-center"
                >
                    <p class="text-xs text-muted-foreground uppercase">
                        {{ formatters.weekday.format(parseLocal(day)) }}
                    </p>
                    <p
                        class="mx-auto mt-0.5 flex size-8 items-center justify-center rounded-full text-sm font-semibold"
                        :class="
                            day === today
                                ? 'bg-primary text-primary-foreground'
                                : ''
                        "
                    >
                        {{ parseLocal(day).getDate() }}
                    </p>
                </div>
            </div>

            <!-- Cuerpo -->
            <div
                class="grid"
                :style="{
                    gridTemplateColumns: `56px repeat(${days.length}, minmax(0, 1fr))`,
                }"
            >
                <!-- Horas -->
                <div class="relative" :style="{ height: `${height}px` }">
                    <span
                        v-for="hour in hours"
                        :key="hour"
                        class="absolute right-2 -translate-y-1/2 text-[11px] text-muted-foreground"
                        :style="{ top: `${top(hour)}px` }"
                    >
                        <template v-if="hour !== bounds.start">{{
                            minutesToTime(hour)
                        }}</template>
                    </span>
                </div>

                <!-- Columnas por día -->
                <div
                    v-for="day in days"
                    :key="day"
                    class="relative border-l bg-muted/60"
                    :class="canManage ? 'cursor-pointer' : ''"
                    :style="{ height: `${height}px` }"
                    :aria-label="
                        capitalize(formatters.long.format(parseLocal(day)))
                    "
                    @click="onColumnClick(day, $event)"
                >
                    <!-- Horario de atención -->
                    <div
                        v-for="(range, i) in workingRanges(day)"
                        :key="`w${i}`"
                        class="absolute inset-x-0 bg-background"
                        :style="{
                            top: `${range.top}px`,
                            height: `${range.height}px`,
                        }"
                    />

                    <!-- Líneas de hora -->
                    <div
                        v-for="hour in hours"
                        :key="`h${hour}`"
                        class="pointer-events-none absolute inset-x-0 border-t border-border/60"
                        :style="{ top: `${top(hour)}px` }"
                    />

                    <!-- Bloqueos -->
                    <button
                        v-for="item in blocksFor(day)"
                        :key="`b${item.block.id}`"
                        type="button"
                        class="absolute inset-x-0 z-10 flex items-start gap-1 overflow-hidden bg-[repeating-linear-gradient(135deg,transparent,transparent_6px,rgb(0_0_0/0.06)_6px,rgb(0_0_0/0.06)_12px)] px-2 py-1 text-left text-xs text-muted-foreground dark:bg-[repeating-linear-gradient(135deg,transparent,transparent_6px,rgb(255_255_255/0.06)_6px,rgb(255_255_255/0.06)_12px)]"
                        :style="{
                            top: `${item.top}px`,
                            height: `${item.height}px`,
                        }"
                        @click.stop="emit('selectBlock', item.block)"
                    >
                        <Ban class="mt-0.5 size-3 shrink-0" />
                        <span class="truncate">{{
                            item.block.reason ?? 'Bloqueado'
                        }}</span>
                    </button>

                    <!-- Citas -->
                    <button
                        v-for="event in eventsFor(day)"
                        :key="event.appointment.id"
                        type="button"
                        class="absolute z-10 overflow-hidden rounded-md border-l-4 px-2 py-1 text-left text-xs shadow-xs transition hover:brightness-95 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="
                            statusEventClasses[
                                statusColor(event.appointment.status)
                            ]
                        "
                        :style="{
                            top: `${event.top + 1}px`,
                            height: `${event.height - 2}px`,
                            left: `calc(${(event.column / event.columns) * 100}% + 2px)`,
                            width: `calc(${100 / event.columns}% - 4px)`,
                        }"
                        @click.stop="emit('select', event.appointment)"
                    >
                        <p class="truncate font-medium">
                            {{
                                event.appointment.patient?.full_name ??
                                'Paciente'
                            }}
                        </p>
                        <p class="truncate opacity-80">
                            {{ timeOf(event.appointment.starts_at) }}–{{
                                timeOf(event.appointment.ends_at)
                            }}
                            <template v-if="event.appointment.service">
                                · {{ event.appointment.service.name }}</template
                            >
                        </p>
                    </button>

                    <!-- Ahora -->
                    <div
                        v-if="day === today && nowTop !== null"
                        class="pointer-events-none absolute inset-x-0 z-20 border-t-2 border-red-500"
                        :style="{ top: `${nowTop}px` }"
                    >
                        <span
                            class="absolute -top-[5px] -left-[5px] size-2 rounded-full bg-red-500"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
