<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Plus, Search, TriangleAlert } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, index, show } from '@/routes/patients';
import type { Paginated, PatientListItem } from '@/types';

const props = defineProps<{
    patients: Paginated<PatientListItem>;
    filters: { q: string | null };
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pacientes', href: index() }],
    },
});

const search = ref(props.filters.q ?? '');

const runSearch = useDebounceFn((value: string) => {
    router.get(index.url(), value.trim() ? { q: value.trim() } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

watch(search, (value) => runSearch(value));
</script>

<template>
    <Head title="Pacientes" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Pacientes"
                description="Busca por nombre, teléfono, folio o CURP completa"
            />
            <Button v-if="can.create" as-child>
                <Link :href="create()"><Plus /> Nuevo paciente</Link>
            </Button>
        </div>

        <div class="relative max-w-md">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                type="search"
                class="pl-9"
                placeholder="Buscar paciente…"
                aria-label="Buscar paciente"
                autofocus
            />
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Folio</th>
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Edad</th>
                        <th class="hidden px-4 py-3 font-medium md:table-cell">
                            Teléfono
                        </th>
                        <th class="hidden px-4 py-3 font-medium lg:table-cell">
                            Correo
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="patient in patients.data"
                        :key="patient.id"
                        class="cursor-pointer transition-colors hover:bg-muted/40"
                        @click="router.visit(show(patient.id))"
                    >
                        <td
                            class="px-4 py-3 font-mono text-xs text-muted-foreground"
                        >
                            {{ patient.folio }}
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                :href="show(patient.id)"
                                class="font-medium hover:underline"
                                @click.stop
                            >
                                {{ patient.full_name }}
                            </Link>
                            <TriangleAlert
                                v-if="patient.has_critical_allergies"
                                class="ml-1.5 inline size-4 text-destructive"
                                aria-label="Alergias críticas"
                            />
                        </td>
                        <td class="px-4 py-3">
                            {{
                                patient.age !== null
                                    ? `${patient.age} años`
                                    : '—'
                            }}
                        </td>
                        <td class="hidden px-4 py-3 md:table-cell">
                            {{ patient.phone ?? '—' }}
                        </td>
                        <td class="hidden px-4 py-3 lg:table-cell">
                            {{ patient.email ?? '—' }}
                        </td>
                    </tr>
                    <tr v-if="patients.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            {{
                                filters.q
                                    ? `Sin resultados para "${filters.q}".`
                                    : 'Aún no hay pacientes registrados.'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="patients" noun="pacientes" />
    </div>
</template>
