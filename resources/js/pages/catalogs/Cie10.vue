<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Eye, EyeOff, Search, Upload } from '@lucide/vue';
import { ref, watch } from 'vue';
import Cie10Controller from '@/actions/App/Http/Controllers/Catalogs/Cie10Controller';
import CatalogTabs from '@/components/catalogs/CatalogTabs.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/catalogs/diagnoses';
import { index as catalogsIndex } from '@/routes/catalogs/vademecum';
import type { Paginated } from '@/types';

type Code = {
    id: number;
    code: string;
    description: string;
    chapter: string | null;
    is_active: boolean;
};

const props = defineProps<{
    codes: Paginated<Code>;
    filters: { q: string | null; filter: 'all' | 'inactive' };
    totals: { active: number; inactive: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Catálogos', href: catalogsIndex() }],
    },
});

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

function toggle(code: Code): void {
    router.patch(
        Cie10Controller.toggle.url(code.id),
        {},
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Diagnósticos CIE-10" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <Heading
            title="Catálogos"
            description="Medicamentos, servicios, plantillas y diagnósticos"
        />

        <CatalogTabs />

        <section
            class="grid gap-4 rounded-xl border p-5 lg:grid-cols-[1fr_auto] lg:items-end"
        >
            <div class="space-y-1">
                <h2 class="text-base font-medium">
                    Importar catálogo completo
                </h2>
                <p class="text-sm text-muted-foreground">
                    Descarga el catálogo CIE-10 de la DGIS (Secretaría de
                    Salud), guárdalo como CSV y súbelo aquí. Se aceptan las
                    columnas
                    <code class="rounded bg-muted px-1"
                        >CATALOG_KEY, NOMBRE</code
                    >
                    o
                    <code class="rounded bg-muted px-1">code, description</code
                    >. Los códigos existentes se actualizan; no se borra nada.
                </p>
                <p class="text-sm">
                    <strong class="tabular-nums">{{
                        totals.active.toLocaleString('es-MX')
                    }}</strong>
                    códigos activos
                    <template v-if="totals.inactive"
                        >·
                        {{
                            totals.inactive.toLocaleString('es-MX')
                        }}
                        ocultos</template
                    >
                </p>
            </div>
            <Form
                v-bind="Cie10Controller.import.form()"
                :reset-on-success="true"
                :options="{ preserveScroll: true }"
                class="flex flex-wrap items-start gap-2"
                v-slot="{ errors, processing, progress }"
            >
                <div class="grid gap-1">
                    <input
                        type="file"
                        name="file"
                        accept=".csv,text/csv,text/plain"
                        required
                        class="block text-sm file:mr-2 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm"
                    />
                    <InputError :message="errors.file" />
                    <div
                        v-if="progress"
                        class="h-1 w-48 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full bg-primary"
                            :style="{ width: `${progress.percentage ?? 0}%` }"
                        />
                    </div>
                </div>
                <Button type="submit" :disabled="processing"
                    ><Upload />
                    {{ processing ? 'Importando…' : 'Importar' }}</Button
                >
            </Form>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full max-w-sm">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    class="pl-9"
                    placeholder="Buscar por código o descripción…"
                />
            </div>
            <div class="flex gap-1.5">
                <button
                    v-for="f in [
                        { value: 'all', label: 'Activos' },
                        { value: 'inactive', label: 'Ocultos' },
                    ]"
                    :key="f.value"
                    type="button"
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
                        <th class="w-24 px-4 py-2.5 font-medium">Código</th>
                        <th class="px-4 py-2.5 font-medium">Descripción</th>
                        <th
                            class="hidden w-24 px-4 py-2.5 font-medium sm:table-cell"
                        >
                            Capítulo
                        </th>
                        <th class="w-12" />
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="c in codes.data" :key="c.id">
                        <td class="px-4 py-2 font-mono text-xs font-semibold">
                            {{ c.code }}
                        </td>
                        <td class="px-4 py-2">{{ c.description }}</td>
                        <td
                            class="hidden px-4 py-2 text-muted-foreground sm:table-cell"
                        >
                            {{ c.chapter ?? '—' }}
                        </td>
                        <td class="px-2">
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                :aria-label="
                                    c.is_active
                                        ? 'Ocultar del autocompletado'
                                        : 'Mostrar en el autocompletado'
                                "
                                :title="
                                    c.is_active
                                        ? 'Ocultar del autocompletado'
                                        : 'Mostrar en el autocompletado'
                                "
                                @click="toggle(c)"
                            >
                                <EyeOff v-if="c.is_active" /><Eye v-else />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="codes.data.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            Sin resultados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="codes" noun="códigos" />
    </div>
</template>
