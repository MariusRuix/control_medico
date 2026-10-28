<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Agenda/AppointmentController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { timeOf } from '@/lib/agenda';
import { availability } from '@/routes/appointments';
import type {
    AgendaService,
    CalendarAppointment,
    CalendarView,
    PatientSearchResult,
} from '@/types';
import PatientPicker from './PatientPicker.vue';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    appointment: CalendarAppointment | null;
    initial: { date: string; time: string } | null;
    services: AgendaService[];
    slotMinutes: number;
    view: CalendarView;
}>();

const patient = ref<PatientSearchResult | null>(null);
const slots = ref<string[]>([]);
const loadingSlots = ref(false);

const form = useForm({
    patient_id: null as number | null,
    service_id: null as number | null,
    date: '',
    time: '',
    duration_minutes: 30,
    reason: '',
    notes: '',
    allow_outside_hours: false,
});

const isEdit = computed(() => props.appointment !== null);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    const a = props.appointment;

    if (a) {
        patient.value = a.patient ? { ...a.patient, age: null } : null;
        form.service_id = a.service?.id ?? null;
        form.date = a.starts_at.slice(0, 10);
        form.time = timeOf(a.starts_at);
        form.duration_minutes = a.duration_minutes;
        form.reason = a.reason ?? '';
        form.notes = a.notes ?? '';
    } else {
        patient.value = null;
        const firstService = props.services[0] ?? null;
        form.service_id = firstService?.id ?? null;
        form.date = props.initial?.date ?? '';
        form.time = props.initial?.time ?? '';
        form.duration_minutes =
            firstService?.duration_minutes ?? props.slotMinutes;
        form.reason = '';
        form.notes = '';
    }

    form.allow_outside_hours = false;
    void loadSlots();
});

watch(patient, (value) => (form.patient_id = value?.id ?? null));

function onServiceChange(): void {
    const service = props.services.find((s) => s.id === form.service_id);

    if (service?.duration_minutes) {
        form.duration_minutes = service.duration_minutes;
    }
}

const loadSlots = useDebounceFn(async () => {
    if (!form.date || !form.duration_minutes) {
        slots.value = [];

        return;
    }

    loadingSlots.value = true;

    try {
        const url = availability.url({
            query: {
                date: form.date,
                duration_minutes: form.duration_minutes,
                ...(props.appointment ? { ignore: props.appointment.id } : {}),
            },
        });
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        slots.value = response.ok
            ? ((await response.json()) as { slots: string[] }).slots
            : [];
    } catch {
        slots.value = [];
    } finally {
        loadingSlots.value = false;
    }
}, 200);

watch(
    () => [form.date, form.duration_minutes],
    () => void loadSlots(),
);

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.appointment) {
        form.put(
            AppointmentController.update.url(props.appointment.id),
            options,
        );
    } else {
        form.post(
            AppointmentController.store.url({ query: { view: props.view } }),
            options,
        );
    }
}

const scheduleError = computed(
    () => form.errors.time ?? (form.errors as Record<string, string>).starts_at,
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        isEdit ? 'Reprogramar cita' : 'Nueva cita'
                    }}</DialogTitle>
                    <DialogDescription>
                        Los horarios disponibles respetan el horario de
                        atención, bloqueos y otras citas.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label>Paciente *</Label>
                    <PatientPicker
                        v-model="patient"
                        :invalid="!!form.errors.patient_id"
                        :disabled="isEdit"
                    />
                    <InputError :message="form.errors.patient_id" />
                </div>

                <div class="grid gap-4 sm:grid-cols-[1fr_120px]">
                    <div class="grid gap-2">
                        <Label for="service_id">Tipo de consulta</Label>
                        <select
                            id="service_id"
                            v-model="form.service_id"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                            @change="onServiceChange"
                        >
                            <option :value="null">Sin especificar</option>
                            <option
                                v-for="service in services"
                                :key="service.id"
                                :value="service.id"
                            >
                                {{ service.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.service_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="duration_minutes">Duración (min)</Label>
                        <Input
                            id="duration_minutes"
                            v-model.number="form.duration_minutes"
                            type="number"
                            min="5"
                            max="480"
                            step="5"
                        />
                        <InputError :message="form.errors.duration_minutes" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="date">Fecha *</Label>
                        <Input
                            id="date"
                            v-model="form.date"
                            type="date"
                            required
                        />
                        <InputError :message="form.errors.date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="time">Hora *</Label>
                        <Input
                            id="time"
                            v-model="form.time"
                            type="time"
                            step="300"
                            required
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="flex items-center gap-2 text-sm font-medium">
                        Horarios libres
                        <LoaderCircle
                            v-if="loadingSlots"
                            class="size-3.5 animate-spin text-muted-foreground"
                        />
                    </p>
                    <div v-if="slots.length" class="flex flex-wrap gap-1.5">
                        <button
                            v-for="slot in slots"
                            :key="slot"
                            type="button"
                            class="rounded-md border px-2.5 py-1 text-xs tabular-nums transition-colors hover:bg-accent"
                            :class="
                                slot === form.time
                                    ? 'border-primary bg-primary text-primary-foreground hover:bg-primary'
                                    : ''
                            "
                            @click="form.time = slot"
                        >
                            {{ slot }}
                        </button>
                    </div>
                    <p
                        v-else-if="!loadingSlots && form.date"
                        class="text-xs text-muted-foreground"
                    >
                        No hay horarios libres para esta fecha y duración.
                    </p>
                    <InputError :message="scheduleError" />
                    <label
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <input
                            v-model="form.allow_outside_hours"
                            type="checkbox"
                            class="size-3.5"
                        />
                        Permitir fuera del horario de atención (sobrecupo)
                    </label>
                </div>

                <div class="grid gap-2">
                    <Label for="reason">Motivo</Label>
                    <Input
                        id="reason"
                        v-model="form.reason"
                        maxlength="255"
                        placeholder="Ej. Revisión de estudios"
                    />
                    <InputError :message="form.errors.reason" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Notas para recepción</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="2"
                        maxlength="500"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.patient_id"
                    >
                        {{ isEdit ? 'Guardar cambios' : 'Agendar cita' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
