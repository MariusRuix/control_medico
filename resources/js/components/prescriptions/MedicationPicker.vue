<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { LoaderCircle, Pill, Plus, Search, Star } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { medications as medicationsRoute } from '@/routes/catalogs';
import type { MedicationOption } from '@/types';

const emit = defineEmits<{
    pick: [medication: MedicationOption];
    free: [name: string];
}>();

const query = ref('');
const results = ref<MedicationOption[]>([]);
const loading = ref(false);
const open = ref(false);
const highlighted = ref(0);
let controller: AbortController | null = null;

const search = useDebounceFn(async (term: string) => {
    controller?.abort();

    if (term.trim().length < 2) {
        results.value = [];
        open.value = false;

        return;
    }

    controller = new AbortController();
    loading.value = true;

    try {
        const response = await fetch(
            medicationsRoute.url({ query: { q: term.trim() } }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
            },
        );
        results.value = response.ok
            ? ((await response.json()) as { data: MedicationOption[] }).data
            : [];
        highlighted.value = 0;
        open.value = true;
    } catch (error) {
        if ((error as Error).name !== 'AbortError') {
            results.value = [];
        }
    } finally {
        loading.value = false;
    }
}, 200);

watch(query, (term) => search(term));

/** Opciones: resultados del catálogo + "usar texto libre" al final. */
function optionCount(): number {
    return results.value.length + 1;
}

function choose(index: number): void {
    if (index < results.value.length) {
        emit('pick', results.value[index]);
    } else if (query.value.trim()) {
        emit('free', query.value.trim());
    }

    query.value = '';
    results.value = [];
    open.value = false;
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter') {
        event.preventDefault();

        if (open.value) {
            choose(highlighted.value);
        } else if (query.value.trim()) {
            choose(results.value.length);
        }

        return;
    }

    if (!open.value) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlighted.value = (highlighted.value + 1) % optionCount();
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlighted.value =
            (highlighted.value - 1 + optionCount()) % optionCount();
    } else if (event.key === 'Escape') {
        open.value = false;
    }
}
</script>

<template>
    <div class="relative">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            v-model="query"
            class="pl-9"
            placeholder="Buscar medicamento o escribir uno nuevo…"
            autocomplete="off"
            role="combobox"
            :aria-expanded="open"
            @keydown="onKeydown"
            @blur="open = false"
        />
        <LoaderCircle
            v-if="loading"
            class="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
        />
        <ul
            v-if="open"
            role="listbox"
            class="absolute z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 text-sm shadow-md"
        >
            <li
                v-for="(med, i) in results"
                :key="med.id"
                role="option"
                :aria-selected="i === highlighted"
                class="flex cursor-pointer items-center gap-2 rounded px-3 py-2"
                :class="
                    i === highlighted ? 'bg-accent text-accent-foreground' : ''
                "
                @mousedown.prevent="choose(i)"
                @mouseenter="highlighted = i"
            >
                <Star
                    v-if="med.is_favorite"
                    class="size-3.5 shrink-0 fill-amber-400 text-amber-400"
                />
                <Pill v-else class="size-3.5 shrink-0 text-muted-foreground" />
                <span class="flex-1">{{ med.name }}</span>
                <span
                    v-if="med.default_frequency"
                    class="text-xs text-muted-foreground"
                    >{{ med.default_frequency }}</span
                >
            </li>
            <li
                role="option"
                :aria-selected="highlighted === results.length"
                class="flex cursor-pointer items-center gap-2 rounded px-3 py-2 text-muted-foreground"
                :class="
                    highlighted === results.length
                        ? 'bg-accent text-accent-foreground'
                        : ''
                "
                @mousedown.prevent="choose(results.length)"
                @mouseenter="highlighted = results.length"
            >
                <Plus class="size-3.5" /> Usar "{{ query.trim() }}" como texto
                libre
            </li>
        </ul>
    </div>
</template>
