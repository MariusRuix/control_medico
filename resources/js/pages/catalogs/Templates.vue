<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import NoteTemplateController from '@/actions/App/Http/Controllers/Catalogs/NoteTemplateController';
import CatalogTabs from '@/components/catalogs/CatalogTabs.vue';
import Heading from '@/components/Heading.vue';
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
import { Label } from '@/components/ui/label';
import { index } from '@/routes/catalogs/vademecum';

type Field = 'reason' | 'subjective' | 'objective' | 'assessment' | 'plan';
type Template = {
    id: number;
    name: string;
    structure: Partial<Record<Field, string>>;
    is_active: boolean;
};

defineProps<{ templates: Template[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Catálogos', href: index() }],
    },
});

const FIELDS: { key: Field; label: string; rows: number }[] = [
    { key: 'reason', label: 'Motivo de consulta', rows: 1 },
    { key: 'subjective', label: 'S · Subjetivo', rows: 4 },
    { key: 'objective', label: 'O · Objetivo', rows: 4 },
    { key: 'assessment', label: 'A · Análisis', rows: 2 },
    { key: 'plan', label: 'P · Plan', rows: 3 },
];

const dialogOpen = ref(false);
const editing = ref<Template | null>(null);
const deleting = ref<Template | null>(null);

const form = useForm({
    name: '',
    is_active: true,
    structure: {
        reason: '',
        subjective: '',
        objective: '',
        assessment: '',
        plan: '',
    } as Record<Field, string>,
});

function openForm(template: Template | null): void {
    editing.value = template;
    form.clearErrors();
    form.defaults({
        name: template?.name ?? '',
        is_active: template?.is_active ?? true,
        structure: Object.fromEntries(
            FIELDS.map((f) => [f.key, template?.structure[f.key] ?? '']),
        ) as Record<Field, string>,
    });
    form.reset();
    dialogOpen.value = true;
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };

    if (editing.value) {
        form.put(NoteTemplateController.update.url(editing.value.id), options);
    } else {
        form.post(NoteTemplateController.store.url(), options);
    }
}

function remove(): void {
    if (!deleting.value) {
        return;
    }

    router.delete(NoteTemplateController.destroy.url(deleting.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Plantillas de nota" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Catálogos"
                description="Medicamentos, servicios, plantillas y diagnósticos"
            />
            <Button @click="openForm(null)"><Plus /> Nueva plantilla</Button>
        </div>

        <CatalogTabs />

        <p class="text-sm text-muted-foreground">
            Al aplicar una plantilla en la consulta, solo se llenan los
            apartados vacíos. No se puede firmar una nota con el texto de la
            plantilla sin modificar.
        </p>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="t in templates"
                :key="t.id"
                class="flex flex-col gap-3 rounded-xl border p-4"
                :class="t.is_active ? '' : 'opacity-60'"
            >
                <div class="flex items-start justify-between gap-2">
                    <h2 class="font-medium">
                        {{ t.name }}
                        <Badge
                            v-if="!t.is_active"
                            variant="outline"
                            class="ml-1"
                            >Inactiva</Badge
                        >
                    </h2>
                    <div class="flex gap-1">
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Editar"
                            @click="openForm(t)"
                            ><Pencil
                        /></Button>
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            class="text-destructive"
                            aria-label="Eliminar"
                            @click="deleting = t"
                            ><Trash2
                        /></Button>
                    </div>
                </div>
                <dl class="space-y-2 text-xs">
                    <template v-for="f in FIELDS" :key="f.key">
                        <div v-if="t.structure[f.key]">
                            <dt class="font-medium text-muted-foreground">
                                {{ f.label }}
                            </dt>
                            <dd class="line-clamp-3 whitespace-pre-line">
                                {{ t.structure[f.key] }}
                            </dd>
                        </div>
                    </template>
                </dl>
            </article>
            <p
                v-if="templates.length === 0"
                class="text-sm text-muted-foreground"
            >
                Aún no hay plantillas.
            </p>
        </div>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
            <form class="space-y-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Editar plantilla' : 'Nueva plantilla'
                    }}</DialogTitle>
                    <DialogDescription
                        >Escribe el texto inicial de cada apartado (por ejemplo,
                        los encabezados de la exploración
                        física).</DialogDescription
                    >
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="tpl_name">Nombre *</Label>
                    <Input
                        id="tpl_name"
                        v-model="form.name"
                        maxlength="150"
                        placeholder="Ej. Control de diabetes"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div v-for="f in FIELDS" :key="f.key" class="grid gap-1.5">
                    <Label :for="`tpl_${f.key}`">{{ f.label }}</Label>
                    <textarea
                        :id="`tpl_${f.key}`"
                        v-model="form.structure[f.key]"
                        :rows="f.rows"
                        maxlength="5000"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                    />
                </div>
                <InputError
                    :message="(form.errors as Record<string, string>).structure"
                />

                <label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4"
                    />
                    Activa</label
                >

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
                <DialogTitle
                    >¿Eliminar la plantilla "{{ deleting.name }}"?</DialogTitle
                >
                <DialogDescription
                    >Las notas que ya la usaron conservan su
                    texto.</DialogDescription
                >
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child
                    ><Button variant="secondary">Cancelar</Button></DialogClose
                >
                <Button variant="destructive" @click="remove">Eliminar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
