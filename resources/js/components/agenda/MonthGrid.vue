<script setup lang="ts">
import { computed } from 'vue';
import { parseLocal, statusEventClasses, timeOf } from '@/lib/agenda';
import type {
    AppointmentStatusDefinition,
    CalendarAppointment,
    CalendarBlock,
} from '@/types';

const props = defineProps<{
    days: string[];
    month: string;
    today: string;
    appointments: CalendarAppointment[];
    blocks: CalendarBlock[];
    statuses: Record<string, AppointmentStatusDefinition>;
}>();

const emit = defineEmits<{
    day: [date: string];
    select: [appointment: CalendarAppointment];
}>();

const MAX_VISIBLE = 3;
const WEEKDAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

const byDay = computed(() => {
    const map = new Map<string, CalendarAppointment[]>();

    for (const a of props.appointments) {
        const day = a.starts_at.slice(0, 10);
        map.set(day, [...(map.get(day) ?? []), a]);
    }

    return map;
});

function isBlocked(day: string): boolean {
    const start = parseLocal(day).getTime();
    const end = start + 86_400_000;

    return props.blocks.some(
        (b) =>
            parseLocal(b.starts_at).getTime() <= start &&
            parseLocal(b.ends_at).getTime() >= end,
    );
}

function color(status: string): string {
    return props.statuses[status]?.color ?? 'zinc';
}
</script>

<template>
    <div class="overflow-x-auto rounded-xl border">
        <div class="min-w-[640px]">
            <div
                class="grid grid-cols-7 border-b bg-muted/40 text-center text-xs font-medium text-muted-foreground"
            >
                <div v-for="name in WEEKDAYS" :key="name" class="py-2">
                    {{ name }}
                </div>
            </div>
            <div class="grid grid-cols-7">
                <div
                    v-for="day in days"
                    :key="day"
                    class="min-h-28 cursor-pointer border-b border-l p-1.5 transition-colors first:border-l-0 hover:bg-muted/40 [&:nth-child(7n+1)]:border-l-0"
                    :class="[
                        day.slice(0, 7) !== month
                            ? 'bg-muted/30 text-muted-foreground'
                            : '',
                        isBlocked(day)
                            ? 'bg-[repeating-linear-gradient(135deg,transparent,transparent_6px,rgb(0_0_0/0.05)_6px,rgb(0_0_0/0.05)_12px)]'
                            : '',
                    ]"
                    @click="emit('day', day)"
                >
                    <p
                        class="mb-1 flex size-6 items-center justify-center rounded-full text-xs font-medium"
                        :class="
                            day === today
                                ? 'bg-primary text-primary-foreground'
                                : ''
                        "
                    >
                        {{ parseLocal(day).getDate() }}
                    </p>
                    <div class="space-y-0.5">
                        <button
                            v-for="a in (byDay.get(day) ?? []).slice(
                                0,
                                MAX_VISIBLE,
                            )"
                            :key="a.id"
                            type="button"
                            class="block w-full truncate rounded border-l-2 px-1 py-0.5 text-left text-[11px]"
                            :class="statusEventClasses[color(a.status)]"
                            @click.stop="emit('select', a)"
                        >
                            {{ timeOf(a.starts_at) }} {{ a.patient?.full_name }}
                        </button>
                        <p
                            v-if="(byDay.get(day)?.length ?? 0) > MAX_VISIBLE"
                            class="px-1 text-[11px] font-medium text-muted-foreground"
                        >
                            +{{
                                (byDay.get(day)?.length ?? 0) - MAX_VISIBLE
                            }}
                            más
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
