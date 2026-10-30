<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { CalendarClock, Phone, Stethoscope, Wallet } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Agenda/AppointmentController';
import ConsultationController from '@/actions/App/Http/Controllers/Clinical/ConsultationController';
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
import {
    capitalize,
    formatters,
    parseLocal,
    statusBadgeClasses,
    timeOf,
} from '@/lib/agenda';
import {
    edit as consultationEdit,
    show as consultationShow,
} from '@/routes/consultations';
import { show as patientShow } from '@/routes/patients';
import type { AppointmentStatusDefinition, CalendarAppointment } from '@/types';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    appointment: CalendarAppointment | null;
    statuses: Record<string, AppointmentStatusDefinition>;
    canManage: boolean;
    canViewPatients: boolean;
    canConsult: boolean;
    canCharge: boolean;
}>();

const emit = defineEmits<{
    edit: [appointment: CalendarAppointment];
    charge: [appointment: CalendarAppointment];
}>();

const cancelling = ref(false);
const cancelReason = ref('');
const errors = ref<Record<string, string>>({});
const processing = ref(false);

watch(open, () => {
    cancelling.value = false;
    cancelReason.value = '';
    errors.value = {};
});

const status = computed(() =>
    props.appointment ? props.statuses[props.appointment.status] : null,
);

const transitions = computed(() =>
    (status.value?.transitions ?? [])
        .filter((value) => value !== 'cancelada')
        .map((value) => props.statuses[value])
        .filter(Boolean),
);

const canCancel = computed(
    () => status.value?.transitions.includes('cancelada') ?? false,
);
const canStart = computed(() =>
    ['agendada', 'confirmada', 'en_consultorio'].includes(
        props.appointment?.status ?? '',
    ),
);

function startConsultation(): void {
    if (!props.appointment) {
        return;
    }

    router.post(
        ConsultationController.startFromAppointment.url(props.appointment.id),
        {},
        {
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onError: (e) => (errors.value = e),
        },
    );
}

const isFinal = computed(() => (status.value?.transitions.length ?? 0) === 0);

const actionLabels: Record<string, string> = {
    agendada: 'Regresar a agendada',
    confirmada: 'Confirmar',
    en_consultorio: 'Pasar a consultorio',
    finalizada: 'Finalizar',
    no_asistio: 'No asistió',
};

function changeStatus(value: string): void {
    if (!props.appointment) {
        return;
    }

    router.patch(
        AppointmentController.status.url(props.appointment.id),
        {
            status: value,
            cancel_reason: value === 'cancelada' ? cancelReason.value : null,
        },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => (open.value = false),
            onError: (e) => (errors.value = e),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="appointment" class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex flex-wrap items-center gap-2">
                    {{ appointment.patient?.full_name ?? 'Paciente' }}
                    <span
                        v-if="status"
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="statusBadgeClasses[status.color]"
                    >
                        {{ status.label }}
                    </span>
                </DialogTitle>
                <DialogDescription class="flex items-center gap-1.5">
                    <CalendarClock class="size-4" />
                    {{
                        capitalize(
                            formatters.long.format(
                                parseLocal(appointment.starts_at),
                            ),
                        )
                    }}, {{ timeOf(appointment.starts_at) }}–{{
                        timeOf(appointment.ends_at)
                    }}
                </DialogDescription>
            </DialogHeader>

            <dl class="grid gap-3 text-sm">
                <div v-if="appointment.service">
                    <dt class="text-muted-foreground">Tipo de consulta</dt>
                    <dd>{{ appointment.service.name }}</dd>
                </div>
                <div v-if="appointment.reason">
                    <dt class="text-muted-foreground">Motivo</dt>
                    <dd>{{ appointment.reason }}</dd>
                </div>
                <div v-if="appointment.notes">
                    <dt class="text-muted-foreground">Notas</dt>
                    <dd class="whitespace-pre-line">{{ appointment.notes }}</dd>
                </div>
                <div v-if="appointment.cancel_reason">
                    <dt class="text-muted-foreground">Motivo de cancelación</dt>
                    <dd>{{ appointment.cancel_reason }}</dd>
                </div>
                <div
                    v-if="appointment.patient"
                    class="flex flex-wrap items-center gap-3"
                >
                    <span class="font-mono text-xs text-muted-foreground">{{
                        appointment.patient.folio
                    }}</span>
                    <a
                        v-if="appointment.patient.phone"
                        :href="`tel:${appointment.patient.phone}`"
                        class="inline-flex items-center gap-1 hover:underline"
                    >
                        <Phone class="size-3.5" />
                        {{ appointment.patient.phone }}
                    </a>
                    <Link
                        v-if="canViewPatients"
                        :href="patientShow(appointment.patient.id)"
                        class="text-sm font-medium underline-offset-4 hover:underline"
                    >
                        Ver expediente
                    </Link>
                </div>
            </dl>

            <div
                v-if="canConsult && (appointment.consultation || canStart)"
                class="rounded-lg bg-muted/50 p-3"
            >
                <Button v-if="appointment.consultation" class="w-full" as-child>
                    <Link
                        :href="
                            appointment.consultation.is_signed
                                ? consultationShow(appointment.consultation.id)
                                : consultationEdit(appointment.consultation.id)
                        "
                    >
                        <Stethoscope />
                        {{
                            appointment.consultation.is_signed
                                ? 'Ver nota médica'
                                : 'Continuar consulta'
                        }}
                    </Link>
                </Button>
                <Button
                    v-else
                    class="w-full"
                    :disabled="processing"
                    @click="startConsultation"
                >
                    <Stethoscope /> Iniciar consulta
                </Button>
            </div>

            <Button
                v-if="
                    canCharge &&
                    appointment.patient &&
                    appointment.status !== 'cancelada'
                "
                variant="outline"
                class="w-full"
                @click="emit('charge', appointment)"
            >
                <Wallet /> Registrar cobro
            </Button>

            <template v-if="canManage && !isFinal">
                <div v-if="cancelling" class="space-y-2 rounded-lg border p-3">
                    <label for="cancel_reason" class="text-sm font-medium"
                        >Motivo de la cancelación *</label
                    >
                    <Input
                        id="cancel_reason"
                        v-model="cancelReason"
                        maxlength="255"
                        placeholder="Ej. El paciente pidió reprogramar"
                        autofocus
                    />
                    <InputError :message="errors.cancel_reason" />
                    <div class="flex justify-end gap-2">
                        <Button
                            size="sm"
                            variant="secondary"
                            @click="cancelling = false"
                            >Volver</Button
                        >
                        <Button
                            size="sm"
                            variant="destructive"
                            :disabled="processing || !cancelReason.trim()"
                            @click="changeStatus('cancelada')"
                        >
                            Cancelar cita
                        </Button>
                    </div>
                </div>

                <InputError :message="errors.status" />

                <DialogFooter
                    v-if="!cancelling"
                    class="flex-wrap gap-2 sm:justify-between"
                >
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="next in transitions"
                            :key="next.value"
                            size="sm"
                            :variant="
                                next.value === 'no_asistio' ||
                                next.value === 'agendada'
                                    ? 'outline'
                                    : 'default'
                            "
                            :disabled="processing"
                            @click="changeStatus(next.value)"
                        >
                            {{ actionLabels[next.value] ?? next.label }}
                        </Button>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            @click="emit('edit', appointment)"
                            >Reprogramar</Button
                        >
                        <Button
                            v-if="canCancel"
                            size="sm"
                            variant="ghost"
                            class="text-destructive"
                            @click="cancelling = true"
                        >
                            Cancelar
                        </Button>
                    </div>
                </DialogFooter>
            </template>
        </DialogContent>
    </Dialog>
</template>
