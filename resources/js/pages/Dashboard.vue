<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarPlus,
    FileClock,
    Settings,
    Stethoscope,
    TriangleAlert,
    UserPlus,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Agenda/AppointmentController';
import ConsultationController from '@/actions/App/Http/Controllers/Clinical/ConsultationController';
import AppointmentDetailsDialog from '@/components/agenda/AppointmentDetailsDialog.vue';
import ChargeDialog from '@/components/billing/ChargeDialog.vue';
import { Button } from '@/components/ui/button';
import {
    capitalize,
    formatters,
    parseLocal,
    statusBadgeClasses,
    timeOf,
} from '@/lib/agenda';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as appointmentsIndex } from '@/routes/appointments';
import { index as cashIndex } from '@/routes/cash';
import {
    edit as consultationEdit,
    show as consultationShow,
} from '@/routes/consultations';
import { create as patientCreate } from '@/routes/patients';
import { edit as clinicSettings } from '@/routes/settings/clinic';
import { index as backupsIndex } from '@/routes/settings/backups';
import type {
    AppointmentStatusDefinition,
    BillingOptions,
    CalendarAppointment,
    ChargePatient,
} from '@/types';

const props = defineProps<{
    today: string;
    appointments: CalendarAppointment[] | null;
    stats: {
        total: number;
        pending: number;
        in_progress: number;
        completed: number;
        no_show: number;
        tomorrow: number;
    } | null;
    drafts:
        | {
              id: number;
              consulted_at: string;
              patient: string | null;
              folio: string | null;
          }[]
        | null;
    finance: {
        collected_today: string;
        outstanding_total: string;
        outstanding_count: number;
        cash_open: boolean;
        cash_opened_at: string | null;
    } | null;
    statuses: AppointmentStatusDefinition[];
    billing: BillingOptions | null;
    needsSetup: boolean;
    backupWarning: string | null;
    can: {
        manageAppointments: boolean;
        viewPatients: boolean;
        consult: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inicio', href: dashboard() }],
    },
});

const page = usePage();

const statusMap = computed(
    () =>
        Object.fromEntries(props.statuses.map((s) => [s.value, s])) as Record<
            string,
            AppointmentStatusDefinition
        >,
);

const greeting = computed(() => {
    const hour = new Date().getHours();
    const name = page.props.auth.user.name.split(' ').slice(0, 2).join(' ');
    const salute =
        hour < 12
            ? 'Buenos días'
            : hour < 19
              ? 'Buenas tardes'
              : 'Buenas noches';

    return `${salute}, ${name}`;
});

const dateLabel = computed(() =>
    capitalize(formatters.long.format(parseLocal(props.today))),
);

/** Próxima cita por atender (agendada o confirmada) a partir de ahora. */
const nextId = computed(() => {
    const now = new Date();

    return (
        props.appointments?.find(
            (a) =>
                ['agendada', 'confirmada'].includes(a.status) &&
                parseLocal(a.ends_at) > now,
        )?.id ?? null
    );
});

// --- Acciones rápidas -------------------------------------------------------
const busy = ref<number | null>(null);

function setStatus(appointment: CalendarAppointment, status: string): void {
    router.patch(
        AppointmentController.status.url(appointment.id),
        { status },
        {
            preserveScroll: true,
            onStart: () => (busy.value = appointment.id),
            onFinish: () => (busy.value = null),
        },
    );
}

function startConsultation(appointment: CalendarAppointment): void {
    router.post(
        ConsultationController.startFromAppointment.url(appointment.id),
        {},
        {
            onStart: () => (busy.value = appointment.id),
            onFinish: () => (busy.value = null),
        },
    );
}

// --- Diálogos -------------------------------------------------------------
const detailsOpen = ref(false);
const selected = ref<CalendarAppointment | null>(null);
const chargeOpen = ref(false);
const chargePatient = ref<ChargePatient | null>(null);
const chargeServiceId = ref<number | null>(null);

function openDetails(appointment: CalendarAppointment): void {
    selected.value = appointment;
    detailsOpen.value = true;
}

function openCharge(appointment: CalendarAppointment): void {
    if (!appointment.patient) {
        return;
    }

    detailsOpen.value = false;
    chargePatient.value = { ...appointment.patient, age: null };
    chargeServiceId.value = appointment.service?.id ?? null;
    chargeOpen.value = true;
}

function goToAgenda(): void {
    router.visit(
        appointmentsIndex.url({ query: { view: 'day', date: props.today } }),
    );
}
</script>

<template>
    <Head title="Inicio" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ greeting }}
                </h1>
                <p class="text-sm text-muted-foreground">{{ dateLabel }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="can.manageAppointments"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="appointmentsIndex()"
                        ><CalendarPlus /> Agendar cita</Link
                    >
                </Button>
                <Button
                    v-if="can.viewPatients && can.manageAppointments"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="patientCreate()"
                        ><UserPlus /> Nuevo paciente</Link
                    >
                </Button>
            </div>
        </div>

        <div
            v-if="needsSetup"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <p class="flex items-center gap-2">
                <TriangleAlert class="size-4 shrink-0" />
                Completa tus datos (cédula, institución, domicilio y firma) para
                poder emitir recetas.
            </p>
            <Button size="sm" variant="outline" as-child>
                <Link :href="clinicSettings()"><Settings /> Configurar</Link>
            </Button>
        </div>

        <div
            v-if="backupWarning"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <p class="flex items-center gap-2">
                <TriangleAlert class="size-4 shrink-0" />
                {{ backupWarning }} Revisa los respaldos para no perder
                información.
            </p>
            <Button size="sm" variant="outline" as-child>
                <Link :href="backupsIndex()"><Settings /> Respaldos</Link>
            </Button>
        </div>

        <!-- Indicadores -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <template v-if="stats">
                <div class="rounded-xl border p-4">
                    <p class="text-xs text-muted-foreground">Citas hoy</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ stats.total }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Mañana: {{ stats.tomorrow }}
                    </p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-xs text-muted-foreground">Por atender</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ stats.pending }}
                    </p>
                    <p
                        v-if="stats.no_show"
                        class="text-xs text-muted-foreground"
                    >
                        No asistieron: {{ stats.no_show }}
                    </p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-xs text-muted-foreground">En consultorio</p>
                    <p
                        class="mt-1 text-2xl font-semibold text-amber-600 tabular-nums"
                    >
                        {{ stats.in_progress }}
                    </p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-xs text-muted-foreground">Atendidas</p>
                    <p
                        class="mt-1 text-2xl font-semibold text-emerald-600 tabular-nums"
                    >
                        {{ stats.completed }}
                    </p>
                </div>
            </template>
            <template v-if="finance">
                <Link
                    :href="cashIndex()"
                    class="rounded-xl border p-4 transition-colors hover:bg-muted/40"
                >
                    <p class="text-xs text-muted-foreground">Cobrado hoy</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ formatCurrency(finance.collected_today) }}
                    </p>
                    <p
                        class="text-xs"
                        :class="
                            finance.cash_open
                                ? 'text-emerald-600'
                                : 'text-muted-foreground'
                        "
                    >
                        {{
                            finance.cash_open ? 'Caja abierta' : 'Caja cerrada'
                        }}
                    </p>
                </Link>
                <Link
                    :href="cashIndex()"
                    class="rounded-xl border p-4 transition-colors hover:bg-muted/40"
                >
                    <p class="text-xs text-muted-foreground">Adeudos</p>
                    <p
                        class="mt-1 text-2xl font-semibold tabular-nums"
                        :class="
                            finance.outstanding_count ? 'text-destructive' : ''
                        "
                    >
                        {{ formatCurrency(finance.outstanding_total) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ finance.outstanding_count }} cobro(s) pendientes
                    </p>
                </Link>
            </template>
        </div>

        <div
            class="grid gap-6"
            :class="drafts !== null ? 'xl:grid-cols-[minmax(0,1fr)_340px]' : ''"
        >
            <!-- Citas del día -->
            <section
                v-if="appointments !== null"
                class="space-y-3 rounded-xl border p-5"
            >
                <div class="flex items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2 text-base font-medium">
                        <CalendarDays class="size-4" /> Citas de hoy
                    </h2>
                    <Button variant="ghost" size="sm" @click="goToAgenda"
                        >Ver agenda</Button
                    >
                </div>

                <p
                    v-if="appointments.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    No hay citas para hoy.
                </p>

                <ul v-else class="divide-y rounded-lg border">
                    <li
                        v-for="a in appointments"
                        :key="a.id"
                        class="flex flex-wrap items-center gap-3 p-3 transition-colors"
                        :class="[
                            a.id === nextId ? 'bg-primary/5' : '',
                            ['cancelada', 'no_asistio'].includes(a.status)
                                ? 'opacity-60'
                                : '',
                        ]"
                    >
                        <button
                            type="button"
                            class="flex min-w-0 flex-1 items-center gap-3 text-left"
                            @click="openDetails(a)"
                        >
                            <span
                                class="w-12 shrink-0 text-sm font-semibold tabular-nums"
                                >{{ timeOf(a.starts_at) }}</span
                            >
                            <span class="min-w-0">
                                <span
                                    class="block truncate text-sm font-medium"
                                >
                                    {{ a.patient?.full_name ?? 'Paciente' }}
                                    <span
                                        v-if="a.id === nextId"
                                        class="ml-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-medium text-primary-foreground"
                                        >Siguiente</span
                                    >
                                </span>
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                >
                                    {{ a.service?.name ?? 'Cita'
                                    }}<template v-if="a.reason">
                                        · {{ a.reason }}</template
                                    >
                                </span>
                            </span>
                        </button>

                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="
                                statusBadgeClasses[
                                    statusMap[a.status]?.color ?? 'zinc'
                                ]
                            "
                        >
                            {{ statusMap[a.status]?.label ?? a.status }}
                        </span>

                        <div class="flex gap-1">
                            <Button
                                v-if="
                                    can.manageAppointments &&
                                    a.status === 'agendada'
                                "
                                size="sm"
                                variant="outline"
                                :disabled="busy === a.id"
                                @click="setStatus(a, 'confirmada')"
                            >
                                Confirmar
                            </Button>
                            <Button
                                v-if="
                                    can.manageAppointments &&
                                    !can.consult &&
                                    ['agendada', 'confirmada'].includes(
                                        a.status,
                                    )
                                "
                                size="sm"
                                :disabled="busy === a.id"
                                @click="setStatus(a, 'en_consultorio')"
                            >
                                Pasar a consultorio
                            </Button>
                            <template v-if="can.consult">
                                <Button
                                    v-if="a.consultation"
                                    size="sm"
                                    :variant="
                                        a.consultation.is_signed
                                            ? 'ghost'
                                            : 'default'
                                    "
                                    as-child
                                >
                                    <Link
                                        :href="
                                            a.consultation.is_signed
                                                ? consultationShow(
                                                      a.consultation.id,
                                                  )
                                                : consultationEdit(
                                                      a.consultation.id,
                                                  )
                                        "
                                    >
                                        <Stethoscope />
                                        {{
                                            a.consultation.is_signed
                                                ? 'Nota'
                                                : 'Continuar'
                                        }}
                                    </Link>
                                </Button>
                                <Button
                                    v-else-if="
                                        [
                                            'agendada',
                                            'confirmada',
                                            'en_consultorio',
                                        ].includes(a.status)
                                    "
                                    size="sm"
                                    :variant="
                                        a.status === 'en_consultorio'
                                            ? 'default'
                                            : 'outline'
                                    "
                                    :disabled="busy === a.id"
                                    @click="startConsultation(a)"
                                >
                                    <Stethoscope /> Consulta
                                </Button>
                            </template>
                            <Button
                                v-if="billing && a.status === 'finalizada'"
                                size="sm"
                                variant="ghost"
                                @click="openCharge(a)"
                            >
                                <Wallet /> Cobrar
                            </Button>
                        </div>
                    </li>
                </ul>
            </section>

            <aside
                v-if="
                    drafts !== null ||
                    (appointments === null && finance === null)
                "
                class="space-y-6"
            >
                <!-- Notas sin firmar -->
                <section
                    v-if="drafts !== null"
                    class="space-y-3 rounded-xl border p-5"
                >
                    <h2 class="flex items-center gap-2 text-base font-medium">
                        <FileClock class="size-4" /> Notas sin firmar
                    </h2>
                    <p
                        v-if="drafts.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Todas las notas están firmadas.
                    </p>
                    <ul v-else class="space-y-2">
                        <li v-for="d in drafts" :key="d.id">
                            <Link
                                :href="consultationEdit(d.id)"
                                class="flex items-center justify-between gap-2 rounded-lg border p-2.5 text-sm transition-colors hover:bg-muted/40"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{
                                        d.patient
                                    }}</span>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >{{
                                            formatDateTime(d.consulted_at)
                                        }}</span
                                    >
                                </span>
                                <span class="text-xs font-medium text-primary"
                                    >Continuar</span
                                >
                            </Link>
                        </li>
                    </ul>
                </section>

                <section
                    v-if="
                        appointments === null &&
                        drafts === null &&
                        finance === null
                    "
                    class="rounded-xl border p-5 text-sm text-muted-foreground"
                >
                    Tu usuario aún no tiene permisos asignados. Pide al médico
                    que te asigne un rol.
                </section>
            </aside>
        </div>
    </div>

    <AppointmentDetailsDialog
        v-model:open="detailsOpen"
        :appointment="selected"
        :statuses="statusMap"
        :can-manage="can.manageAppointments"
        :can-view-patients="can.viewPatients"
        :can-consult="can.consult"
        :can-charge="billing !== null"
        @edit="goToAgenda"
        @charge="openCharge"
    />
    <ChargeDialog
        v-if="billing"
        v-model:open="chargeOpen"
        :billing="billing"
        :patient="chargePatient"
        :initial-service-id="chargeServiceId"
    />
</template>
