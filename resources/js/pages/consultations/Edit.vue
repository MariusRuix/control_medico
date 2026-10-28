<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn, useEventListener } from '@vueuse/core';
import {
    Check,
    FileSignature,
    LoaderCircle,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConsultationController from '@/actions/App/Http/Controllers/Clinical/ConsultationController';
import Cie10Picker from '@/components/clinical/Cie10Picker.vue';
import DiagnosisList from '@/components/clinical/DiagnosisList.vue';
import PatientContextPanel from '@/components/clinical/PatientContextPanel.vue';
import MedicalFilesPanel from '@/components/files/MedicalFilesPanel.vue';
import PrescriptionsPanel from '@/components/prescriptions/PrescriptionsPanel.vue';
import VitalSignsFields from '@/components/clinical/VitalSignsFields.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePermissions } from '@/composables/usePermissions';
import { formatDateTime } from '@/lib/format';
import { index as patientsIndex, show as patientShow } from '@/routes/patients';
import type {
    Cie10Result,
    ConsultationPageProps,
    DiagnosisEntry,
    SoapNote,
    VitalSigns,
} from '@/types';

const props = defineProps<ConsultationPageProps>();

const { can } = usePermissions();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pacientes', href: patientsIndex() }],
    },
});

const c = props.consultation;

const form = useForm({
    note_template_id: c.note_template_id,
    weight_kg: c.weight_kg,
    height_cm: c.height_cm,
    systolic_bp: c.systolic_bp,
    diastolic_bp: c.diastolic_bp,
    heart_rate: c.heart_rate,
    respiratory_rate: c.respiratory_rate,
    temperature_c: c.temperature_c,
    oxygen_saturation: c.oxygen_saturation,
    glucose_mg_dl: c.glucose_mg_dl,
    reason: c.reason ?? '',
    subjective: c.subjective ?? '',
    objective: c.objective ?? '',
    assessment: c.assessment ?? '',
    plan: c.plan ?? '',
    diagnoses: c.diagnoses as DiagnosisEntry[],
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

// Signos vitales como un solo objeto para el componente.
const vitals = computed<VitalSigns>({
    get: () => ({
        weight_kg: form.weight_kg,
        height_cm: form.height_cm,
        systolic_bp: form.systolic_bp,
        diastolic_bp: form.diastolic_bp,
        heart_rate: form.heart_rate,
        respiratory_rate: form.respiratory_rate,
        temperature_c: form.temperature_c,
        oxygen_saturation: form.oxygen_saturation,
        glucose_mg_dl: form.glucose_mg_dl,
    }),
    set: (value) => Object.assign(form, value),
});

// --- Guardado automático ---------------------------------------------------
const lastSavedAt = ref<Date | null>(null);
const saveFailed = ref(false);
const dirtySinceSave = ref(false);

function payload() {
    return {
        ...form.data(),
        diagnoses: form.diagnoses.map((d) => ({
            cie10_code_id: d.cie10_code_id,
            type: d.type,
            notes: d.notes,
        })),
    };
}

function save(): void {
    if (form.processing) {
        dirtySinceSave.value = true;

        return;
    }

    dirtySinceSave.value = false;

    form.transform(() => payload()).put(
        ConsultationController.update.url(c.id),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                lastSavedAt.value = new Date();
                saveFailed.value = false;
            },
            onError: () => (saveFailed.value = true),
            onFinish: () => {
                if (dirtySinceSave.value) {
                    autosave();
                }
            },
        },
    );
}

const autosave = useDebounceFn(save, 2000);

watch(
    () => form.data(),
    () => autosave(),
    { deep: true },
);

useEventListener('keydown', (event: KeyboardEvent) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        save();
    }
});

const saveStatus = computed(() => {
    if (form.processing) return 'Guardando…';
    if (saveFailed.value) return 'Revisa los campos marcados';
    if (lastSavedAt.value) {
        return `Guardado ${lastSavedAt.value.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}`;
    }

    return 'Borrador';
});

// --- Plantillas ----------------------------------------------------------
const soapKeys: (keyof SoapNote)[] = [
    'reason',
    'subjective',
    'objective',
    'assessment',
    'plan',
];

function applyTemplate(event: Event): void {
    const id = Number((event.target as HTMLSelectElement).value);
    const template = props.templates.find((t) => t.id === id);

    if (!template) {
        return;
    }

    // Solo llena los campos vacíos para no perder lo ya escrito.
    for (const key of soapKeys) {
        const value = template.structure[key];

        if (value && !String(form[key] ?? '').trim()) {
            form[key] = value;
        }
    }

    form.note_template_id = template.id;
}

// --- Diagnósticos ----------------------------------------------------------
function addDiagnosis(code: Cie10Result): void {
    form.diagnoses = [
        ...form.diagnoses,
        {
            cie10_code_id: code.id,
            code: code.code,
            description: code.description,
            type: form.diagnoses.some((d) => d.type === 'principal')
                ? 'secundario'
                : 'principal',
            notes: null,
        },
    ];
}

// --- Firmar / descartar ----------------------------------------------------
const signOpen = ref(false);
const discardOpen = ref(false);

function sign(): void {
    form.transform(() => payload()).post(
        ConsultationController.sign.url(c.id),
        {
            preserveScroll: true,
            onError: () => (signOpen.value = false),
        },
    );
}

function discard(): void {
    router.delete(ConsultationController.destroy.url(c.id));
}

const soapFields: {
    key: keyof SoapNote;
    label: string;
    hint: string;
    rows: number;
}[] = [
    {
        key: 'subjective',
        label: 'S · Subjetivo',
        hint: 'Padecimiento actual, síntomas, evolución desde la última consulta.',
        rows: 5,
    },
    {
        key: 'objective',
        label: 'O · Objetivo',
        hint: 'Exploración física y resultados de estudios.',
        rows: 5,
    },
    {
        key: 'assessment',
        label: 'A · Análisis',
        hint: 'Integración diagnóstica y pronóstico.',
        rows: 3,
    },
    {
        key: 'plan',
        label: 'P · Plan',
        hint: 'Tratamiento, estudios solicitados, indicaciones y próxima cita.',
        rows: 4,
    },
];
</script>

<template>
    <Head :title="`Consulta · ${patient.full_name}`" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <!-- Encabezado -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    <Link
                        :href="patientShow(patient.id)"
                        class="hover:underline"
                        >{{ patient.full_name }}</Link
                    >
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ patient.folio }}
                    <template v-if="patient.age !== null">
                        · {{ patient.age }} años</template
                    >
                    <template v-if="patient.sex_label">
                        · {{ patient.sex_label }}</template
                    >
                    <template v-if="patient.blood_type">
                        · {{ patient.blood_type }}</template
                    >
                    · Consulta del
                    {{ formatDateTime(consultation.consulted_at) }}
                    <template v-if="consultation.appointment?.service">
                        · {{ consultation.appointment.service }}</template
                    >
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    aria-live="polite"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="size-3.5 animate-spin"
                    />
                    <Check
                        v-else-if="lastSavedAt && !saveFailed"
                        class="size-3.5 text-emerald-600"
                    />
                    <TriangleAlert
                        v-else-if="saveFailed"
                        class="size-3.5 text-destructive"
                    />
                    {{ saveStatus }}
                </span>
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-destructive"
                    @click="discardOpen = true"
                >
                    <Trash2 /> Descartar
                </Button>
                <Button size="sm" @click="signOpen = true"
                    ><FileSignature /> Firmar nota</Button
                >
            </div>
        </div>

        <div
            v-if="criticalAllergies.length"
            role="alert"
            class="flex gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200"
        >
            <TriangleAlert class="mt-0.5 size-5 shrink-0" />
            <p class="text-sm">
                <span class="font-medium">Alergias críticas:</span>
                {{ criticalAllergies.join(' · ') }}
            </p>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-6">
                <section class="space-y-3 rounded-xl border p-5">
                    <h2 class="text-base font-medium">Signos vitales</h2>
                    <VitalSignsFields v-model="vitals" :errors="errors" />
                </section>

                <section class="space-y-4 rounded-xl border p-5">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2 class="text-base font-medium">
                            Nota de evolución (SOAP)
                        </h2>
                        <select
                            v-if="templates.length"
                            class="h-8 rounded-md border border-input bg-transparent px-2 text-sm dark:bg-input/30"
                            aria-label="Aplicar plantilla"
                            @change="applyTemplate"
                        >
                            <option value="">Aplicar plantilla…</option>
                            <option
                                v-for="t in templates"
                                :key="t.id"
                                :value="t.id"
                            >
                                {{ t.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-1.5">
                        <label for="reason" class="text-sm font-medium"
                            >Motivo de consulta</label
                        >
                        <input
                            id="reason"
                            v-model="form.reason"
                            maxlength="1000"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                        />
                        <InputError :message="errors.reason" />
                    </div>

                    <div
                        v-for="field in soapFields"
                        :key="field.key"
                        class="grid gap-1.5"
                    >
                        <label :for="field.key" class="text-sm font-medium">{{
                            field.label
                        }}</label>
                        <textarea
                            :id="field.key"
                            v-model="form[field.key]"
                            :rows="field.rows"
                            :placeholder="field.hint"
                            :aria-invalid="!!errors[field.key] || undefined"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base leading-relaxed shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                        />
                        <InputError :message="errors[field.key]" />
                    </div>
                </section>

                <section class="space-y-3 rounded-xl border p-5">
                    <h2 class="text-base font-medium">Diagnósticos (CIE-10)</h2>
                    <Cie10Picker
                        :exclude-ids="
                            form.diagnoses.map((d) => d.cie10_code_id)
                        "
                        @pick="addDiagnosis"
                    />
                    <DiagnosisList v-model="form.diagnoses" />
                    <InputError :message="errors.diagnoses" />
                </section>

                <PrescriptionsPanel
                    :consultation-id="consultation.id"
                    :prescriptions="prescriptions"
                    :favorites="favorites"
                    :critical-allergies="criticalAllergies"
                    :prescriber-missing="prescriberMissing"
                    :patient-has-email="patient.has_email"
                />

                <MedicalFilesPanel
                    :patient-id="patient.id"
                    :consultation-id="consultation.id"
                    :files="files"
                    :types="fileTypes"
                    :can-upload="can('medical-files.upload')"
                    :can-view="can('medical-files.view')"
                    :can-delete="can('medical-files.delete')"
                />

                <p class="text-xs text-muted-foreground">
                    Los cambios se guardan solos (o con Ctrl+S). La nota queda
                    cifrada y, una vez firmada, ya no se puede editar.
                </p>
            </div>

            <aside
                class="rounded-xl border p-5 xl:sticky xl:top-4 xl:max-h-[calc(100vh-2rem)] xl:self-start xl:overflow-y-auto"
            >
                <PatientContextPanel
                    :histories="histories"
                    :history-types="historyTypes"
                    :previous="previous"
                    :trend="trend"
                />
            </aside>
        </div>
    </div>

    <Dialog v-model:open="signOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>¿Firmar la nota?</DialogTitle>
                <DialogDescription>
                    Al firmar, la nota queda cerrada y ya no se puede modificar.
                    La cita se marcará como finalizada.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Seguir editando</Button>
                </DialogClose>
                <Button :disabled="form.processing" @click="sign"
                    ><FileSignature /> Firmar</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="discardOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>¿Descartar este borrador?</DialogTitle>
                <DialogDescription>
                    Se eliminará la nota sin firmar. Si viene de una cita, la
                    cita regresa a "Confirmada".
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancelar</Button>
                </DialogClose>
                <Button variant="destructive" @click="discard"
                    >Descartar</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
