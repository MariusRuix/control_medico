<script setup lang="ts">
defineProps<{
    name: string;
    defaultValue?: string | null;
    id?: string;
    rows?: number;
    placeholder?: string;
    invalid?: boolean;
    required?: boolean;
}>();
</script>

<template>
    <textarea
        :id="id ?? name"
        :name="name"
        :rows="rows ?? 3"
        :placeholder="placeholder"
        :required="required"
        :aria-invalid="invalid || undefined"
        :value="defaultValue ?? ''"
        class="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
    />
</template>
