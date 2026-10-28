const dateFormatter = new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

const dateTimeFormatter = new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

const currencyFormatter = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

/**
 * Fechas "YYYY-MM-DD" se interpretan en hora local para no recorrer el día.
 */
function parse(value: string): Date {
    return /^\d{4}-\d{2}-\d{2}$/.test(value)
        ? new Date(`${value}T00:00:00`)
        : new Date(value);
}

export function formatDate(value: string | null | undefined): string {
    return value ? dateFormatter.format(parse(value)) : '—';
}

export function formatDateTime(value: string | null | undefined): string {
    return value ? dateTimeFormatter.format(parse(value)) : '—';
}

export function formatCurrency(
    value: string | number | null | undefined,
): string {
    return currencyFormatter.format(Number(value ?? 0));
}
