<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Pencil, Plus, Search, Star } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import MedicationController from '@/actions/App/Http/Controllers/Catalogs/MedicationController';
import CatalogTabs from '@/components/catalogs/CatalogTabs.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
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
import { index } from '@/routes/catalogs/vademecum';
import type { Paginated } from '@/types';

type Medication = {
    id: number;
    generic_name: string;
    brand_name: string | null;
    presentation: string | null;
    concentration: string | null;
    route: string | null;
    is_favorite: boolean;
    default_dose: string | null;
    default_frequency: string | null;
    default_duration: string | null;
    default_instructions: string | null;
    is_active: boolean;
};

const props = defineProps<{
    medications: Paginated<Medication>;
    filters: { q: string | null; filter: 'all' | 'favorites' | 'inactive' };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Catálogos', href: index() }],
    },
});

const FILTERS = [
    { value: 'all', label: 'Activos' },
    { value: 'favorites', label: 'Vademécum' },
    { value: 'inactive', label: 'Inactivos' },
] as const;

const search = ref(props.filters.q ?? '');

function visit(params: { q?: string; filter?: string }): void {
    router.get(
        index.url(),
        {
            q: params.q ?? (search.value || undefined),
            filter: params.filter ?? props.filters.filter,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const runSearch = useDebounceFn(
    (value: string) => visit({ q: value.trim() || undefined }),
    300,
);
watch(search, (value) => runSearch(value));

function toggleFavorite(med: Medication): void {
    router.patch(
        MedicationController.favorite.url(med.id),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function describe(med: Medication): string {
    return [
        med.concentration,
        med.presentation,
        med.brand_name ? `(${med.brand_name})` : null,
    ]
        .filter(Boolean)
        .join(' ');
}

function posology(med: Medication): string {
    return [med.default_dose, med.default_frequency, med.default_duration]
        .filter(Boolean)
        .join(' · ');
}

// --- Formulario ------------------------------------------------------------
const dialogOpen = ref(false);
const editing = ref<Medication | null>(null);

const empty = {
    generic_name: '',
    brand_name: '',
    presentation: '',
    concentration: '',
    route: 'Oral',
    is_favorite: true,
    default_dose: '',
    default_frequency: '',
    default_duration: '',
    default_instructions: '',
    is_active: true,
};

const form = useForm({ ...empty });

function openForm(med: Medication | null): void {
    editing.value = med;
    form.clearErrors();
    form.defaults(
        med
            ? {
                  generic_name: med.generic_name,
                  brand_name: med.brand_name ?? '',
                  presentation: med.presentation ?? '',
                  concentration: med.concentration ?? '',
                  route: med.route ?? '',
                  is_favorite: med.is_favorite,
                  default_dose: med.default_dose ?? '',
                  default_frequency: med.default_frequency ?? '',
                  default_duration: med.default_duration ?? '',
                  default_instructions: med.default_instructions ?? '',
                  is_active: med.is_active,
              }
            : { ...empty },
    );
    form.reset();
    dialogOpen.value = true;
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };

    if (editing.value) {
        form.put(MedicationController.update.url(editing.value.id), options);
    } else {
        form.post(MedicationController.store.url(), options);
    }
}

const fields = computed(
    () =>
        [
            {
                key: 'generic_name',
                label: 'Nombre genérico *',
                placeholder: 'Paracetamol',
                span: true,
            },
            {
                key: 'concentration',
                label: 'Concentración',
                placeholder: '500 mg',
            },
            {
                key: 'presentation',
                label: 'Presentación',
                placeholder: 'Tabletas',
            },
            {
                key: 'brand_name',
                label: 'Nombre comercial',
                placeholder: 'Opcional',
            },
            { key: 'route', label: 'Vía', placeholder: 'Oral' },
        ] as const,
);

const posologyFields = [
    { key: 'default_dose', label: 'Dosis', placeholder: '1 tableta' },
    {
        key: 'default_frequency',
        label: 'Frecuencia',
        placeholder: 'Cada 8 horas',
    },
    { key: 'default_duration', label: 'Duración', placeholder: '5 días' },
] as const;
</script>

<template>
    <Head title="Vademécum" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Catálogos"
                description="Medicamentos, servicios, plantillas y diagnósticos"
            />
            <Button @click="openForm(null)"><Plus /> Nuevo medicamento</Button>
        </div>

        <CatalogTabs />

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full max-w-sm">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    class="pl-9"
                    placeholder="Buscar por nombre genérico o comercial…"
                />
            </div>
            <div class="flex gap-1.5" role="tablist">
                <button
                    v-for="f in FILTERS"
                    :key="f.value"
                    type="button"
                    role="tab"
                    :aria-selected="filters.filter === f.value"
                    class="rounded-full border px-3 py-1 text-xs"
                    :class="
                        filters.filter === f.value
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'hover:bg-accent'
                    "
                    @click="visit({ filter: f.value })"
                >
                    {{ f.label }}
                </button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="w-10 px-3 py-2.5">
                            <span class="sr-only">Vademécum</span>
                        </th>
                        <th class="px-3 py-2.5 font-medium">Medicamento</th>
                        <th
                            class="hidden px-3 py-2.5 font-medium md:table-cell"
                        >
                            Vía
                        </th>
                        <th
                            class="hidden px-3 py-2.5 font-medium lg:table-cell"
                        >
                            Posología predeterminada
                        </th>
                        <th class="w-12" />
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="med in medications.data"
                        :key="med.id"
                        :class="med.is_active ? '' : 'opacity-60'"
                    >
                        <td class="px-3 py-2">
                            <button
                                type="button"
                                :aria-label="
                                    med.is_favorite
                                        ? 'Quitar del vademécum'
                                        : 'Agregar al vademécum'
                                "
                                :title="
                                    med.is_favorite
                                        ? 'Quitar del vademécum'
                                        : 'Agregar al vademécum'
                                "
                                class="rounded p-1 hover:bg-accent"
                                @click="toggleFavorite(med)"
                            >
                                <Star
                                    class="size-4"
                                    :class="
                                        med.is_favorite
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'text-muted-foreground'
                                    "
                                />
                            </button>
                        </td>
                        <td class="px-3 py-2">
                            <p class="font-medium">{{ med.generic_name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ describe(med) }}
                            </p>
                        </td>
                        <td class="hidden px-3 py-2 md:table-cell">
                            {{ med.route ?? '—' }}
                        </td>
                        <td
                            class="hidden px-3 py-2 text-muted-foreground lg:table-cell"
                        >
                            {{ posology(med) || '—' }}
                        </td>
                        <td class="px-2">
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                aria-label="Editar"
                                @click="openForm(med)"
                                ><Pencil
                            /></Button>
                        </td>
                    </tr>
                    <tr v-if="medications.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            Sin medicamentos.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="medications" noun="medicamentos" />
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-xl">
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Editar medicamento' : 'Nuevo medicamento'
                    }}</DialogTitle>
                    <DialogDescription
                        >La posología predeterminada se llena sola al
                        recetarlo.</DialogDescription
                    >
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="f in fields"
                        :key="f.key"
                        class="grid gap-1.5"
                        :class="'span' in f ? 'sm:col-span-2' : ''"
                    >
                        <Label :for="f.key">{{ f.label }}</Label>
                        <Input
                            :id="f.key"
                            v-model="form[f.key]"
                            :placeholder="f.placeholder"
                        />
                        <InputError :message="form.errors[f.key]" />
                    </div>
                </div>

                <fieldset class="space-y-3 rounded-lg border p-3">
                    <legend class="px-1 text-sm font-medium">
                        Posología predeterminada
                    </legend>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div
                            v-for="f in posologyFields"
                            :key="f.key"
                            class="grid gap-1.5"
                        >
                            <Label :for="f.key">{{ f.label }}</Label>
                            <Input
                                :id="f.key"
                                v-model="form[f.key]"
                                :placeholder="f.placeholder"
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="default_instructions">Indicaciones</Label>
                        <Input
                            id="default_instructions"
                            v-model="form.default_instructions"
                            placeholder="Tomar con alimentos"
                        />
                    </div>
                </fieldset>

                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2"
                        ><input
                            v-model="form.is_favorite"
                            type="checkbox"
                            class="size-4"
                        />
                        En mi vademécum</label
                    >
                    <label class="flex items-center gap-2"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-4"
                        />
                        Activo</label
                    >
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="dialogOpen = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Guardar</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
