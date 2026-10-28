<script setup lang="ts">
import type { SelectOption } from '@/types';

defineProps<{
    name: string;
    options: SelectOption[];
    defaultValue?: string | null;
    placeholder?: string;
    id?: string;
    invalid?: boolean;
}>();
</script>

<template>
    <select
        :id="id ?? name"
        :name="name"
        :aria-invalid="invalid || undefined"
        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
    >
        <option value="" :selected="!defaultValue">
            {{ placeholder ?? 'Sin especificar' }}
        </option>
        <option
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            :selected="option.value === defaultValue"
        >
            {{ option.label }}
        </option>
    </select>
</template>
