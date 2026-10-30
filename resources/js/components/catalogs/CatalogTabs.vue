<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { index as diagnoses } from '@/routes/catalogs/diagnoses';
import { index as services } from '@/routes/catalogs/services';
import { index as templates } from '@/routes/catalogs/templates';
import { index as vademecum } from '@/routes/catalogs/vademecum';

const { isCurrentOrParentUrl } = useCurrentUrl();

const tabs = [
    { title: 'Vademécum', href: vademecum() },
    { title: 'Servicios y precios', href: services() },
    { title: 'Plantillas de nota', href: templates() },
    { title: 'Diagnósticos CIE-10', href: diagnoses() },
];
</script>

<template>
    <nav class="flex gap-1 overflow-x-auto border-b" aria-label="Catálogos">
        <Link
            v-for="tab in tabs"
            :key="tab.title"
            :href="tab.href"
            class="-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors"
            :class="
                isCurrentOrParentUrl(tab.href)
                    ? 'border-primary font-medium text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
        >
            {{ tab.title }}
        </Link>
    </nav>
</template>
