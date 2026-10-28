<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Ban, ChevronLeft, ChevronRight, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppointmentDetailsDialog from '@/components/agenda/AppointmentDetailsDialog.vue';
import AppointmentFormDialog from '@/components/agenda/AppointmentFormDialog.vue';
import MonthGrid from '@/components/agenda/MonthGrid.vue';
import ScheduleBlockDialog from '@/components/agenda/ScheduleBlockDialog.vue';
import TimeGrid from '@/components/agenda/TimeGrid.vue';
import { Button } from '@/components/ui/button';
import {
    addDays,
    addMonths,
    capitalize,
    daysBetween,
    formatters,
    parseLocal,
    statusBadgeClasses,
} from '@/lib/agenda';
import { index } from '@/routes/appointments';
import type {
    AgendaService,
    AppointmentStatusDefinition,
    CalendarAppointment,
    CalendarBlock,
    CalendarView,
    WorkingHours,
} from '@/types';

const props = defineProps<{
    view: CalendarView;
    date: string;
    range: { from: string; to: string };
    today: string;
    appointments: CalendarAppointment[];
    blocks: CalendarBlock[];
    workingHours: WorkingHours;
    slotMinutes: number;
    services: AgendaService[];
    statuses: AppointmentStatusDefinition[];
    can: { manage: boolean; manageBlocks: boolean; viewPatients: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Agenda', href: index() }],
    },
});

const VIEWS: { value: CalendarView; label: string }[] = [
    { value: 'day', label: 'Día' },
    { value: 'week', label: 'Semana' },
    { value: 'month', label: 'Mes' },
];

const statusMap = computed(
    () =>
        Object.fromEntries(props.statuses.map((s) => [s.value, s])) as Record<
            string,
            AppointmentStatusDefinition
        >,
);

const days = computed(() => daysBetween(props.range.from, props.range.to));

const title = computed(() => {
    const date = parseLocal(props.date);

    if (props.view === 'day') {
        return capitalize(formatters.long.format(date));
    }

    if (props.view === 'month') {
        return capitalize(formatters.monthYear.format(date));
    }

    const first = parseLocal(props.range.from);
    const last = parseLocal(addDays(props.range.to, -1));

    return `${formatters.dayMonth.format(first)} – ${formatters.dayMonth.format(last)} ${last.getFullYear()}`;
});

const counts = computed(() =>
    props.statuses
        .map((s) => ({
            ...s,
            total: props.appointments.filter((a) => a.status === s.value)
                .length,
        }))
        .filter((s) => s.total > 0),
);

function visit(view: CalendarView, date: string): void {
    router.get(
        index.url({ query: { view, date } }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function shift(direction: 1 | -1): void {
    const next =
        props.view === 'month'
            ? addMonths(props.date, direction)
            : addDays(
                  props.date,
                  props.view === 'week' ? 7 * direction : direction,
              );

    visit(props.view, next);
}

// Diálogos
const formOpen = ref(false);
const editing = ref<CalendarAppointment | null>(null);
const initialSlot = ref<{ date: string; time: string } | null>(null);
const detailsOpen = ref(false);
const selected = ref<CalendarAppointment | null>(null);
const blockOpen = ref(false);
const selectedBlock = ref<CalendarBlock | null>(null);

function openCreate(date?: string, time?: string): void {
    editing.value = null;
    initialSlot.value = {
        date: date ?? (props.date < props.today ? props.today : props.date),
        time: time ?? '',
    };
    formOpen.value = true;
}

function openDetails(appointment: CalendarAppointment): void {
    selected.value = appointment;
    detailsOpen.value = true;
}

function openEdit(appointment: CalendarAppointment): void {
    detailsOpen.value = false;
    editing.value = appointment;
    formOpen.value = true;
}

function openBlock(block: CalendarBlock | null): void {
    selectedBlock.value = block;
    blockOpen.value = true;
}
</script>

<template>
    <Head title="Agenda" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <!-- Barra de herramientas -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" @click="visit(view, today)"
                    >Hoy</Button
                >
                <div class="flex">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Anterior"
                        @click="shift(-1)"
                    >
                        <ChevronLeft />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Siguiente"
                        @click="shift(1)"
                    >
                        <ChevronRight />
                    </Button>
                </div>
                <h1 class="text-lg font-semibold tracking-tight">
                    {{ title }}
                </h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div
                    class="inline-flex rounded-md border p-0.5"
                    role="tablist"
                    aria-label="Vista"
                >
                    <button
                        v-for="option in VIEWS"
                        :key="option.value"
                        type="button"
                        role="tab"
                        :aria-selected="view === option.value"
                        class="rounded px-3 py-1 text-sm transition-colors"
                        :class="
                            view === option.value
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="visit(option.value, date)"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <Button
                    v-if="can.manageBlocks"
                    variant="outline"
                    size="sm"
                    @click="openBlock(null)"
                >
                    <Ban /> Bloquear
                </Button>
                <Button v-if="can.manage" size="sm" @click="openCreate()">
                    <Plus /> Nueva cita
                </Button>
            </div>
        </div>

        <!-- Resumen por estatus -->
        <div v-if="counts.length" class="flex flex-wrap gap-2 text-xs">
            <span
                v-for="item in counts"
                :key="item.value"
                class="rounded-full px-2 py-0.5 font-medium"
                :class="statusBadgeClasses[item.color]"
            >
                {{ item.label }}: {{ item.total }}
            </span>
        </div>

        <MonthGrid
            v-if="view === 'month'"
            :days="days"
            :month="date.slice(0, 7)"
            :today="today"
            :appointments="appointments"
            :blocks="blocks"
            :statuses="statusMap"
            @day="(d) => visit('day', d)"
            @select="openDetails"
        />
        <TimeGrid
            v-else
            :days="days"
            :today="today"
            :appointments="appointments"
            :blocks="blocks"
            :working-hours="workingHours"
            :slot-minutes="slotMinutes"
            :statuses="statusMap"
            :can-manage="can.manage"
            @slot="(d, t) => openCreate(d, t)"
            @select="openDetails"
            @select-block="openBlock"
        />

        <p
            v-if="can.manage && view !== 'month'"
            class="text-xs text-muted-foreground"
        >
            Haz clic en un espacio libre para agendar. Las zonas grises están
            fuera del horario de atención.
        </p>
    </div>

    <AppointmentFormDialog
        v-model:open="formOpen"
        :appointment="editing"
        :initial="initialSlot"
        :services="services"
        :slot-minutes="slotMinutes"
        :view="view"
    />
    <AppointmentDetailsDialog
        v-model:open="detailsOpen"
        :appointment="selected"
        :statuses="statusMap"
        :can-manage="can.manage"
        :can-view-patients="can.viewPatients"
        @edit="openEdit"
    />
    <ScheduleBlockDialog
        v-model:open="blockOpen"
        :block="selectedBlock"
        :date="date < today ? today : date"
        :can-manage="can.manageBlocks"
    />
</template>
