<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import ServiceController from '@/actions/App/Http/Controllers/Catalogs/ServiceController';
import CatalogTabs from '@/components/catalogs/CatalogTabs.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { formatCurrency } from '@/lib/format';
import { index } from '@/routes/catalogs/vademecum';

type Service = {
    id: number;
    name: string;
    description: string | null;
    price: string;
    is_appointable: boolean;
    duration_minutes: number | null;
    color: string | null;
    is_active: boolean;
    appointments_count: number;
};

defineProps<{ services: Service[]; colors: string[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Catálogos', href: index() }],
    },
});

const swatch: Record<string, string> = {
    sky: 'bg-sky-500',
    indigo: 'bg-indigo-500',
    teal: 'bg-teal-500',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    rose: 'bg-rose-500',
    violet: 'bg-violet-500',
    zinc: 'bg-zinc-500',
};

const dialogOpen = ref(false);
const editing = ref<Service | null>(null);

const form = useForm({
    name: '',
    description: '',
    price: '',
    is_appointable: true,
    duration_minutes: 30 as number | null,
    color: 'sky' as string | null,
    is_active: true,
});

function openForm(service: Service | null): void {
    editing.value = service;
    form.clearErrors();
    form.defaults({
        name: service?.name ?? '',
        description: service?.description ?? '',
        price: service?.price ?? '',
        is_appointable: service?.is_appointable ?? true,
        duration_minutes: service?.duration_minutes ?? 30,
        color: service?.color ?? 'sky',
        is_active: service?.is_active ?? true,
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
        form.put(ServiceController.update.url(editing.value.id), options);
    } else {
        form.post(ServiceController.store.url(), options);
    }
}
</script>

<template>
    <Head title="Servicios y precios" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Catálogos"
                description="Medicamentos, servicios, plantillas y diagnósticos"
            />
            <Button @click="openForm(null)"><Plus /> Nuevo servicio</Button>
        </div>

        <CatalogTabs />

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Servicio</th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Precio
                        </th>
                        <th
                            class="hidden px-4 py-2.5 font-medium sm:table-cell"
                        >
                            En agenda
                        </th>
                        <th
                            class="hidden px-4 py-2.5 text-right font-medium md:table-cell"
                        >
                            Citas
                        </th>
                        <th class="w-12" />
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="s in services"
                        :key="s.id"
                        :class="s.is_active ? '' : 'opacity-60'"
                    >
                        <td class="px-4 py-2.5">
                            <p class="flex items-center gap-2 font-medium">
                                <span
                                    v-if="s.color"
                                    class="size-2.5 rounded-full"
                                    :class="swatch[s.color]"
                                />
                                {{ s.name }}
                                <Badge v-if="!s.is_active" variant="outline"
                                    >Inactivo</Badge
                                >
                            </p>
                            <p
                                v-if="s.description"
                                class="text-xs text-muted-foreground"
                            >
                                {{ s.description }}
                            </p>
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatCurrency(s.price) }}
                        </td>
                        <td class="hidden px-4 py-2.5 sm:table-cell">
                            {{
                                s.is_appointable
                                    ? `Sí · ${s.duration_minutes} min`
                                    : 'No'
                            }}
                        </td>
                        <td
                            class="hidden px-4 py-2.5 text-right text-muted-foreground tabular-nums md:table-cell"
                        >
                            {{ s.appointments_count }}
                        </td>
                        <td class="px-2">
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                aria-label="Editar"
                                @click="openForm(s)"
                                ><Pencil
                            /></Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-muted-foreground">
            Los servicios "en agenda" aparecen como tipo de consulta al agendar
            y definen la duración. Todos aparecen al registrar un cobro. Para
            dejar de usar un servicio, desactívalo: las citas y cobros
            anteriores lo conservan.
        </p>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-lg">
            <form class="space-y-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Editar servicio' : 'Nuevo servicio'
                    }}</DialogTitle>
                    <DialogDescription
                        >Consultas, procedimientos, certificados o
                        insumos.</DialogDescription
                    >
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-[1fr_140px]">
                    <div class="grid gap-1.5">
                        <Label for="svc_name">Nombre *</Label>
                        <Input
                            id="svc_name"
                            v-model="form.name"
                            maxlength="150"
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="svc_price">Precio *</Label>
                        <Input
                            id="svc_price"
                            v-model="form.price"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError :message="form.errors.price" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="svc_desc">Descripción</Label>
                        <Input
                            id="svc_desc"
                            v-model="form.description"
                            maxlength="500"
                            placeholder="Opcional"
                        />
                    </div>
                </div>

                <div class="space-y-3 rounded-lg border p-3">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input
                            v-model="form.is_appointable"
                            type="checkbox"
                            class="size-4"
                        />
                        Se puede agendar como cita
                    </label>
                    <div
                        v-if="form.is_appointable"
                        class="grid gap-3 sm:grid-cols-2"
                    >
                        <div class="grid gap-1.5">
                            <Label for="svc_duration">Duración (min) *</Label>
                            <input
                                id="svc_duration"
                                v-model.number="form.duration_minutes"
                                type="number"
                                min="5"
                                max="480"
                                step="5"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                            />
                            <InputError
                                :message="form.errors.duration_minutes"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <span class="text-sm font-medium"
                                >Color en la agenda</span
                            >
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="c in colors"
                                    :key="c"
                                    type="button"
                                    class="size-6 rounded-full ring-offset-2 ring-offset-background"
                                    :class="[
                                        swatch[c],
                                        form.color === c
                                            ? 'ring-2 ring-foreground'
                                            : '',
                                    ]"
                                    :aria-label="c"
                                    @click="form.color = c"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4"
                    />
                    Activo</label
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
</template>
