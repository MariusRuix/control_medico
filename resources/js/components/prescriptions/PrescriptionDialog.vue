<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileText, Star, Trash2, TriangleAlert } from '@lucide/vue';
import { computed, watch } from 'vue';
import PrescriptionController from '@/actions/App/Http/Controllers/Clinical/PrescriptionController';
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
import type { MedicationOption, PrescriptionItemInput } from '@/types';
import MedicationPicker from './MedicationPicker.vue';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    consultationId: number;
    favorites: MedicationOption[];
    criticalAllergies: string[];
}>();

const emit = defineEmits<{ issued: [] }>();

const form = useForm({
    general_instructions: '',
    items: [] as PrescriptionItemInput[],
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

function fromMedication(med: MedicationOption): PrescriptionItemInput {
    return {
        medication_id: med.id,
        medication_name: med.name,
        dose: med.default_dose ?? '',
        route: med.route ?? '',
        frequency: med.default_frequency ?? '',
        duration: med.default_duration ?? '',
        quantity: '',
        instructions: med.default_instructions ?? '',
        save_to_vademecum: false,
    };
}

function add(med: MedicationOption): void {
    if (form.items.some((i) => i.medication_id === med.id)) {
        return;
    }

    form.items = [...form.items, fromMedication(med)];
}

function addFree(name: string): void {
    form.items = [
        ...form.items,
        {
            medication_id: null,
            medication_name: name,
            dose: '',
            route: 'Oral',
            frequency: '',
            duration: '',
            quantity: '',
            instructions: '',
            save_to_vademecum: false,
        },
    ];
}

function remove(index: number): void {
    form.items = form.items.filter((_, i) => i !== index);
}

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function itemError(
    index: number,
    field: keyof PrescriptionItemInput,
): string | undefined {
    return errors.value[`items.${index}.${field}`];
}

function normalize(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

/** Raíces de 5 letras de cada palabra significativa ("sulfas" → "sulfa"). */
function stems(value: string): string[] {
    return normalize(value)
        .split(/[^a-z]+/)
        .filter((word) => word.length >= 4)
        .map((word) => word.slice(0, 5));
}

/**
 * Aviso heurístico: el medicamento comparte raíz con una alergia crítica
 * (p. ej. "Sulfametoxazol" con "Sulfas"). No sustituye el criterio médico.
 */
function allergyWarning(name: string): string | null {
    const medication = normalize(name);
    const medicationStems = stems(name);

    const match = props.criticalAllergies.find((allergy) => {
        const allergyText = normalize(allergy);

        return (
            stems(allergy).some((stem) => medication.includes(stem)) ||
            medicationStems.some((stem) => allergyText.includes(stem))
        );
    });

    return match ? `Posible alergia: ${match}` : null;
}

function submit(): void {
    form.post(PrescriptionController.store.url(props.consultationId), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            emit('issued');
        },
    });
}

const fieldClass =
    'h-8 w-full min-w-0 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30';
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Nueva receta</DialogTitle>
                    <DialogDescription>
                        Al emitirla se asigna folio, se genera el PDF y ya no se
                        puede modificar (solo anular).
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="criticalAllergies.length"
                    class="flex gap-2 rounded-lg border border-red-200 bg-red-50 p-2.5 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    Alergias críticas: {{ criticalAllergies.join(' · ') }}
                </div>

                <div v-if="favorites.length" class="space-y-1.5">
                    <p class="text-xs font-medium text-muted-foreground">
                        Vademécum (un clic para agregar)
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="med in favorites"
                            :key="med.id"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs transition-colors hover:bg-accent disabled:opacity-40"
                            :disabled="
                                form.items.some(
                                    (i) => i.medication_id === med.id,
                                )
                            "
                            @click="add(med)"
                        >
                            <Star
                                class="size-3 fill-amber-400 text-amber-400"
                            />
                            {{ med.name }}
                        </button>
                    </div>
                </div>

                <MedicationPicker @pick="add" @free="addFree" />
                <InputError :message="errors.items" />

                <ol class="space-y-3">
                    <li
                        v-for="(item, i) in form.items"
                        :key="i"
                        class="space-y-2 rounded-lg border p-3"
                    >
                        <div class="flex items-start gap-2">
                            <span
                                class="mt-1.5 text-sm font-semibold text-muted-foreground"
                                >{{ i + 1 }}.</span
                            >
                            <div class="min-w-0 flex-1 space-y-1">
                                <input
                                    v-model="item.medication_name"
                                    :class="[fieldClass, 'font-medium']"
                                    :aria-invalid="
                                        !!itemError(i, 'medication_name') ||
                                        undefined
                                    "
                                    aria-label="Medicamento"
                                    maxlength="255"
                                />
                                <InputError
                                    :message="itemError(i, 'medication_name')"
                                />
                                <p
                                    v-if="allergyWarning(item.medication_name)"
                                    class="flex items-center gap-1 text-xs font-medium text-destructive"
                                >
                                    <TriangleAlert class="size-3.5" />
                                    {{ allergyWarning(item.medication_name) }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Quitar"
                                @click="remove(i)"
                            >
                                <Trash2 />
                            </Button>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pl-5 sm:grid-cols-5">
                            <label
                                class="grid gap-1 text-xs text-muted-foreground"
                            >
                                Dosis *
                                <input
                                    v-model="item.dose"
                                    :class="fieldClass"
                                    placeholder="1 tableta"
                                    maxlength="100"
                                    :aria-invalid="
                                        !!itemError(i, 'dose') || undefined
                                    "
                                />
                            </label>
                            <label
                                class="grid gap-1 text-xs text-muted-foreground"
                            >
                                Vía
                                <input
                                    v-model="item.route"
                                    :class="fieldClass"
                                    placeholder="Oral"
                                    maxlength="50"
                                    list="routes"
                                />
                            </label>
                            <label
                                class="grid gap-1 text-xs text-muted-foreground"
                            >
                                Frecuencia *
                                <input
                                    v-model="item.frequency"
                                    :class="fieldClass"
                                    placeholder="Cada 8 horas"
                                    maxlength="100"
                                    :aria-invalid="
                                        !!itemError(i, 'frequency') || undefined
                                    "
                                />
                            </label>
                            <label
                                class="grid gap-1 text-xs text-muted-foreground"
                            >
                                Duración
                                <input
                                    v-model="item.duration"
                                    :class="fieldClass"
                                    placeholder="5 días"
                                    maxlength="100"
                                />
                            </label>
                            <label
                                class="grid gap-1 text-xs text-muted-foreground"
                            >
                                Cantidad
                                <input
                                    v-model="item.quantity"
                                    :class="fieldClass"
                                    placeholder="1 caja"
                                    maxlength="50"
                                />
                            </label>
                        </div>
                        <div class="pl-5">
                            <InputError
                                :message="
                                    itemError(i, 'dose') ??
                                    itemError(i, 'frequency')
                                "
                            />
                            <input
                                v-model="item.instructions"
                                :class="fieldClass"
                                placeholder="Indicaciones (p. ej. tomar con alimentos)"
                                maxlength="500"
                                aria-label="Indicaciones"
                            />
                            <label
                                v-if="item.medication_id === null"
                                class="mt-2 flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <input
                                    v-model="item.save_to_vademecum"
                                    type="checkbox"
                                    class="size-3.5"
                                />
                                Guardar en mi vademécum con esta posología
                            </label>
                        </div>
                    </li>
                </ol>

                <p
                    v-if="form.items.length === 0"
                    class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                >
                    Agrega medicamentos desde el vademécum o el buscador.
                </p>

                <div class="grid gap-1.5">
                    <label
                        for="general_instructions"
                        class="text-sm font-medium"
                        >Indicaciones generales</label
                    >
                    <textarea
                        id="general_instructions"
                        v-model="form.general_instructions"
                        rows="3"
                        maxlength="2000"
                        placeholder="Dieta, cuidados, signos de alarma, próxima cita…"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                    />
                    <InputError :message="errors.general_instructions" />
                </div>

                <InputError :message="errors.prescriber" />

                <datalist id="routes">
                    <option
                        v-for="r in [
                            'Oral',
                            'Sublingual',
                            'Tópica',
                            'Oftálmica',
                            'Ótica',
                            'Nasal',
                            'Inhalada',
                            'Intramuscular',
                            'Intravenosa',
                            'Subcutánea',
                            'Rectal',
                            'Vaginal',
                        ]"
                        :key="r"
                        :value="r"
                    />
                </datalist>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        :disabled="form.processing || form.items.length === 0"
                    >
                        <FileText /> Emitir receta
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
