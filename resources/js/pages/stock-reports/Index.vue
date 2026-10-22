<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ClipboardCheck, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { fmtDate } from '@/lib/format';
import { factorOf, formatMixed } from '@/lib/units';
import type { UnitOption } from '@/lib/units';

interface ItemRow {
    id: number;
    name: string;
    unit: string;
    unit_options: UnitOption[];
    default_in_unit_id: number | null;
    default_out_unit_id: number | null;
    stock_qty: string;
}

type Column = 'in' | 'out' | 'waste' | 'physical';
type Inputs = Partial<Record<Column, { qty: number; unit_id: number | null }>>;

interface ReportItem {
    item_id: number;
    opening_qty: string;
    in_qty: string;
    out_qty: string;
    waste_qty: string;
    physical_qty: string | null;
    inputs: Inputs | null;
    note: string | null;
}

const props = defineProps<{
    branch: { id: number; branch_name: string } | null;
    date: string;
    today: string;
    items: ItemRow[];
    report: { status: string; revision_notes: string | null; notes: string | null; items: ReportItem[] } | null;
    history: Array<{ id: number; report_date: string; status: string }>;
}>();

const statusMap: Record<string, { label: string; cls: string }> = {
    submitted: { label: 'Menunggu Verifikasi', cls: 'bg-amber-100 text-amber-700' },
    verified: { label: 'Terverifikasi', cls: 'bg-emerald-100 text-emerald-700' },
    revision_needed: { label: 'Perlu Revisi', cls: 'bg-red-100 text-red-700' },
};

const locked = computed(() => props.report?.status === 'verified');

// Kolom masuk memakai satuan default masuk; keluar/rusak/fisik memakai satuan default keluar.
// Laporan yang sudah tersimpan menampilkan input asli staff (misal "1 karton"), bukan angka satuan terkecil.
const buildLines = () => {
    const saved = new Map((props.report?.items ?? []).map((r) => [r.item_id, r]));
    return props.items.map((i) => {
        const r = saved.get(i.id);
        const line: Record<string, number | string> = { item_id: i.id, note: r?.note ?? '' };
        (['in', 'out', 'waste', 'physical'] as Column[]).forEach((c) => {
            const input = r?.inputs?.[c];
            line[`${c}_qty`] = input ? input.qty : '';
            line[`${c}_unit_id`] = input?.unit_id ?? (c === 'in' ? i.default_in_unit_id : i.default_out_unit_id) ?? '';
        });
        return line;
    });
};

const form = useForm({
    report_date: props.date,
    notes: props.report?.notes ?? '',
    items: buildLines() as Array<Record<string, number | string>>,
});

watch(() => [props.date, props.report, props.items], () => {
    form.report_date = props.date;
    form.notes = props.report?.notes ?? '';
    form.items = buildLines();
});

// Stok awal = stok sistem sebelum laporan ini; kalau sudah pernah disimpan pakai snapshot-nya.
const opening = (idx: number): number => {
    const saved = props.report?.items.find((r) => r.item_id === props.items[idx].id);
    return saved ? Number(saved.opening_qty) : Number(props.items[idx].stock_qty);
};

const toBase = (idx: number, c: Column): number => {
    const l = form.items[idx];
    return Number(l[`${c}_qty`] || 0) * factorOf(props.items[idx].unit_options, l[`${c}_unit_id`] as number | '');
};

const expected = (idx: number): number => opening(idx) + toBase(idx, 'in') - toBase(idx, 'out') - toBase(idx, 'waste');

const diff = (idx: number): number | null => {
    const p = form.items[idx].physical_qty;
    return p === '' || p === null ? null : toBase(idx, 'physical') - expected(idx);
};

const mixed = (idx: number, base: number): string => formatMixed(Math.round(base * 100) / 100, props.items[idx].unit_options);

const changeDate = (e: Event) => router.get('/stock-reports', { date: (e.target as HTMLInputElement).value }, { preserveState: false });

const submit = () => form.post('/stock-reports', { preserveScroll: true });

const unitCls = 'h-11 rounded-lg border border-gray-200 bg-white px-2 text-sm dark:border-zinc-700 dark:bg-zinc-900';
const numCls = 'h-11 w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 text-right text-base dark:border-zinc-700 dark:bg-zinc-900';

// Satu halaman, tiga tab. Keluar memuat dua alasan: terpakai & rusak/expired.
const tabs: Array<{ key: string; label: string; cols: Column[] }> = [
    { key: 'in', label: 'Masuk', cols: ['in'] },
    { key: 'out', label: 'Keluar', cols: ['out', 'waste'] },
    { key: 'opname', label: 'Opname', cols: ['physical'] },
];
const colLabel: Record<Column, string> = { in: 'Masuk', out: 'Terpakai', waste: 'Rusak / Expired', physical: 'Hitung fisik' };

const tab = ref('in');
const search = ref('');
const activeCols = computed(() => tabs.find((t) => t.key === tab.value)?.cols ?? []);

const visible = computed(() =>
    props.items
        .map((item, idx) => ({ item, idx }))
        .filter(({ item }) => item.name.toLowerCase().includes(search.value.trim().toLowerCase())),
);

const filled = (idx: number, cols: Column[]): boolean => cols.some((c) => form.items[idx][`${c}_qty`] !== '' && form.items[idx][`${c}_qty`] !== null);
const filledCount = (cols: Column[]): number => props.items.filter((_, idx) => filled(idx, cols)).length;
</script>

<template>
    <Head title="Stok Harian - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Stok Harian</h1>
                <p class="text-sm text-gray-500">{{ branch?.branch_name ?? 'Akun belum ditempatkan di cabang' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <input
                    type="date"
                    :value="date"
                    :max="today"
                    class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                    @change="changeDate"
                />
                <Badge v-if="report" :class="statusMap[report.status]?.cls">{{ statusMap[report.status]?.label }}</Badge>
            </div>
        </div>

        <div v-if="report?.status === 'revision_needed'" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <b>Perlu revisi:</b> {{ report.revision_notes }}
        </div>
        <div v-if="locked" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
            Laporan ini sudah diverifikasi dan tidak bisa diubah.
        </div>

        <Card v-if="branch">
            <CardHeader class="border-b pb-3">
                <CardTitle class="flex items-center gap-2 text-sm font-bold">
                    <ClipboardCheck class="size-4 text-rose-500" /> Stok {{ fmtDate(date) }}
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-3 gap-1 border-b bg-gray-50 p-2 dark:bg-zinc-900">
                        <button
                            v-for="t in tabs"
                            :key="t.key"
                            type="button"
                            class="rounded-lg px-2 py-2.5 text-sm font-semibold transition"
                            :class="tab === t.key ? 'bg-white text-rose-600 shadow-sm dark:bg-zinc-800' : 'text-gray-500'"
                            @click="tab = t.key"
                        >
                            {{ t.label }}
                            <span v-if="filledCount(t.cols)" class="ml-1 rounded-full bg-rose-100 px-1.5 text-xs text-rose-600">{{ filledCount(t.cols) }}</span>
                        </button>
                    </div>

                    <div class="relative border-b p-3">
                        <Search class="absolute top-1/2 left-6 size-4 -translate-y-1/2 text-gray-400" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Cari barang..."
                            class="h-11 w-full rounded-lg border border-gray-200 bg-white pr-3 pl-9 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                        />
                    </div>

                    <div class="grid gap-3 p-3 md:grid-cols-2">
                        <div
                            v-for="{ item, idx } in visible"
                            :key="item.id"
                            class="space-y-3 rounded-xl border bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950"
                            :class="filled(idx, activeCols) ? 'border-rose-300' : ''"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-bold text-gray-900 dark:text-white">{{ item.name }}</div>
                                <div class="shrink-0 text-right text-xs text-gray-500">
                                    Stok awal<br /><span class="font-semibold text-gray-700 dark:text-gray-300">{{ mixed(idx, opening(idx)) }}</span>
                                </div>
                            </div>

                            <div v-for="c in activeCols" :key="c" class="space-y-1">
                                <label v-if="activeCols.length > 1 || tab === 'opname'" class="text-xs font-medium text-gray-500">{{ colLabel[c] }}</label>
                                <div class="flex gap-2">
                                    <input
                                        v-model="form.items[idx][`${c}_qty`]"
                                        type="number"
                                        inputmode="decimal"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                        :disabled="locked"
                                        :class="numCls"
                                    />
                                    <select v-model="form.items[idx][`${c}_unit_id`]" :disabled="locked" :class="unitCls" class="w-28 shrink-0">
                                        <option v-for="u in [...item.unit_options].reverse()" :key="u.unit_id" :value="u.unit_id">{{ u.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="tab === 'opname'" class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-zinc-900">
                                <span class="text-gray-500">Seharusnya <b class="text-gray-700 dark:text-gray-300">{{ mixed(idx, expected(idx)) }}</b></span>
                                <span
                                    class="font-bold"
                                    :class="diff(idx) === null ? 'text-gray-300' : diff(idx) === 0 ? 'text-emerald-600' : 'text-red-600'"
                                >
                                    Selisih {{ diff(idx) === null ? '-' : mixed(idx, diff(idx) ?? 0) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-if="visible.length === 0" class="p-8 text-center text-sm text-gray-400">
                        {{ items.length === 0 ? 'Belum ada barang. Hubungi Admin Pusat.' : 'Barang tidak ditemukan.' }}
                    </div>

                    <div class="space-y-3 border-t p-4 pb-6">
                        <InputError :message="form.errors.items || form.errors.report_date" />
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            :disabled="locked"
                            placeholder="Catatan (opsional)"
                            class="w-full rounded-lg border border-gray-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                        />
                        <p class="text-xs text-gray-500">
                            Isi bertahap di tiga tab, kosongkan yang tidak berubah. Masuk biasanya per karton, keluar per box.
                            Di tab Opname, selisih fisik otomatis jadi penyesuaian stok.
                        </p>
                        <Button type="submit" class="h-11 w-full" :disabled="form.processing || locked || items.length === 0">Kirim ke Pusat</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card v-if="history.length">
            <CardHeader class="border-b pb-3"><CardTitle class="text-sm font-bold">Riwayat Terakhir</CardTitle></CardHeader>
            <CardContent class="divide-y p-0">
                <button
                    v-for="h in history"
                    :key="h.id"
                    class="flex w-full items-center justify-between p-3 text-left text-sm hover:bg-gray-50 dark:hover:bg-zinc-900"
                    @click="router.get('/stock-reports', { date: h.report_date.slice(0, 10) })"
                >
                    <span>{{ fmtDate(h.report_date) }}</span>
                    <Badge :class="statusMap[h.status]?.cls">{{ statusMap[h.status]?.label }}</Badge>
                </button>
            </CardContent>
        </Card>
    </div>
</template>
