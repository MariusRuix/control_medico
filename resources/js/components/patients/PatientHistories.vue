<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import PatientHistoryController from '@/actions/App/Http/Controllers/Patients/PatientHistoryController';
import NativeSelect from '@/components/form/NativeSelect.vue';
import Textarea from '@/components/form/Textarea.vue';
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
import type { PatientHistory, SelectOption } from '@/types';

const props = defineProps<{
    patientId: number;
    histories: PatientHistory[];
    types: SelectOption[];
    canManage: boolean;
}>();

const dialogOpen = ref(false);
const editing = ref<PatientHistory | null>(null);
const deleting = ref<PatientHistory | null>(null);

const grouped = computed(() =>
    props.types
        .map((type) => ({
            ...type,
            items: props.histories.filter((h) => h.type === type.value),
        }))
        .filter((group) => group.items.length > 0),
);

const formAction = computed(() =>
    editing.value
        ? PatientHistoryController.update.form({
              patient: props.patientId,
              history: editing.value.id,
          })
        : PatientHistoryController.store.form(props.patientId),
);

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(history: PatientHistory): void {
    editing.value = history;
    dialogOpen.value = true;
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-medium">Antecedentes y alergias</h2>
            <Button
                v-if="canManage"
                size="sm"
                variant="outline"
                @click="openCreate"
            >
                <Plus /> Agregar
            </Button>
        </div>

        <p v-if="grouped.length === 0" class="text-sm text-muted-foreground">
            Sin antecedentes registrados.
        </p>

        <div v-for="group in grouped" :key="group.value" class="space-y-2">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ group.label }}
            </h3>
            <ul class="divide-y rounded-lg border">
                <li
                    v-for="item in group.items"
                    :key="item.id"
                    class="flex items-start justify-between gap-3 p-3"
                >
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm break-words whitespace-pre-line">
                            <TriangleAlert
                                v-if="item.is_critical"
                                class="mr-1 inline size-4 text-destructive"
                                aria-label="Crítico"
                            />
                            {{ item.description }}
                        </p>
                        <div class="flex flex-wrap gap-1">
                            <Badge v-if="item.is_critical" variant="destructive"
                                >Crítico</Badge
                            >
                            <Badge v-if="item.relative" variant="secondary">{{
                                item.relative
                            }}</Badge>
                        </div>
                    </div>
                    <div v-if="canManage" class="flex shrink-0 gap-1">
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Editar"
                            @click="openEdit(item)"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Eliminar"
                            @click="deleting = item"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Alta / edición -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <Form
                    :key="editing?.id ?? 'new'"
                    v-bind="formAction"
                    :options="{ preserveScroll: true }"
                    class="space-y-4"
                    @success="dialogOpen = false"
                    v-slot="{ errors, processing }"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                editing
                                    ? 'Editar antecedente'
                                    : 'Nuevo antecedente'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            Esta información se guarda cifrada y solo la ve el
                            médico.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="type">Tipo *</Label>
                        <NativeSelect
                            name="type"
                            :options="types"
                            :default-value="editing?.type ?? 'alergia'"
                            placeholder="Selecciona…"
                            :invalid="!!errors.type"
                        />
                        <InputError :message="errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Descripción *</Label>
                        <Textarea
                            name="description"
                            :default-value="editing?.description"
                            :invalid="!!errors.description"
                            required
                            placeholder="Ej. Penicilina — urticaria generalizada"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="relative"
                            >Parentesco (heredo-familiares)</Label
                        >
                        <Input
                            id="relative"
                            name="relative"
                            :default-value="editing?.relative ?? ''"
                            placeholder="Ej. Madre"
                        />
                        <InputError :message="errors.relative" />
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="is_critical"
                            value="1"
                            :checked="editing?.is_critical ?? false"
                            class="size-4 rounded border-input accent-destructive"
                        />
                        Marcar como crítico (se muestra como alerta a todo el
                        personal)
                    </label>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary"
                                >Cancelar</Button
                            >
                        </DialogClose>
                        <Button type="submit" :disabled="processing"
                            >Guardar</Button
                        >
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <!-- Confirmar eliminación -->
        <Dialog
            :open="deleting !== null"
            @update:open="
                (open) => {
                    if (!open) deleting = null;
                }
            "
        >
            <DialogContent>
                <Form
                    v-if="deleting"
                    v-bind="
                        PatientHistoryController.destroy.form({
                            patient: patientId,
                            history: deleting.id,
                        })
                    "
                    :options="{ preserveScroll: true }"
                    @success="deleting = null"
                    v-slot="{ processing }"
                >
                    <DialogHeader>
                        <DialogTitle>¿Eliminar este antecedente?</DialogTitle>
                        <DialogDescription>
                            "{{ deleting.description }}". Quedará registrado en
                            la bitácora.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="mt-4 gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary"
                                >Cancelar</Button
                            >
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                        >
                            Eliminar
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
