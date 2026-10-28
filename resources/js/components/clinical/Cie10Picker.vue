<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { LoaderCircle, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cie10 } from '@/routes/catalogs';
import type { Cie10Result } from '@/types';

const props = defineProps<{ excludeIds: number[] }>();

const emit = defineEmits<{ pick: [code: Cie10Result] }>();

const query = ref('');
const results = ref<Cie10Result[]>([]);
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
        const response = await fetch(cie10.url({ query: { q: term.trim() } }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        });
        const json = response.ok
            ? ((await response.json()) as { data: Cie10Result[] })
            : { data: [] };
        results.value = json.data.filter(
            (r) => !props.excludeIds.includes(r.id),
        );
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

function choose(code: Cie10Result): void {
    emit('pick', code);
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
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            v-model="query"
            class="pl-9"
            placeholder="Buscar diagnóstico CIE-10 por código o descripción…"
            autocomplete="off"
            role="combobox"
            :aria-expanded="open"
            @keydown="onKeydown"
            @blur="open = false"
            @focus="open = results.length > 0"
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
                v-if="results.length === 0"
                class="px-3 py-2 text-muted-foreground"
            >
                Sin resultados.
            </li>
            <li
                v-for="(code, i) in results"
                :key="code.id"
                role="option"
                :aria-selected="i === highlighted"
                class="flex cursor-pointer gap-3 rounded px-3 py-2"
                :class="
                    i === highlighted ? 'bg-accent text-accent-foreground' : ''
                "
                @mousedown.prevent="choose(code)"
                @mouseenter="highlighted = i"
            >
                <span class="w-14 shrink-0 font-mono text-xs font-semibold">{{
                    code.code
                }}</span>
                <span>{{ code.description }}</span>
            </li>
        </ul>
    </div>
</template>
