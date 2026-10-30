<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    Download,
    HardDriveDownload,
    Info,
    LoaderCircle,
} from '@lucide/vue';
import { computed } from 'vue';
import BackupController from '@/actions/App/Http/Controllers/Settings/BackupController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { formatBytes, formatDateTime } from '@/lib/format';
import { index } from '@/routes/settings/backups';

type Backup = { name: string; size: number; created_at: string };

const props = defineProps<{
    status: {
        last_attempt_at: string | null;
        last_success_at: string | null;
        last_status: 'ok' | 'failed' | null;
        last_error: string | null;
        last_file: string | null;
        last_size: number | null;
        warning: string | null;
    };
    backups: Backup[];
    config: {
        path: string;
        inside_project: boolean;
        encrypted: boolean;
        keep_days: number;
        time: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Respaldos', href: index() }],
    },
});

const form = useForm({});

function runNow(): void {
    form.post(BackupController.store.url(), { preserveScroll: true });
}

// El error llega con la llave "backup" (no es un campo del formulario).
const runError = computed(
    () => (form.errors as Record<string, string | undefined>).backup,
);

const failed = computed(() => props.status.last_status === 'failed');
</script>

<template>
    <Head title="Respaldos" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Respaldos"
                description="Copia cifrada de la base de datos y de los archivos (estudios, recetas, firma y sello)"
            />
            <Button size="sm" :disabled="form.processing" @click="runNow">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                <HardDriveDownload v-else />
                {{ form.processing ? 'Respaldando…' : 'Respaldar ahora' }}
            </Button>
        </div>

        <InputError :message="runError" />

        <!-- Estado -->
        <div
            class="flex items-start gap-3 rounded-lg border p-4 text-sm"
            :class="
                status.warning
                    ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200'
                    : ''
            "
        >
            <CircleAlert v-if="status.warning" class="mt-0.5 size-4 shrink-0" />
            <CircleCheck
                v-else
                class="mt-0.5 size-4 shrink-0 text-emerald-600"
            />
            <div class="space-y-1">
                <p class="font-medium">
                    {{ status.warning ?? 'Los respaldos están al día.' }}
                </p>
                <p v-if="status.last_success_at">
                    Último respaldo exitoso:
                    {{ formatDateTime(status.last_success_at) }}
                    <template v-if="status.last_size"
                        >· {{ formatBytes(status.last_size) }}</template
                    >
                </p>
                <p v-if="failed && status.last_error" class="break-words">
                    Error del último intento ({{
                        formatDateTime(status.last_attempt_at)
                    }}):
                    {{ status.last_error }}
                </p>
            </div>
        </div>

        <!-- Configuración (se cambia en .env) -->
        <ul class="space-y-2 text-sm">
            <li class="flex items-start gap-2">
                <CircleAlert
                    v-if="config.inside_project"
                    class="mt-0.5 size-4 shrink-0 text-amber-600"
                />
                <Info
                    v-else
                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                />
                <span>
                    Carpeta:
                    <code class="rounded bg-muted px-1 break-all">{{
                        config.path
                    }}</code>
                    <template v-if="config.inside_project">
                        — está dentro del mismo equipo y proyecto. Si el disco
                        falla se pierde junto con el sistema: define
                        <code class="rounded bg-muted px-1">BACKUP_PATH</code>
                        con una carpeta de OneDrive/Google Drive o un disco
                        externo.
                    </template>
                </span>
            </li>
            <li class="flex items-start gap-2">
                <CircleCheck
                    v-if="config.encrypted"
                    class="mt-0.5 size-4 shrink-0 text-emerald-600"
                />
                <CircleAlert
                    v-else
                    class="mt-0.5 size-4 shrink-0 text-amber-600"
                />
                <span v-if="config.encrypted"
                    >Cifrado AES-256 con la contraseña de
                    <code class="rounded bg-muted px-1">BACKUP_PASSWORD</code>.
                    Guárdala fuera del equipo: sin ella no se puede abrir el
                    respaldo.</span
                >
                <span v-else
                    >Sin cifrar: define
                    <code class="rounded bg-muted px-1">BACKUP_PASSWORD</code>
                    en <code class="rounded bg-muted px-1">.env</code>. Los
                    respaldos contienen todo el expediente clínico.</span
                >
            </li>
            <li class="flex items-start gap-2">
                <Info class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                <span
                    >Automático todos los días a las {{ config.time }} h
                    (requiere el programador de tareas). Se conservan
                    {{ config.keep_days }} días.</span
                >
            </li>
        </ul>

        <!-- Lista -->
        <div>
            <h3 class="mb-2 text-sm font-medium">
                Respaldos guardados ({{ backups.length }})
            </h3>
            <ul v-if="backups.length" class="divide-y rounded-xl border">
                <li
                    v-for="b in backups"
                    :key="b.name"
                    class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                >
                    <div class="min-w-0">
                        <p class="tabular-nums">
                            {{ formatDateTime(b.created_at) }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ b.name }} · {{ formatBytes(b.size) }}
                        </p>
                    </div>
                    <Button size="icon-sm" variant="ghost" as-child>
                        <a
                            :href="BackupController.download.url(b.name)"
                            :aria-label="`Descargar ${b.name}`"
                            ><Download
                        /></a>
                    </Button>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                Todavía no hay respaldos en la carpeta.
            </p>
        </div>

        <p class="text-xs text-muted-foreground">
            Para restaurar, abre el ZIP con 7-Zip y la contraseña, importa
            <code>database/database.sql</code> en HeidiSQL y copia la carpeta
            <code>files</code> a <code>storage/app/private</code>. Los pasos
            completos están en el README.
        </p>
    </div>
</template>
