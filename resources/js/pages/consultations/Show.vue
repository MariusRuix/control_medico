<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileSignature, Printer, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import AddendaSection from '@/components/clinical/AddendaSection.vue';
import DiagnosisList from '@/components/clinical/DiagnosisList.vue';
import PatientContextPanel from '@/components/clinical/PatientContextPanel.vue';
import MedicalFilesPanel from '@/components/files/MedicalFilesPanel.vue';
import PrescriptionsPanel from '@/components/prescriptions/PrescriptionsPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { formatDateTime } from '@/lib/format';
import { bmiCategory } from '@/lib/vitals';
import { edit } from '@/routes/consultations';
import { index as patientsIndex, show as patientShow } from '@/routes/patients';
import type { ConsultationPageProps } from '@/types';

const props = defineProps<ConsultationPageProps>();

const { can } = usePermissions();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pacientes', href: patientsIndex() }],
    },
});

const c = computed(() => props.consultation);

const vitals = computed(() =>
    [
        { label: 'Peso', value: c.value.weight_kg, unit: 'kg' },
        { label: 'Talla', value: c.value.height_cm, unit: 'cm' },
        {
            label: 'IMC',
            value: c.value.bmi,
            unit: bmiCategory(c.value.bmi) ?? '',
        },
        {
            label: 'TA',
            value: c.value.systolic_bp
                ? `${c.value.systolic_bp}/${c.value.diastolic_bp}`
                : null,
            unit: 'mmHg',
        },
        { label: 'FC', value: c.value.heart_rate, unit: 'lpm' },
        { label: 'FR', value: c.value.respiratory_rate, unit: 'rpm' },
        { label: 'Temp.', value: c.value.temperature_c, unit: '°C' },
        { label: 'SpO₂', value: c.value.oxygen_saturation, unit: '%' },
        { label: 'Glucosa', value: c.value.glucose_mg_dl, unit: 'mg/dL' },
    ].filter((v) => v.value !== null && v.value !== ''),
);

const sections = computed(() =>
    [
        { label: 'Subjetivo', value: c.value.subjective },
        { label: 'Objetivo', value: c.value.objective },
        { label: 'Análisis', value: c.value.assessment },
        { label: 'Plan', value: c.value.plan },
    ].filter((s) => s.value),
);

function print(): void {
    window.print();
}
</script>

<template>
    <Head :title="`Nota · ${patient.full_name}`" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        <Link
                            :href="patientShow(patient.id)"
                            class="hover:underline"
                            >{{ patient.full_name }}</Link
                        >
                    </h1>
                    <Badge v-if="consultation.signed_at" variant="secondary">
                        <FileSignature /> Firmada
                    </Badge>
                    <Badge v-else variant="outline">Borrador</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ patient.folio }}
                    <template v-if="patient.age !== null">
                        · {{ patient.age }} años</template
                    >
                    · Consulta del
                    {{ formatDateTime(consultation.consulted_at) }}
                    <template v-if="consultation.signed_at">
                        · Firmada {{ formatDateTime(consultation.signed_at) }}
                        <template v-if="consultation.author">
                            por {{ consultation.author }}</template
                        >
                    </template>
                </p>
            </div>
            <div class="flex gap-2 print:hidden">
                <Button v-if="!consultation.signed_at" as-child>
                    <Link :href="edit(consultation.id)">Continuar nota</Link>
                </Button>
                <Button variant="outline" @click="print"
                    ><Printer /> Imprimir</Button
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

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px] print:block">
            <article class="space-y-6 rounded-xl border p-5">
                <section v-if="vitals.length" class="space-y-2">
                    <h2
                        class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Signos vitales
                    </h2>
                    <dl
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
                    >
                        <div
                            v-for="v in vitals"
                            :key="v.label"
                            class="rounded-lg bg-muted/50 p-2.5"
                        >
                            <dt class="text-xs text-muted-foreground">
                                {{ v.label }}
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{ v.value }}
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                    >{{ v.unit }}</span
                                >
                            </dd>
                        </div>
                    </dl>
                </section>

                <section v-if="consultation.reason" class="space-y-1">
                    <h2
                        class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Motivo de consulta
                    </h2>
                    <p>{{ consultation.reason }}</p>
                </section>

                <section v-for="s in sections" :key="s.label" class="space-y-1">
                    <h2
                        class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ s.label }}
                    </h2>
                    <p class="leading-relaxed whitespace-pre-line">
                        {{ s.value }}
                    </p>
                </section>

                <section class="space-y-2">
                    <h2
                        class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Diagnósticos
                    </h2>
                    <DiagnosisList
                        :model-value="consultation.diagnoses"
                        readonly
                    />
                </section>

                <AddendaSection
                    :consultation-id="consultation.id"
                    :addenda="consultation.addenda"
                    :can-add="
                        !!consultation.signed_at && can('consultations.manage')
                    "
                />
            </article>

            <aside class="rounded-xl border p-5 print:hidden">
                <PatientContextPanel
                    :histories="histories"
                    :history-types="historyTypes"
                    :previous="previous"
                    :trend="trend"
                />
            </aside>
        </div>

        <div class="print:hidden">
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
        </div>
    </div>
</template>
