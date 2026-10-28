<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileImage, FileText, Upload, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import MedicalFileController from '@/actions/App/Http/Controllers/MedicalFiles/MedicalFileController';
import InputError from '@/components/InputError.vue';
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
import { formatBytes } from '@/lib/format';
import type { SelectOption } from '@/types';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    patientId: number;
    consultationId?: number | null;
    types: SelectOption[];
    maxMb?: number;
}>();

const ACCEPT = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
const MAX_FILES = 10;
const maxBytes = computed(() => (props.maxMb ?? 20) * 1024 * 1024);
const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    files: [] as File[],
    type: 'laboratorio',
    title: '',
    study_date: '',
    notes: '',
    consultation_id: null as number | null,
});

const linkToConsultation = ref(true);
const clientErrors = ref<string[]>([]);
const dragging = ref(false);
const input = ref<HTMLInputElement | null>(null);

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
        clientErrors.value = [];
        linkToConsultation.value = true;
    }
});

function addFiles(list: FileList | null): void {
    if (!list) {
        return;
    }

    const errors: string[] = [];
    const accepted: File[] = [];

    for (const file of Array.from(list)) {
        if (!ACCEPT.includes(file.type)) {
            errors.push(`${file.name}: solo PDF o imágenes (JPG, PNG, WEBP).`);
        } else if (file.size > maxBytes.value) {
            errors.push(`${file.name}: pesa más de ${props.maxMb ?? 20} MB.`);
        } else {
            accepted.push(file);
        }
    }

    const merged = [...form.files, ...accepted];

    if (merged.length > MAX_FILES) {
        errors.push(`Puedes subir hasta ${MAX_FILES} archivos a la vez.`);
    }

    form.files = merged.slice(0, MAX_FILES);
    clientErrors.value = errors;

    if (input.value) {
        input.value.value = '';
    }
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
}

function remove(index: number): void {
    form.files = form.files.filter((_, i) => i !== index);
}

const serverFileErrors = computed(() =>
    Object.entries(form.errors as Record<string, string>)
        .filter(([key]) => key.startsWith('files'))
        .map(([, message]) => message),
);

function submit(): void {
    form.transform((data) => ({
        ...data,
        consultation_id:
            props.consultationId && linkToConsultation.value
                ? props.consultationId
                : null,
    })).post(MedicalFileController.store.url(props.patientId), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-xl">
            <form class="space-y-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Subir estudios</DialogTitle>
                    <DialogDescription>
                        PDF o imágenes de laboratorio, rayos X, ultrasonido,
                        etc. Se guardan en un almacenamiento privado.
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-6 text-center text-sm transition-colors"
                    :class="
                        dragging
                            ? 'border-primary bg-primary/5'
                            : 'border-border hover:bg-muted/40'
                    "
                    role="button"
                    tabindex="0"
                    @click="input?.click()"
                    @keydown.enter.prevent="input?.click()"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="onDrop"
                >
                    <Upload class="size-6 text-muted-foreground" />
                    <p>
                        <span class="font-medium"
                            >Arrastra los archivos aquí</span
                        >
                        o haz clic para elegirlos
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Hasta {{ MAX_FILES }} archivos de {{ maxMb ?? 20 }} MB ·
                        PDF, JPG, PNG, WEBP
                    </p>
                    <input
                        ref="input"
                        type="file"
                        multiple
                        :accept="ACCEPT.join(',')"
                        class="hidden"
                        @change="
                            addFiles(($event.target as HTMLInputElement).files)
                        "
                    />
                </div>

                <ul
                    v-if="form.files.length"
                    class="divide-y rounded-lg border text-sm"
                >
                    <li
                        v-for="(file, i) in form.files"
                        :key="`${file.name}-${i}`"
                        class="flex items-center gap-2 px-3 py-2"
                    >
                        <FileText
                            v-if="file.type === 'application/pdf'"
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <FileImage
                            v-else
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <span class="min-w-0 flex-1 truncate">{{
                            file.name
                        }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            formatBytes(file.size)
                        }}</span>
                        <Button
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Quitar"
                            @click="remove(i)"
                        >
                            <X />
                        </Button>
                    </li>
                </ul>
                <div
                    v-for="message in [...clientErrors, ...serverFileErrors]"
                    :key="message"
                >
                    <InputError :message="message" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="file_type">Tipo *</Label>
                        <select
                            id="file_type"
                            v-model="form.type"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                        >
                            <option
                                v-for="t in types"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="study_date">Fecha del estudio</Label>
                        <Input
                            id="study_date"
                            v-model="form.study_date"
                            type="date"
                            :max="today"
                        />
                        <InputError :message="form.errors.study_date" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="file_title">Título</Label>
                        <Input
                            id="file_title"
                            v-model="form.title"
                            maxlength="150"
                            placeholder="Ej. Biometría hemática (si lo dejas vacío se usa el nombre del archivo)"
                        />
                        <InputError :message="form.errors.title" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="file_notes">Notas</Label>
                        <Input
                            id="file_notes"
                            v-model="form.notes"
                            maxlength="500"
                            placeholder="Laboratorio, médico que lo solicitó…"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>

                <label
                    v-if="consultationId"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="linkToConsultation"
                        type="checkbox"
                        class="size-4"
                    />
                    Vincular a esta consulta
                </label>

                <div
                    v-if="form.progress"
                    class="h-1.5 overflow-hidden rounded-full bg-muted"
                >
                    <div
                        class="h-full bg-primary transition-all"
                        :style="{ width: `${form.progress.percentage ?? 0}%` }"
                    />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        :disabled="form.processing || form.files.length === 0"
                    >
                        <Upload /> Subir
                        {{
                            form.files.length > 1
                                ? `${form.files.length} archivos`
                                : ''
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
