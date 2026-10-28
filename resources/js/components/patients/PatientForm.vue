<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import type { RouteFormDefinition } from '@/wayfinder';
import NativeSelect from '@/components/form/NativeSelect.vue';
import Textarea from '@/components/form/Textarea.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Patient, SelectOption } from '@/types';

defineProps<{
    action: RouteFormDefinition<'post'>;
    cancelHref: string;
    submitLabel: string;
    patient?: Patient | null;
    sexes: SelectOption[];
    bloodTypes: SelectOption[];
}>();

const today = new Date().toISOString().slice(0, 10);
</script>

<template>
    <Form
        v-bind="action"
        :options="{ preserveScroll: true }"
        class="space-y-8"
        v-slot="{ errors, processing }"
    >
        <section class="space-y-4">
            <h2 class="text-base font-medium">Datos personales</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="first_name">Nombre(s) *</Label>
                    <Input
                        id="first_name"
                        name="first_name"
                        :default-value="patient?.first_name ?? ''"
                        :aria-invalid="!!errors.first_name"
                        required
                        autofocus
                        autocomplete="off"
                    />
                    <InputError :message="errors.first_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="last_name">Apellido paterno *</Label>
                    <Input
                        id="last_name"
                        name="last_name"
                        :default-value="patient?.last_name ?? ''"
                        :aria-invalid="!!errors.last_name"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="errors.last_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="second_last_name">Apellido materno</Label>
                    <Input
                        id="second_last_name"
                        name="second_last_name"
                        :default-value="patient?.second_last_name ?? ''"
                        :aria-invalid="!!errors.second_last_name"
                        autocomplete="off"
                    />
                    <InputError :message="errors.second_last_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="birth_date">Fecha de nacimiento</Label>
                    <Input
                        id="birth_date"
                        name="birth_date"
                        type="date"
                        :max="today"
                        :default-value="patient?.birth_date ?? ''"
                        :aria-invalid="!!errors.birth_date"
                    />
                    <InputError :message="errors.birth_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="sex">Sexo</Label>
                    <NativeSelect
                        name="sex"
                        :options="sexes"
                        :default-value="patient?.sex"
                        :invalid="!!errors.sex"
                    />
                    <InputError :message="errors.sex" />
                </div>
                <div class="grid gap-2">
                    <Label for="blood_type">Tipo de sangre</Label>
                    <NativeSelect
                        name="blood_type"
                        :options="bloodTypes"
                        :default-value="patient?.blood_type"
                        placeholder="Desconocido"
                        :invalid="!!errors.blood_type"
                    />
                    <InputError :message="errors.blood_type" />
                </div>

                <div class="grid gap-2">
                    <Label for="curp">CURP</Label>
                    <Input
                        id="curp"
                        name="curp"
                        maxlength="18"
                        class="uppercase"
                        :default-value="patient?.curp ?? ''"
                        :aria-invalid="!!errors.curp"
                        autocomplete="off"
                    />
                    <InputError :message="errors.curp" />
                </div>
                <div class="grid gap-2">
                    <Label for="occupation">Ocupación</Label>
                    <Input
                        id="occupation"
                        name="occupation"
                        :default-value="patient?.occupation ?? ''"
                        :aria-invalid="!!errors.occupation"
                    />
                    <InputError :message="errors.occupation" />
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-base font-medium">Contacto</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="phone">Teléfono</Label>
                    <Input
                        id="phone"
                        name="phone"
                        type="tel"
                        :default-value="patient?.phone ?? ''"
                        :aria-invalid="!!errors.phone"
                    />
                    <InputError :message="errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">Correo electrónico</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        :default-value="patient?.email ?? ''"
                        :aria-invalid="!!errors.email"
                    />
                    <InputError :message="errors.email" />
                </div>
                <div class="grid gap-2 md:col-span-3">
                    <Label for="address">Domicilio</Label>
                    <Input
                        id="address"
                        name="address"
                        :default-value="patient?.address ?? ''"
                        :aria-invalid="!!errors.address"
                    />
                    <InputError :message="errors.address" />
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-base font-medium">Contacto de emergencia</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="emergency_name">Nombre</Label>
                    <Input
                        id="emergency_name"
                        name="emergency_name"
                        :default-value="patient?.emergency_name ?? ''"
                        :aria-invalid="!!errors.emergency_name"
                    />
                    <InputError :message="errors.emergency_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="emergency_relationship">Parentesco</Label>
                    <Input
                        id="emergency_relationship"
                        name="emergency_relationship"
                        :default-value="patient?.emergency_relationship ?? ''"
                        :aria-invalid="!!errors.emergency_relationship"
                    />
                    <InputError :message="errors.emergency_relationship" />
                </div>
                <div class="grid gap-2">
                    <Label for="emergency_phone">Teléfono</Label>
                    <Input
                        id="emergency_phone"
                        name="emergency_phone"
                        type="tel"
                        :default-value="patient?.emergency_phone ?? ''"
                        :aria-invalid="!!errors.emergency_phone"
                    />
                    <InputError :message="errors.emergency_phone" />
                </div>
            </div>
        </section>

        <section class="space-y-2">
            <Label for="notes">Notas administrativas</Label>
            <Textarea
                name="notes"
                :default-value="patient?.notes"
                :invalid="!!errors.notes"
                placeholder="Preferencias de contacto, aseguradora, etc. (no información clínica)"
            />
            <InputError :message="errors.notes" />
        </section>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="processing">
                {{ submitLabel }}
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="cancelHref">Cancelar</Link>
            </Button>
        </div>
    </Form>
</template>
