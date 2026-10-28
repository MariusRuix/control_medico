<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Download, Eye, FileText, Lock, Trash2, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import MedicalFileController from '@/actions/App/Http/Controllers/MedicalFiles/MedicalFileController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatBytes, formatDate } from '@/lib/format';
import { show } from '@/routes/medical-files';
import type { MedicalFileItem, SelectOption } from '@/types';
import FileViewerDialog from './FileViewerDialog.vue';
import UploadFilesDialog from './UploadFilesDialog.vue';

const props = defineProps<{
    patientId: number;
    files: MedicalFileItem[];
    types: SelectOption[];
    canUpload: boolean;
    canView: boolean;
    canDelete: boolean;
    consultationId?: number | null;
}>();

const filter = ref<string>('all');
const uploadOpen = ref(false);
const viewerOpen = ref(false);
const viewerIndex = ref(0);
const deleting = ref<MedicalFileItem | null>(null);
const deleteReason = ref('');
const deleteErrors = ref<Record<string, string>>({});

const presentTypes = computed(() =>
    props.types.filter((t) => props.files.some((f) => f.type === t.value)),
);

const visible = computed(() =>
    filter.value === 'all'
        ? props.files
        : props.files.filter((f) => f.type === filter.value),
);

function openViewer(file: MedicalFileItem): void {
    viewerIndex.value = visible.value.findIndex((f) => f.id === file.id);
    viewerOpen.value = true;
}

function confirmDelete(): void {
    if (!deleting.value) {
        return;
    }

    router.delete(MedicalFileController.destroy.url(deleting.value.id), {
        data: { reason: deleteReason.value },
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
            deleteReason.value = '';
            deleteErrors.value = {};
        },
        onError: (e) => (deleteErrors.value = e),
    });
}
</script>

<template>
    <section class="space-y-3 rounded-xl border p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-medium">Estudios y archivos</h2>
            <Button
                v-if="canUpload"
                size="sm"
                variant="outline"
                @click="uploadOpen = true"
            >
                <Upload /> Subir
            </Button>
        </div>

        <div
            v-if="presentTypes.length > 1"
            class="flex flex-wrap gap-1.5"
            role="tablist"
            aria-label="Filtrar por tipo"
        >
            <button
                type="button"
                role="tab"
                :aria-selected="filter === 'all'"
                class="rounded-full border px-2.5 py-0.5 text-xs"
                :class="
                    filter === 'all'
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-accent'
                "
                @click="filter = 'all'"
            >
                Todos ({{ files.length }})
            </button>
            <button
                v-for="t in presentTypes"
                :key="t.value"
                type="button"
                role="tab"
                :aria-selected="filter === t.value"
                class="rounded-full border px-2.5 py-0.5 text-xs"
                :class="
                    filter === t.value
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-accent'
                "
                @click="filter = t.value"
            >
                {{ t.label }} ({{
                    files.filter((f) => f.type === t.value).length
                }})
            </button>
        </div>

        <p v-if="files.length === 0" class="text-sm text-muted-foreground">
            Sin estudios registrados.
        </p>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="file in visible"
                :key="file.id"
                class="flex items-center gap-3 p-2.5"
            >
                <button
                    type="button"
                    class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted/50"
                    :disabled="!canView"
                    :aria-label="`Ver ${file.title}`"
                    @click="openViewer(file)"
                >
                    <img
                        v-if="canView && file.is_image"
                        :src="show.url(file.id)"
                        alt=""
                        loading="lazy"
                        class="h-full w-full object-cover"
                    />
                    <FileText v-else class="size-5 text-muted-foreground" />
                </button>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ file.title }}</p>
                    <p
                        class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground"
                    >
                        <Badge variant="secondary" class="text-[10px]">{{
                            file.type_label
                        }}</Badge>
                        <span>{{
                            file.study_date
                                ? formatDate(file.study_date)
                                : `Subido ${formatDate(file.created_at)}`
                        }}</span>
                        <span>{{ formatBytes(file.size_bytes) }}</span>
                        <span
                            v-if="
                                consultationId &&
                                file.consultation_id === consultationId
                            "
                            class="font-medium text-primary"
                        >
                            · De esta consulta
                        </span>
                    </p>
                </div>

                <div class="flex shrink-0 gap-1">
                    <template v-if="canView">
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Ver"
                            @click="openViewer(file)"
                            ><Eye
                        /></Button>
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Descargar"
                            as-child
                        >
                            <a
                                :href="
                                    show.url(file.id, {
                                        query: { download: 1 },
                                    })
                                "
                                ><Download
                            /></a>
                        </Button>
                    </template>
                    <span
                        v-else
                        class="flex items-center gap-1 px-2 text-xs text-muted-foreground"
                        title="Solo el médico puede abrir los estudios"
                    >
                        <Lock class="size-3.5" />
                    </span>
                    <Button
                        v-if="canDelete"
                        size="icon-sm"
                        variant="ghost"
                        class="text-destructive"
                        aria-label="Eliminar"
                        @click="deleting = file"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </li>
        </ul>

        <UploadFilesDialog
            v-if="canUpload"
            v-model:open="uploadOpen"
            :patient-id="patientId"
            :consultation-id="consultationId"
            :types="types"
        />

        <FileViewerDialog
            v-if="canView"
            v-model:open="viewerOpen"
            v-model:index="viewerIndex"
            :files="visible"
        />

        <Dialog
            :open="deleting !== null"
            @update:open="
                (o) => {
                    if (!o) deleting = null;
                }
            "
        >
            <DialogContent v-if="deleting">
                <DialogHeader>
                    <DialogTitle>¿Eliminar "{{ deleting.title }}"?</DialogTitle>
                    <DialogDescription>
                        Deja de mostrarse en el expediente. El archivo se
                        conserva por la NOM-004 y el motivo queda en la
                        bitácora.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <label for="delete_reason" class="text-sm font-medium"
                        >Motivo *</label
                    >
                    <Input
                        id="delete_reason"
                        v-model="deleteReason"
                        maxlength="255"
                        placeholder="Ej. Se subió al paciente equivocado"
                    />
                    <InputError :message="deleteErrors.reason" />
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        :disabled="deleteReason.trim().length < 3"
                        @click="confirmDelete"
                    >
                        Eliminar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
