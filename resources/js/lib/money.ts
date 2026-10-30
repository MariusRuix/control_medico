/** Operaciones en centavos para evitar errores de punto flotante en la vista. */
export function toCents(value: string | number | null | undefined): number {
    const n = Number(value ?? 0);

    return Number.isFinite(n) ? Math.round(n * 100) : 0;
}

export function fromCents(cents: number): string {
    return (cents / 100).toFixed(2);
}
