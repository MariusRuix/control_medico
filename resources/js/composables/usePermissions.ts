import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Permisos del usuario actual (compartidos por HandleInertiaRequests).
 * Solo sirve para mostrar u ocultar elementos: la autorización real
 * siempre se valida en el servidor.
 */
export function usePermissions() {
    const page = usePage();

    const permissions = computed<string[]>(
        () => page.props.auth.permissions ?? [],
    );
    const roles = computed<string[]>(() => page.props.auth.roles ?? []);

    const can = (permission: string): boolean =>
        permissions.value.includes(permission);

    const hasRole = (role: string): boolean => roles.value.includes(role);

    return { permissions, roles, can, hasRole };
}
