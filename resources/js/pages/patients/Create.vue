<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import Heading from '@/components/Heading.vue';
import PatientForm from '@/components/patients/PatientForm.vue';
import { create, index } from '@/routes/patients';
import type { SelectOption } from '@/types';

defineProps<{
    sexes: SelectOption[];
    bloodTypes: SelectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Pacientes', href: index() },
            { title: 'Nuevo', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Nuevo paciente" />

    <div class="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Nuevo paciente"
            description="El folio se asigna automáticamente al guardar"
        />
        <PatientForm
            :action="PatientController.store.form()"
            :cancel-href="index.url()"
            submit-label="Registrar paciente"
            :sexes="sexes"
            :blood-types="bloodTypes"
        />
    </div>
</template>
