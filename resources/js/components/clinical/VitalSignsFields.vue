<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { bmiCategory, calculateBmi } from '@/lib/vitals';
import type { VitalSigns } from '@/types';

const vitals = defineModel<VitalSigns>({ required: true });

defineProps<{ errors: Partial<Record<keyof VitalSigns, string>> }>();

const bmi = computed(() =>
    calculateBmi(vitals.value.weight_kg, vitals.value.height_cm),
);

type Field = {
    key: keyof VitalSigns;
    label: string;
    unit: string;
    step: string;
    min: number;
    max: number;
};

const fields: Field[] = [
    {
        key: 'weight_kg',
        label: 'Peso',
        unit: 'kg',
        step: '0.1',
        min: 0.5,
        max: 400,
    },
    {
        key: 'height_cm',
        label: 'Talla',
        unit: 'cm',
        step: '0.5',
        min: 20,
        max: 250,
    },
    {
        key: 'temperature_c',
        label: 'Temperatura',
        unit: '°C',
        step: '0.1',
        min: 30,
        max: 45,
    },
    {
        key: 'heart_rate',
        label: 'FC',
        unit: 'lpm',
        step: '1',
        min: 20,
        max: 250,
    },
    {
        key: 'respiratory_rate',
        label: 'FR',
        unit: 'rpm',
        step: '1',
        min: 5,
        max: 80,
    },
    {
        key: 'oxygen_saturation',
        label: 'SpO₂',
        unit: '%',
        step: '1',
        min: 50,
        max: 100,
    },
    {
        key: 'glucose_mg_dl',
        label: 'Glucosa',
        unit: 'mg/dL',
        step: '1',
        min: 20,
        max: 800,
    },
];

function setNumber(key: keyof VitalSigns, value: string | number): void {
    vitals.value = {
        ...vitals.value,
        [key]: value === '' ? null : Number(value),
    };
}
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <div v-for="field in fields" :key="field.key" class="grid gap-1">
            <label
                :for="field.key"
                class="text-xs font-medium text-muted-foreground"
            >
                {{ field.label }}
                <span class="font-normal">({{ field.unit }})</span>
            </label>
            <Input
                :id="field.key"
                type="number"
                inputmode="decimal"
                :step="field.step"
                :min="field.min"
                :max="field.max"
                :model-value="vitals[field.key] ?? ''"
                :aria-invalid="!!errors[field.key] || undefined"
                @update:model-value="(v) => setNumber(field.key, v)"
            />
            <InputError :message="errors[field.key]" />
        </div>

        <div class="col-span-2 grid gap-1">
            <label
                for="systolic_bp"
                class="text-xs font-medium text-muted-foreground"
            >
                TA <span class="font-normal">(mmHg)</span>
            </label>
            <div class="flex items-center gap-1">
                <Input
                    id="systolic_bp"
                    type="number"
                    inputmode="numeric"
                    min="50"
                    max="260"
                    placeholder="Sis"
                    :model-value="vitals.systolic_bp ?? ''"
                    :aria-invalid="!!errors.systolic_bp || undefined"
                    @update:model-value="(v) => setNumber('systolic_bp', v)"
                />
                <span class="text-muted-foreground">/</span>
                <Input
                    id="diastolic_bp"
                    type="number"
                    inputmode="numeric"
                    min="30"
                    max="160"
                    placeholder="Dia"
                    aria-label="Presión diastólica"
                    :model-value="vitals.diastolic_bp ?? ''"
                    :aria-invalid="!!errors.diastolic_bp || undefined"
                    @update:model-value="(v) => setNumber('diastolic_bp', v)"
                />
            </div>
            <InputError :message="errors.systolic_bp ?? errors.diastolic_bp" />
        </div>

        <div class="grid gap-1">
            <span class="text-xs font-medium text-muted-foreground">IMC</span>
            <p
                class="flex h-9 items-center gap-2 rounded-md border border-dashed px-3 text-sm"
            >
                <span class="font-semibold tabular-nums">{{ bmi ?? '—' }}</span>
                <span
                    v-if="bmi"
                    class="truncate text-xs text-muted-foreground"
                    >{{ bmiCategory(bmi) }}</span
                >
            </p>
        </div>
    </div>
</template>
