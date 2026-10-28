<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{
    paginator: Paginated<unknown>;
    noun?: string;
}>();
</script>

<template>
    <nav
        v-if="paginator.meta.total > 0"
        class="flex flex-wrap items-center justify-between gap-4 text-sm text-muted-foreground"
        aria-label="Paginación"
    >
        <p>
            {{ paginator.meta.from }}–{{ paginator.meta.to }} de
            {{ paginator.meta.total }} {{ noun ?? 'registros' }}
        </p>
        <div class="flex items-center gap-2">
            <Button
                v-if="paginator.links.prev"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="paginator.links.prev" preserve-scroll>
                    <ChevronLeft /> Anterior
                </Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled>
                <ChevronLeft /> Anterior
            </Button>

            <span class="px-1">
                {{ paginator.meta.current_page }} /
                {{ paginator.meta.last_page }}
            </span>

            <Button
                v-if="paginator.links.next"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="paginator.links.next" preserve-scroll>
                    Siguiente <ChevronRight />
                </Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled>
                Siguiente <ChevronRight />
            </Button>
        </div>
    </nav>
</template>
