<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import {
    Check,
    Copy,
    EllipsisVertical,
    KeyRound,
    Pencil,
    Plus,
    Power,
    ShieldCheck,
} from '@lucide/vue';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatDateTime } from '@/lib/format';
import { index } from '@/routes/settings/users';

type StaffUser = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    role_label: string;
    is_active: boolean;
    two_factor: boolean;
    passkeys_count: number;
    last_activity_at: string | null;
    created_at: string | null;
    is_self: boolean;
};

type Credentials = { name: string; email: string; password: string };

defineProps<{
    users: StaffUser[];
    roles: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios', href: index() }],
    },
});

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 dark:bg-input/30';

// Contraseña temporal: llega como dato flash y solo se muestra una vez.
const credentials = ref<Credentials | null>(null);
const { copy, copied } = useClipboard({ legacy: true });

function captureCredentials(flash: Record<string, unknown>): void {
    const data = flash.credentials as Credentials | undefined;

    if (data) {
        credentials.value = data;
    }
}

// Alta y edición
const formOpen = ref(false);
const editing = ref<StaffUser | null>(null);
const form = useForm({
    name: '',
    email: '',
    role: 'recepcion',
    password: '',
});

function openForm(user: StaffUser | null): void {
    editing.value = user;
    form.clearErrors();
    form.defaults({
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? 'recepcion',
        password: '',
    });
    form.reset();
    formOpen.value = true;
}

function submitForm(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (formOpen.value = false),
        onFlash: captureCredentials,
    };

    if (editing.value) {
        form.transform(({ name, email, role }) => ({ name, email, role })).put(
            UserController.update.url(editing.value.id),
            options,
        );
    } else {
        form.transform((data) => data).post(
            UserController.store.url(),
            options,
        );
    }
}

// Restablecer contraseña
const resetOpen = ref(false);
const resetting = ref<StaffUser | null>(null);
const resetForm = useForm({ password: '', reset_second_factor: false });

function openReset(user: StaffUser): void {
    resetting.value = user;
    resetForm.reset();
    resetForm.clearErrors();
    resetOpen.value = true;
}

function submitReset(): void {
    if (!resetting.value) {
        return;
    }

    resetForm.put(UserController.password.url(resetting.value.id), {
        preserveScroll: true,
        onSuccess: () => (resetOpen.value = false),
        onFlash: captureCredentials,
    });
}

// Activar / desactivar
const statusOpen = ref(false);
const target = ref<StaffUser | null>(null);
const statusForm = useForm({ active: false });

function openStatus(user: StaffUser): void {
    target.value = user;
    statusForm.clearErrors();
    statusForm.active = !user.is_active;
    statusOpen.value = true;
}

function submitStatus(): void {
    if (!target.value) {
        return;
    }

    statusForm.patch(UserController.status.url(target.value.id), {
        preserveScroll: true,
        onSuccess: () => (statusOpen.value = false),
    });
}
</script>

<template>
    <Head title="Usuarios" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Usuarios"
                description="Cuentas del personal que entra al sistema"
            />
            <Button size="sm" @click="openForm(null)"
                ><Plus /> Nuevo usuario</Button
            >
        </div>

        <ul class="divide-y rounded-xl border">
            <li
                v-for="u in users"
                :key="u.id"
                class="flex items-start gap-3 p-4"
                :class="u.is_active ? '' : 'bg-muted/40'"
            >
                <div class="min-w-0 flex-1 space-y-1">
                    <p class="flex flex-wrap items-center gap-2 font-medium">
                        <span
                            :class="u.is_active ? '' : 'text-muted-foreground'"
                            >{{ u.name }}</span
                        >
                        <Badge variant="secondary">{{ u.role_label }}</Badge>
                        <Badge v-if="u.is_self" variant="outline">Tú</Badge>
                        <Badge v-if="!u.is_active" variant="destructive"
                            >Desactivado</Badge
                        >
                    </p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ u.email }}
                    </p>
                    <p
                        class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span
                            v-if="u.two_factor || u.passkeys_count > 0"
                            class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400"
                        >
                            <ShieldCheck class="size-3.5" />
                            {{
                                [
                                    u.two_factor ? 'Dos pasos' : null,
                                    u.passkeys_count > 0
                                        ? `${u.passkeys_count} llave(s) de acceso`
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </span>
                        <span v-else>Solo contraseña</span>
                        <span v-if="u.last_activity_at"
                            >Última actividad:
                            {{ formatDateTime(u.last_activity_at) }}</span
                        >
                        <span v-else-if="u.created_at"
                            >Alta: {{ formatDate(u.created_at) }}</span
                        >
                    </p>
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            :aria-label="`Acciones para ${u.name}`"
                            ><EllipsisVertical
                        /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem @select="openForm(u)"
                            ><Pencil /> Editar</DropdownMenuItem
                        >
                        <template v-if="!u.is_self">
                            <DropdownMenuItem @select="openReset(u)"
                                ><KeyRound /> Restablecer
                                contraseña</DropdownMenuItem
                            >
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                :variant="
                                    u.is_active ? 'destructive' : 'default'
                                "
                                @select="openStatus(u)"
                                ><Power />
                                {{
                                    u.is_active ? 'Desactivar' : 'Reactivar'
                                }}</DropdownMenuItem
                            >
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </li>
        </ul>

        <p class="text-xs text-muted-foreground">
            Las cuentas no se borran: al desactivar una, se cierran sus sesiones
            y ya no puede entrar, pero su nombre se conserva en las citas,
            cobros y la bitácora. Para cambiar tu propia contraseña usa
            <strong>Seguridad</strong>.
        </p>
    </div>

    <!-- Alta / edición -->
    <Dialog v-model:open="formOpen">
        <DialogContent class="sm:max-w-md">
            <form class="space-y-4" @submit.prevent="submitForm">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Editar usuario' : 'Nuevo usuario'
                    }}</DialogTitle>
                    <DialogDescription>
                        El rol define qué puede ver: recepción no tiene acceso a
                        notas clínicas, diagnósticos ni recetas.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="user_name">Nombre *</Label>
                    <Input
                        id="user_name"
                        v-model="form.name"
                        maxlength="255"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="user_email">Correo *</Label>
                    <Input
                        id="user_email"
                        v-model="form.email"
                        type="email"
                        maxlength="255"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="user_role">Rol *</Label>
                    <select
                        id="user_role"
                        v-model="form.role"
                        :class="selectClass"
                        :disabled="editing?.is_self"
                    >
                        <option
                            v-for="r in roles"
                            :key="r.value"
                            :value="r.value"
                        >
                            {{ r.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.role" />
                </div>
                <div v-if="!editing" class="grid gap-1.5">
                    <Label for="user_password">Contraseña</Label>
                    <Input
                        id="user_password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Vacía = generar una temporal"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="formOpen = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Guardar</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Restablecer contraseña -->
    <Dialog v-model:open="resetOpen">
        <DialogContent class="sm:max-w-md">
            <form class="space-y-4" @submit.prevent="submitReset">
                <DialogHeader>
                    <DialogTitle>Restablecer contraseña</DialogTitle>
                    <DialogDescription>
                        {{ resetting?.name }} tendrá que entrar con la nueva
                        contraseña; se cierran sus sesiones abiertas.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="reset_password">Nueva contraseña</Label>
                    <Input
                        id="reset_password"
                        v-model="resetForm.password"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Vacía = generar una temporal"
                    />
                    <InputError :message="resetForm.errors.password" />
                </div>

                <label
                    class="flex items-start gap-3 rounded-lg border p-3 text-sm"
                >
                    <input
                        v-model="resetForm.reset_second_factor"
                        type="checkbox"
                        class="mt-0.5 size-4"
                    />
                    <span>
                        <span class="block font-medium"
                            >Quitar verificación en dos pasos y llaves de
                            acceso</span
                        >
                        <span class="block text-xs text-muted-foreground"
                            >Úsalo si perdió o cambió su teléfono. Podrá
                            configurarlas de nuevo en Seguridad.</span
                        >
                    </span>
                </label>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="resetOpen = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="resetForm.processing"
                        >Restablecer</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Activar / desactivar -->
    <Dialog v-model:open="statusOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ statusForm.active ? 'Reactivar' : 'Desactivar' }}
                    {{ target?.name }}
                </DialogTitle>
                <DialogDescription v-if="statusForm.active">
                    Podrá volver a entrar con su contraseña actual.
                </DialogDescription>
                <DialogDescription v-else>
                    Se cerrarán sus sesiones abiertas y ya no podrá entrar. Su
                    historial se conserva y puedes reactivarla cuando quieras.
                </DialogDescription>
            </DialogHeader>
            <InputError :message="statusForm.errors.active" />
            <DialogFooter class="gap-2">
                <Button variant="secondary" @click="statusOpen = false"
                    >Cancelar</Button
                >
                <Button
                    :variant="statusForm.active ? 'default' : 'destructive'"
                    :disabled="statusForm.processing"
                    @click="submitStatus"
                    >{{
                        statusForm.active ? 'Reactivar' : 'Desactivar'
                    }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Contraseña temporal (se muestra una sola vez) -->
    <Dialog
        :open="credentials !== null"
        @update:open="(open) => !open && (credentials = null)"
    >
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Contraseña temporal</DialogTitle>
                <DialogDescription>
                    Entrégala a {{ credentials?.name }}. Solo se muestra esta
                    vez; pídele que la cambie en Configuración → Seguridad al
                    entrar.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-2 rounded-lg border bg-muted/40 p-3 text-sm">
                <p>
                    <span class="text-muted-foreground">Correo:</span>
                    {{ credentials?.email }}
                </p>
                <div class="flex items-center gap-2">
                    <code
                        class="flex-1 rounded bg-background px-2 py-1.5 font-mono text-base tracking-wide select-all"
                        >{{ credentials?.password }}</code
                    >
                    <Button
                        size="icon-sm"
                        variant="outline"
                        aria-label="Copiar contraseña"
                        @click="copy(credentials?.password ?? '')"
                    >
                        <Check v-if="copied" />
                        <Copy v-else />
                    </Button>
                </div>
            </div>
            <DialogFooter>
                <Button @click="credentials = null">Listo</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
