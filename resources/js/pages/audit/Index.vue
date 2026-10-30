<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Download, X } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import AuditLogController from '@/actions/App/Http/Controllers/Audit/AuditLogController';
import PatientPicker from '@/components/agenda/PatientPicker.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/audit-logs';
import { show as patientShow } from '@/routes/patients';
import type { Paginated, PatientSearchResult } from '@/types';

type Change = { field: string; old: string | null; new: string | null };

type AuditRow = {
    id: number;
    created_at: string;
    user: { id: number; name: string } | null;
    action: string;
    action_label: string;
    type_label: string | null;
    auditable_id: number | null;
    patient: { id: number; folio: string; full_name: string } | null;
    changes: Change[];
    ip_address: string | null;
    user_agent: string | null;
};

type Filters = {
    patient: number | null;
    user: number | null;
    action: string | null;
    type: string | null;
    from: string | null;
    to: string | null;
};

type Option<T> = { value: T; label: string };

const props = defineProps<{
    logs: Paginated<AuditRow>;
    filters: Filters;
    patient: PatientSearchResult | null;
    users: Option<number>[];
    actions: Option<string>[];
    types: Option<string>[];
    errors?: Record<string, string>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Bitácora', href: index() }],
    },
});

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const form = reactive({
    user: props.filters.user,
    action: props.filters.action,
    type: props.filters.type,
    from: props.filters.from,
    to: props.filters.to,
});
const selectedPatient = ref<PatientSearchResult | null>(props.patient);

function query(): Record<string, string | number> {
    const q: Record<string, string | number> = {};

    if (selectedPatient.value) {
        q.patient = selectedPatient.value.id;
    }

    for (const [key, value] of Object.entries(form)) {
        if (value !== null && value !== '') {
            q[key] = value;
        }
    }

    return q;
}

function apply(): void {
    router.get(index.url(), query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

watch(selectedPatient, apply);

function isoDay(offset: number): string {
    const d = new Date();
    d.setDate(d.getDate() - offset);

    return [
        d.getFullYear(),
        String(d.getMonth() + 1).padStart(2, '0'),
        String(d.getDate()).padStart(2, '0'),
    ].join('-');
}

function preset(days: number): void {
    form.from = isoDay(days);
    form.to = isoDay(0);
    apply();
}

function clear(): void {
    form.user = null;
    form.action = null;
    form.type = null;
    form.from = null;
    form.to = null;

    if (selectedPatient.value) {
        selectedPatient.value = null; // dispara apply() por el watch
    } else {
        apply();
    }
}

const expanded = ref(new Set<number>());

function toggle(id: number): void {
    const next = new Set(expanded.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    expanded.value = next;
}

function actionClass(action: string): string {
    if (['viewed', 'downloaded', 'exported'].includes(action)) {
        return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200';
    }

    if (['created', 'restored', 'activated'].includes(action)) {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
    }

    if (['updated', 'emailed', 'password_reset'].includes(action)) {
        return 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300';
    }

    if (['login', 'logout'].includes(action)) {
        return 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300';
    }

    return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300';
}
</script>

<template>
    <Head title="Bitácora" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Bitácora"
                description="Quién consultó o modificó cada registro, y cuándo (NOM-004 / NOM-024)"
            />
            <Button variant="outline" as-child>
                <a :href="AuditLogController.export.url({ query: query() })"
                    ><Download /> Exportar CSV</a
                >
            </Button>
        </div>

        <div
            class="grid gap-3 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <div class="grid gap-1.5 md:col-span-2 xl:col-span-1">
                <span class="text-sm font-medium">Paciente</span>
                <PatientPicker v-model="selectedPatient" />
            </div>
            <div class="grid gap-1.5">
                <label for="f_user" class="text-sm font-medium">Usuario</label>
                <select
                    id="f_user"
                    v-model="form.user"
                    :class="selectClass"
                    @change="apply"
                >
                    <option :value="null">Todos</option>
                    <option v-for="u in users" :key="u.value" :value="u.value">
                        {{ u.label }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <label for="f_action" class="text-sm font-medium">Acción</label>
                <select
                    id="f_action"
                    v-model="form.action"
                    :class="selectClass"
                    @change="apply"
                >
                    <option :value="null">Todas</option>
                    <option
                        v-for="a in actions"
                        :key="a.value"
                        :value="a.value"
                    >
                        {{ a.label }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <label for="f_type" class="text-sm font-medium"
                    >Tipo de registro</label
                >
                <select
                    id="f_type"
                    v-model="form.type"
                    :class="selectClass"
                    @change="apply"
                >
                    <option :value="null">Todos</option>
                    <option v-for="t in types" :key="t.value" :value="t.value">
                        {{ t.label }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <span class="text-sm font-medium">Periodo</span>
                <div class="flex items-center gap-2">
                    <input
                        v-model="form.from"
                        type="date"
                        aria-label="Desde"
                        :class="selectClass"
                        @change="apply"
                    />
                    <span class="text-muted-foreground">–</span>
                    <input
                        v-model="form.to"
                        type="date"
                        aria-label="Hasta"
                        :class="selectClass"
                        @change="apply"
                    />
                </div>
                <InputError :message="errors?.to ?? errors?.from" />
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <Button size="sm" variant="secondary" @click="preset(0)"
                    >Hoy</Button
                >
                <Button size="sm" variant="secondary" @click="preset(6)"
                    >7 días</Button
                >
                <Button size="sm" variant="secondary" @click="preset(29)"
                    >30 días</Button
                >
                <Button size="sm" variant="ghost" @click="clear"
                    ><X /> Limpiar</Button
                >
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="w-8" />
                        <th class="px-3 py-2.5 font-medium whitespace-nowrap">
                            Fecha y hora
                        </th>
                        <th class="px-3 py-2.5 font-medium">Usuario</th>
                        <th class="px-3 py-2.5 font-medium">Acción</th>
                        <th class="px-3 py-2.5 font-medium">Registro</th>
                        <th class="px-3 py-2.5 font-medium">Paciente</th>
                        <th
                            class="hidden px-3 py-2.5 font-medium lg:table-cell"
                        >
                            IP
                        </th>
                    </tr>
                </thead>
                <tbody v-if="logs.data.length === 0">
                    <tr>
                        <td
                            colspan="7"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No hay registros con estos filtros.
                        </td>
                    </tr>
                </tbody>
                <tbody v-for="log in logs.data" :key="log.id" class="border-t">
                    <tr
                        class="align-top"
                        :class="
                            log.changes.length || log.user_agent
                                ? 'cursor-pointer hover:bg-muted/30'
                                : ''
                        "
                        @click="toggle(log.id)"
                    >
                        <td class="py-2.5 pl-3 text-muted-foreground">
                            <component
                                :is="
                                    expanded.has(log.id)
                                        ? ChevronDown
                                        : ChevronRight
                                "
                                class="size-4"
                            />
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap tabular-nums">
                            {{ formatDateTime(log.created_at) }}
                        </td>
                        <td class="px-3 py-2.5">
                            <span v-if="log.user">{{ log.user.name }}</span>
                            <span v-else class="text-muted-foreground"
                                >Sin sesión</span
                            >
                        </td>
                        <td class="px-3 py-2.5">
                            <span
                                class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap"
                                :class="actionClass(log.action)"
                                >{{ log.action_label }}</span
                            >
                        </td>
                        <td class="px-3 py-2.5">
                            <template v-if="log.type_label">
                                {{ log.type_label }}
                                <span class="text-muted-foreground"
                                    >#{{ log.auditable_id }}</span
                                >
                            </template>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-3 py-2.5">
                            <Link
                                v-if="log.patient"
                                :href="patientShow(log.patient.id)"
                                class="hover:underline"
                                @click.stop
                            >
                                {{ log.patient.full_name }}
                                <span class="text-xs text-muted-foreground">{{
                                    log.patient.folio
                                }}</span>
                            </Link>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td
                            class="hidden px-3 py-2.5 text-muted-foreground tabular-nums lg:table-cell"
                        >
                            {{ log.ip_address ?? '—' }}
                        </td>
                    </tr>
                    <tr v-if="expanded.has(log.id)" class="bg-muted/20">
                        <td />
                        <td colspan="6" class="px-3 pt-1 pb-3">
                            <table
                                v-if="log.changes.length"
                                class="w-full max-w-3xl text-xs"
                            >
                                <thead class="text-left text-muted-foreground">
                                    <tr>
                                        <th class="py-1 pr-3 font-medium">
                                            Campo
                                        </th>
                                        <th class="py-1 pr-3 font-medium">
                                            Antes
                                        </th>
                                        <th class="py-1 font-medium">
                                            Después
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in log.changes" :key="c.field">
                                        <td class="py-0.5 pr-3 font-mono">
                                            {{ c.field }}
                                        </td>
                                        <td
                                            class="py-0.5 pr-3 break-all text-muted-foreground"
                                        >
                                            {{ c.old ?? '—' }}
                                        </td>
                                        <td class="py-0.5 break-all">
                                            {{ c.new ?? '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-else class="text-xs text-muted-foreground">
                                Sin cambios de datos registrados.
                            </p>
                            <p
                                v-if="log.user_agent"
                                class="mt-2 text-xs break-all text-muted-foreground"
                            >
                                Equipo: {{ log.user_agent }}
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="logs" noun="registros" />

        <p class="text-xs text-muted-foreground">
            La bitácora es de solo lectura y no se puede modificar ni borrar
            desde el sistema. Los datos cifrados (CURP, notas clínicas,
            antecedentes) aparecen como <code>[protegido]</code>. Cada
            exportación queda registrada.
        </p>
    </div>
</template>
