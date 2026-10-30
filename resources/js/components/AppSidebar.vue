<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, CalendarDays, LayoutGrid, Users, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { index as appointmentsIndex } from '@/routes/appointments';
import { index as cashIndex } from '@/routes/cash';
import { index as catalogsIndex } from '@/routes/catalogs/vademecum';
import { index as patientsIndex } from '@/routes/patients';
import type { NavItem } from '@/types';

const { can } = usePermissions();

const navigation: (NavItem & { permission?: string })[] = [
    { title: 'Inicio', href: dashboard(), icon: LayoutGrid },
    {
        title: 'Agenda',
        href: appointmentsIndex(),
        icon: CalendarDays,
        permission: 'appointments.view',
    },
    {
        title: 'Pacientes',
        href: patientsIndex(),
        icon: Users,
        permission: 'patients.view',
    },
    {
        title: 'Caja',
        href: cashIndex(),
        icon: Wallet,
        permission: 'charges.view',
    },
    {
        title: 'Catálogos',
        href: catalogsIndex(),
        icon: BookOpen,
        permission: 'catalogs.manage',
    },
];

const mainNavItems = computed<NavItem[]>(() =>
    navigation.filter((item) => !item.permission || can(item.permission)),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
