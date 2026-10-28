<script setup lang="ts">
import { Star, X } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { DiagnosisEntry } from '@/types';

const diagnoses = defineModel<DiagnosisEntry[]>({ required: true });

defineProps<{ readonly?: boolean }>();

function makePrincipal(index: number): void {
    diagnoses.value = diagnoses.value.map((d, i) => ({
        ...d,
        type: i === index ? 'principal' : 'secundario',
    }));
}

function remove(index: number): void {
    const next = diagnoses.value.filter((_, i) => i !== index);

    // Si se quitó el principal, el primero restante toma su lugar.
    if (next.length && !next.some((d) => d.type === 'principal')) {
        next[0] = { ...next[0], type: 'principal' };
    }

    diagnoses.value = next;
}
</script>

<template>
    <p v-if="diagnoses.length === 0" class="text-sm text-muted-foreground">
        Sin diagnósticos.
    </p>
    <ul v-else class="divide-y rounded-lg border">
        <li
            v-for="(dx, i) in diagnoses"
            :key="dx.cie10_code_id"
            class="flex items-center gap-3 px-3 py-2"
        >
            <span class="w-14 shrink-0 font-mono text-xs font-semibold">{{
                dx.code
            }}</span>
            <span class="min-w-0 flex-1 text-sm">{{ dx.description }}</span>
            <Badge :variant="dx.type === 'principal' ? 'default' : 'secondary'">
                {{ dx.type === 'principal' ? 'Principal' : 'Secundario' }}
            </Badge>
            <template v-if="!readonly">
                <Button
                    v-if="dx.type !== 'principal'"
                    type="button"
                    size="icon-sm"
                    variant="ghost"
                    aria-label="Marcar como principal"
                    title="Marcar como principal"
                    @click="makePrincipal(i)"
                >
                    <Star />
                </Button>
                <Button
                    type="button"
                    size="icon-sm"
                    variant="ghost"
                    aria-label="Quitar"
                    @click="remove(i)"
                >
                    <X />
                </Button>
            </template>
        </li>
    </ul>
</template>
