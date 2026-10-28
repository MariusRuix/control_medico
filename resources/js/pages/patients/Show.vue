<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Archive, Pencil, TriangleAlert } from '@lucide/vue';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import PatientHistories from '@/components/patients/PatientHistories.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { statusBadgeClasses } from '@/lib/agenda';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/format';
import { edit, index } from '@/routes/patients';
import type {
    AppointmentSummary,
    ConsultationSummary,
    Patient,
    PatientHistory,
    SelectOption,
} from '@/types';

const props = defineProps<{
    patient: Patient;
    histories: PatientHistory[] | null;
    criticalAllergies: string[];
    consultations: ConsultationSummary[] | null;
    appointments: AppointmentSummary[] | null;
    balance: string | null;
    historyTypes: SelectOption[];
    can: { update: boolean; delete: boolean; manageHistories: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pacientes', href: index() }],
    },
});

const identification: { label: string; value: () => string | null }[] = [
    {
        label: 'Fecha de nacimiento',
        value: () =>
            props.patient.birth_date
                ? formatDate(props.patient.birth_date)
                : null,
    },
    { label: 'Sexo', value: () => props.patient.sex_label },
    { label: 'Tipo de sangre', value: () => props.patient.blood_type },
    { label: 'CURP', value: () => props.patient.curp },
    { label: 'Ocupación', value: () => props.patient.occupation },
    { label: 'Teléfono', value: () => props.patient.phone },
    { label: 'Correo', value: () => props.patient.email },
    { label: 'Domicilio', value: () => props.patient.address },
];
</script>

<template>
    <Head :title="patient.full_name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Encabezado -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ patient.full_name }}
                    </h1>
                    <Badge variant="outline" class="font-mono">{{
                        patient.folio
                    }}</Badge>
                    <Badge v-if="patient.blood_type" variant="secondary">{{
                        patient.blood_type
                    }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    <template v-if="patient.age !== null"
                        >{{ patient.age }} años ·
                    </template>
                    <template v-if="patient.sex_label"
                        >{{ patient.sex_label }} ·
                    </template>
                    Registrado el {{ formatDate(patient.created_at) }}
                </p>
            </div>

            <div class="flex gap-2">
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="edit(patient.id)"
                        ><Pencil /> Editar ficha</Link
                    >
                </Button>
                <Dialog v-if="can.delete">
                    <DialogTrigger as-child>
                        <Button variant="ghost" class="text-destructive"
                            ><Archive /> Archivar</Button
                        >
                    </DialogTrigger>
                    <DialogContent>
                        <Form
                            v-bind="PatientController.destroy.form(patient.id)"
                            v-slot="{ processing }"
                        >
                            <DialogHeader>
                                <DialogTitle
                                    >¿Archivar a
                                    {{ patient.full_name }}?</DialogTitle
                                >
                                <DialogDescription>
                                    El paciente dejará de aparecer en el
                                    directorio y se cancelarán sus citas
                                    futuras. El expediente se conserva, como
                                    exige la NOM-004.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="mt-4 gap-2">
                                <DialogClose as-child>
                                    <Button type="button" variant="secondary"
                                        >Cancelar</Button
                                    >
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="processing"
                                >
                                    Archivar
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <!-- Alerta de alergias críticas -->
        <div
            v-if="criticalAllergies.length"
            role="alert"
            class="flex gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200"
        >
            <TriangleAlert class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-medium">Alergias críticas</p>
                <p class="text-sm">{{ criticalAllergies.join(' · ') }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Ficha -->
            <section class="space-y-4 rounded-xl border p-5 lg:col-span-1">
                <h2 class="text-base font-medium">Ficha de identificación</h2>
                <dl class="grid gap-3 text-sm">
                    <div v-for="row in identification" :key="row.label">
                        <dt class="text-muted-foreground">{{ row.label }}</dt>
                        <dd class="break-words">{{ row.value() ?? '—' }}</dd>
                    </div>
                </dl>

                <div class="border-t pt-4">
                    <h3 class="mb-2 text-sm font-medium">
                        Contacto de emergencia
                    </h3>
                    <p v-if="patient.emergency_name" class="text-sm">
                        {{ patient.emergency_name }}
                        <span
                            v-if="patient.emergency_relationship"
                            class="text-muted-foreground"
                        >
                            ({{ patient.emergency_relationship }})
                        </span>
                        <br />
                        {{ patient.emergency_phone ?? '' }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Sin registrar
                    </p>
                </div>

                <div v-if="patient.notes" class="border-t pt-4">
                    <h3 class="mb-2 text-sm font-medium">Notas</h3>
                    <p class="text-sm whitespace-pre-line">
                        {{ patient.notes }}
                    </p>
                </div>

                <div v-if="balance !== null" class="border-t pt-4">
                    <h3 class="mb-1 text-sm font-medium">Saldo pendiente</h3>
                    <p
                        class="text-lg font-semibold"
                        :class="
                            Number(balance) > 0
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ formatCurrency(balance) }}
                    </p>
                </div>
            </section>

            <div class="space-y-6 lg:col-span-2">
                <!-- Antecedentes (solo médico) -->
                <section
                    v-if="histories !== null"
                    class="rounded-xl border p-5"
                >
                    <PatientHistories
                        :patient-id="patient.id"
                        :histories="histories"
                        :types="historyTypes"
                        :can-manage="can.manageHistories"
                    />
                </section>

                <!-- Historial de consultas (solo médico) -->
                <section
                    v-if="consultations !== null"
                    class="space-y-4 rounded-xl border p-5"
                >
                    <h2 class="text-base font-medium">
                        Historial de consultas
                    </h2>
                    <p
                        v-if="consultations.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Sin consultas registradas.
                    </p>
                    <ol v-else class="relative space-y-4 border-l pl-5">
                        <li
                            v-for="consultation in consultations"
                            :key="consultation.id"
                            class="relative"
                        >
                            <span
                                class="absolute top-1.5 -left-[25px] size-2.5 rounded-full bg-primary"
                            />
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium">
                                    {{
                                        formatDateTime(
                                            consultation.consulted_at,
                                        )
                                    }}
                                </p>
                                <Badge
                                    v-if="!consultation.is_signed"
                                    variant="outline"
                                    >Borrador</Badge
                                >
                            </div>
                            <p v-if="consultation.reason" class="text-sm">
                                {{ consultation.reason }}
                            </p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Badge
                                    v-for="dx in consultation.diagnoses"
                                    :key="dx.code"
                                    :variant="
                                        dx.type === 'principal'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    :title="dx.description"
                                >
                                    {{ dx.code }}
                                </Badge>
                            </div>
                            <p
                                v-if="
                                    consultation.weight_kg ||
                                    consultation.blood_pressure
                                "
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                <template v-if="consultation.weight_kg">
                                    {{ consultation.weight_kg }} kg
                                    <template v-if="consultation.bmi"
                                        >(IMC {{ consultation.bmi }})</template
                                    >
                                </template>
                                <template v-if="consultation.blood_pressure">
                                    · TA {{ consultation.blood_pressure }}
                                </template>
                            </p>
                        </li>
                    </ol>
                </section>

                <!-- Citas -->
                <section
                    v-if="appointments !== null"
                    class="space-y-4 rounded-xl border p-5"
                >
                    <h2 class="text-base font-medium">Citas</h2>
                    <p
                        v-if="appointments.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Sin citas registradas.
                    </p>
                    <ul v-else class="divide-y">
                        <li
                            v-for="appointment in appointments"
                            :key="appointment.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                        >
                            <div>
                                <p class="font-medium">
                                    {{ formatDateTime(appointment.starts_at) }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ appointment.service ?? 'Cita' }}
                                    <template v-if="appointment.reason">
                                        · {{ appointment.reason }}</template
                                    >
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    statusBadgeClasses[
                                        appointment.status_color
                                    ] ?? statusBadgeClasses.zinc
                                "
                            >
                                {{ appointment.status_label }}
                            </span>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</template>
