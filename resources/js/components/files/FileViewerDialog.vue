<script setup lang="ts">
import { ChevronLeft, ChevronRight, Download, ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDate } from '@/lib/format';
import { show } from '@/routes/medical-files';
import type { MedicalFileItem } from '@/types';

const open = defineModel<boolean>('open', { required: true });
const index = defineModel<number>('index', { required: true });

const props = defineProps<{ files: MedicalFileItem[] }>();

const current = computed(() => props.files[index.value] ?? null);

function go(step: 1 | -1): void {
    const total = props.files.length;
    index.value = (index.value + step + total) % total;
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowRight') go(1);
    if (event.key === 'ArrowLeft') go(-1);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-w-[95vw] gap-3 sm:max-w-5xl"
            @keydown="onKeydown"
        >
            <template v-if="current">
                <DialogHeader class="pr-8">
                    <DialogTitle class="truncate">{{
                        current.title
                    }}</DialogTitle>
                    <DialogDescription>
                        {{ current.type_label }}
                        <template v-if="current.study_date">
                            · {{ formatDate(current.study_date) }}</template
                        >
                        <template v-if="current.notes">
                            · {{ current.notes }}</template
                        >
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="relative h-[68vh] overflow-hidden rounded-lg border bg-muted/40"
                >
                    <img
                        v-if="current.is_image"
                        :key="current.id"
                        :src="show.url(current.id)"
                        :alt="current.title"
                        class="h-full w-full object-contain"
                    />
                    <iframe
                        v-else
                        :key="`pdf-${current.id}`"
                        :src="show.url(current.id)"
                        :title="current.title"
                        class="h-full w-full"
                    />
                </div>

                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1">
                        <template v-if="files.length > 1">
                            <Button
                                variant="outline"
                                size="icon-sm"
                                aria-label="Anterior"
                                @click="go(-1)"
                                ><ChevronLeft
                            /></Button>
                            <span
                                class="px-2 text-sm text-muted-foreground tabular-nums"
                                >{{ index + 1 }} / {{ files.length }}</span
                            >
                            <Button
                                variant="outline"
                                size="icon-sm"
                                aria-label="Siguiente"
                                @click="go(1)"
                                ><ChevronRight
                            /></Button>
                        </template>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="ghost" size="sm" as-child>
                            <a
                                :href="show.url(current.id)"
                                target="_blank"
                                rel="noopener"
                                ><ExternalLink /> Abrir en otra pestaña</a
                            >
                        </Button>
                        <Button variant="outline" size="sm" as-child>
                            <a
                                :href="
                                    show.url(current.id, {
                                        query: { download: 1 },
                                    })
                                "
                                ><Download /> Descargar</a
                            >
                        </Button>
                    </div>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
