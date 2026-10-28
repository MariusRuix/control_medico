import type { DayKey } from '@/types';

/** Clases de Tailwind por color de estatus (definido en AppointmentStatus::color()). */
export const statusBadgeClasses: Record<string, string> = {
    sky: 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
    indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    emerald:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    rose: 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300',
    zinc: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-500/15 dark:text-zinc-300',
};

/** Bloques del calendario: fondo suave + borde izquierdo del color del estatus. */
export const statusEventClasses: Record<string, string> = {
    sky: 'border-sky-500 bg-sky-50 text-sky-900 dark:bg-sky-500/15 dark:text-sky-100',
    indigo: 'border-indigo-500 bg-indigo-50 text-indigo-900 dark:bg-indigo-500/15 dark:text-indigo-100',
    amber: 'border-amber-500 bg-amber-50 text-amber-900 dark:bg-amber-500/15 dark:text-amber-100',
    emerald:
        'border-emerald-500 bg-emerald-50 text-emerald-900 dark:bg-emerald-500/15 dark:text-emerald-100',
    rose: 'border-rose-400 bg-rose-50/70 text-rose-900/70 line-through dark:bg-rose-500/10 dark:text-rose-200/70',
    zinc: 'border-zinc-400 bg-zinc-100 text-zinc-600 line-through dark:bg-zinc-500/15 dark:text-zinc-300',
};

const DAY_KEYS: DayKey[] = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

/** "YYYY-MM-DD" o "YYYY-MM-DDTHH:mm:ss" → Date en hora local. */
export function parseLocal(value: string): Date {
    return value.length === 10
        ? new Date(`${value}T00:00:00`)
        : new Date(value);
}

export function toDateKey(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

export function addDays(dateKey: string, days: number): string {
    const date = parseLocal(dateKey);
    date.setDate(date.getDate() + days);

    return toDateKey(date);
}

export function addMonths(dateKey: string, months: number): string {
    const date = parseLocal(dateKey);
    date.setDate(1);
    date.setMonth(date.getMonth() + months);

    return toDateKey(date);
}

/** Lista de fechas [from, to) en formato YYYY-MM-DD. */
export function daysBetween(from: string, to: string): string[] {
    const days: string[] = [];

    for (let day = from; day < to; day = addDays(day, 1)) {
        days.push(day);
    }

    return days;
}

export function dayKey(dateKey: string): DayKey {
    return DAY_KEYS[parseLocal(dateKey).getDay()];
}

/** "HH:mm" → minutos desde medianoche. */
export function timeToMinutes(time: string): number {
    const [h, m] = time.split(':').map(Number);

    return h * 60 + m;
}

export function minutesToTime(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;

    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

/** Minutos desde la medianoche del día indicado (puede ser > 1440 si termina al día siguiente). */
export function minutesFromDayStart(
    dateTime: string,
    dateKeyValue: string,
): number {
    return Math.round(
        (parseLocal(dateTime).getTime() - parseLocal(dateKeyValue).getTime()) /
            60000,
    );
}

export function timeOf(dateTime: string): string {
    return dateTime.slice(11, 16);
}

const fmt = (options: Intl.DateTimeFormatOptions) =>
    new Intl.DateTimeFormat('es-MX', options);

export const formatters = {
    weekday: fmt({ weekday: 'short' }),
    dayMonth: fmt({ day: 'numeric', month: 'short' }),
    long: fmt({
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
    monthYear: fmt({ month: 'long', year: 'numeric' }),
};

export function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}
