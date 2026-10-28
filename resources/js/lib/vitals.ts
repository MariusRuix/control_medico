/** IMC con dos decimales, o null si faltan datos. */
export function calculateBmi(
    weightKg: string | number | null | undefined,
    heightCm: string | number | null | undefined,
): string | null {
    const weight = Number(weightKg);
    const height = Number(heightCm) / 100;

    if (!weight || !height || weight <= 0 || height <= 0) {
        return null;
    }

    return (weight / height ** 2).toFixed(2);
}

/** Clasificación de la OMS para adultos. */
export function bmiCategory(bmi: string | null): string | null {
    if (bmi === null) {
        return null;
    }

    const value = Number(bmi);

    if (value < 18.5) return 'Bajo peso';
    if (value < 25) return 'Normal';
    if (value < 30) return 'Sobrepeso';
    if (value < 35) return 'Obesidad I';
    if (value < 40) return 'Obesidad II';

    return 'Obesidad III';
}
