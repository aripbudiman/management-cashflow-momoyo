const rupiahFmt = new Intl.NumberFormat('id-ID');

export const rupiah = (n: number | string | null | undefined): string => `Rp ${rupiahFmt.format(Number(n ?? 0))}`;

export const fmtDate = (d: string): string => d.slice(0, 10).split('-').reverse().join('/');
