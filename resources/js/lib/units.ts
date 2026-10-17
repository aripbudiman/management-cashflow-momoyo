export interface UnitOption {
    unit_id: number;
    name: string;
    factor: number;
}

/** 'mixed' = "1 karton 5 box", 'base' = satuan terkecil, angka = id satuan tertentu */
export type QtyMode = 'mixed' | 'base' | number;

const fmt = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

/** Qty (satuan terkecil) dalam satu satuan tertentu, bisa desimal: 18 box = 0,9 karton. */
export const formatInUnit = (base: number, units: UnitOption[], unitId: number): string => {
    const unit = units.find((u) => u.unit_id === unitId) ?? units[0];
    return unit ? `${fmt.format(base / unit.factor)} ${unit.name}` : fmt.format(base);
};

/** Qty dipecah ke satuan terbesar ke terkecil, tanpa desimal: "1 karton 5 box". */
export const formatMixed = (base: number, units: UnitOption[]): string => {
    if (units.length === 0) return fmt.format(base);

    const sorted = [...units].sort((a, b) => b.factor - a.factor);
    const sign = base < 0 ? '-' : '';
    let rest = Math.round(Math.abs(base) * 100);
    const parts: string[] = [];

    for (const u of sorted) {
        const f = Math.round(u.factor * 100);
        const isBase = f === 100;
        const n = isBase ? rest / 100 : Math.floor(rest / f);
        rest = isBase ? 0 : rest - n * f;
        if (n > 0) parts.push(`${fmt.format(n)} ${u.name}`);
    }

    return parts.length ? sign + parts.join(' ') : `0 ${sorted[sorted.length - 1].name}`;
};

export const formatQty = (base: number, units: UnitOption[], mode: QtyMode, defaultUnitId?: number | null): string => {
    if (mode === 'mixed') return formatMixed(base, units);
    if (mode === 'base') return formatInUnit(base, units, units[0]?.unit_id);
    return formatInUnit(base, units, mode);
};

/** Pengali satuan -> satuan terkecil (1 jika satuan tidak dikenal/kosong). */
export const factorOf = (units: UnitOption[], unitId: number | '' | null | undefined): number =>
    units.find((u) => u.unit_id === unitId)?.factor ?? 1;
