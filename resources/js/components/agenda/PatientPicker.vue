<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { LoaderCircle, Search, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { search as searchRoute } from '@/routes/patients';
import type { PatientSearchResult } from '@/types';

const model = defineModel<PatientSearchResult | null>({ required: true });

defineProps<{
    invalid?: boolean;
    disabled?: boolean;
}>();

const query = ref('');
const results = ref<PatientSearchResult[]>([]);
const loading = ref(false);
const open = ref(false);
const highlighted = ref(0);
const failed = ref(false);
let controller: AbortController | null = null;

const runSearch = useDebounceFn(async (term: string) => {
    controller?.abort();

    if (term.trim().length < 2) {
        results.value = [];
        loading.value = false;

        return;
    }

    controller = new AbortController();
    loading.value = true;
    failed.value = false;

    try {
        const response = await fetch(
            searchRoute.url({ query: { q: term.trim() } }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
            },
        );

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const json = (await response.json()) as { data: PatientSearchResult[] };
        results.value = json.data;
        highlighted.value = 0;
        open.value = true;
    } catch (error) {
        if ((error as Error).name !== 'AbortError') {
            failed.value = true;
            results.value = [];
        }
    } finally {
        loading.value = false;
    }
}, 250);

watch(query, (term) => runSearch(term));

function choose(patient: PatientSearchResult): void {
    model.value = patient;
    query.value = '';
    results.value = [];
    open.value = false;
}

function onKeydown(event: KeyboardEvent): void {
    if (!open.value || results.value.length === 0) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlighted.value = (highlighted.value + 1) % results.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlighted.value =
            (highlighted.value - 1 + results.value.length) %
            results.value.length;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        choose(results.value[highlighted.value]);
    } else if (event.key === 'Escape') {
        open.value = false;
    }
}
</script>

<template>
    <div class="relative">
        <div
            v-if="model"
            class="flex h-9 items-center justify-between gap-2 rounded-md border px-3 text-sm"
        >
            <span class="truncate">
                <span class="font-medium">{{ model.full_name }}</span>
                <span class="ml-2 font-mono text-xs text-muted-foreground">{{
                    model.folio
                }}</span>
            </span>
            <button
                v-if="!disabled"
                type="button"
                class="text-muted-foreground hover:text-foreground"
                aria-label="Cambiar paciente"
                @click="model = null"
            >
                <X class="size-4" />
            </button>
        </div>

        <template v-else>
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="query"
                class="pl-9"
                placeholder="Buscar por nombre, teléfono o folio…"
                autocomplete="off"
                role="combobox"
                :aria-expanded="open"
                :aria-invalid="invalid || undefined"
                @keydown="onKeydown"
                @focus="open = results.length > 0"
                @blur="open = false"
            />
            <LoaderCircle
                v-if="loading"
                class="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
            />

            <ul
                v-if="open"
                class="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-md border bg-popover p-1 text-sm shadow-md"
                role="listbox"
            >
                <li
                    v-if="results.length === 0"
                    class="px-3 py-2 text-muted-foreground"
                >
                    Sin resultados.
                </li>
                <li
                    v-for="(patient, i) in results"
                    :key="patient.id"
                    role="option"
                    :aria-selected="i === highlighted"
                    class="cursor-pointer rounded px-3 py-2"
                    :class="
                        i === highlighted
                            ? 'bg-accent text-accent-foreground'
                            : ''
                    "
                    @mousedown.prevent="choose(patient)"
                    @mouseenter="highlighted = i"
                >
                    <p class="font-medium">{{ patient.full_name }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ patient.folio }}
                        <template v-if="patient.age !== null">
                            · {{ patient.age }} años</template
                        >
                        <template v-if="patient.phone">
                            · {{ patient.phone }}</template
                        >
                    </p>
                </li>
            </ul>
            <p v-if="failed" class="mt-1 text-xs text-destructive">
                No se pudo buscar. Intenta de nuevo.
            </p>
        </template>
    </div>
</template>
