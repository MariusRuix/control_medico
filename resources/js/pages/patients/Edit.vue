<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import Heading from '@/components/Heading.vue';
import PatientForm from '@/components/patients/PatientForm.vue';
import { index, show } from '@/routes/patients';
import type { Patient, SelectOption } from '@/types';

defineProps<{
    patient: Patient;
    sexes: SelectOption[];
    bloodTypes: SelectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pacientes', href: index() }],
    },
});
</script>

<template>
    <Head :title="`Editar · ${patient.full_name}`" />

    <div class="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="`Editar ficha · ${patient.full_name}`"
            :description="`Folio ${patient.folio}`"
        />
        <PatientForm
            :action="PatientController.update.form(patient.id)"
            :cancel-href="show.url(patient.id)"
            submit-label="Guardar cambios"
            :patient="patient"
            :sexes="sexes"
            :blood-types="bloodTypes"
        />
    </div>
</template>
