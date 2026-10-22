<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ClipboardCheck, Search, Undo2 } from '@lucide/vue';
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
    moved: boolean;
    opening_qty: number | null;
    in_qty: number;
    out_qty: number;
    waste_qty: number;
    transfer_qty: number;
    expected_qty: number;
}

interface Transaction {
    id: number;
    type: 'in' | 'out';
    status: 'active' | 'cancelled';
    note: string | null;
    created_at: string;
    user: string | null;
    cancelled_by: string | null;
    items: Array<{ id: number; type: string; name: string; qty: number; unit: string | null }>;
}

interface ReportItem {
    item_id: number;
    physical_qty: string | null;
    inputs: { physical?: { qty: number; unit_id: number | null } } | null;
    note: string | null;
}

const props = defineProps<{
    branch: { id: number; branch_name: string } | null;
    date: string;
    today: string;
    open: boolean;
    isAdmin: boolean;
    branches: Array<{ id: number; branch_name: string }>;
    items: ItemRow[];
    transactions: Transaction[];
    report: { status: string; revision_notes: string | null; notes: string | null; items: ReportItem[] } | null;
    history: Array<{ id: number; report_date: string; status: string }>;
}>();

const statusMap: Record<string, { label: string; cls: string }> = {
    submitted: { label: 'Menunggu Verifikasi', cls: 'bg-amber-100 text-amber-700' },
    verified: { label: 'Terverifikasi', cls: 'bg-emerald-100 text-emerald-700' },
    revision_needed: { label: 'Perlu Revisi', cls: 'bg-red-100 text-red-700' },
};

type Line = { qty: number | ''; unit_id: number | '' };
type OutLine = { out: Line; waste: Line };

const tab = ref<'in' | 'out' | 'opname'>('in');
const search = ref('');
const inNote = ref('');
const outNote = ref('');

const inLines = ref<Line[]>([]);
const outLines = ref<OutLine[]>([]);
const physLines = ref<Line[]>([]);

const buildLines = () => {
    const saved = new Map((props.report?.items ?? []).map((r) => [r.item_id, r]));
    inLines.value = props.items.map((i) => ({ qty: '', unit_id: i.default_in_unit_id ?? '' }));
    outLines.value = props.items.map((i) => ({
        out: { qty: '', unit_id: i.default_out_unit_id ?? '' },
        waste: { qty: '', unit_id: i.default_out_unit_id ?? '' },
    }));
    physLines.value = props.items.map((i) => {
        const p = saved.get(i.id)?.inputs?.physical;
        return { qty: p ? p.qty : '', unit_id: p?.unit_id ?? i.default_out_unit_id ?? '' };
    });
};
buildLines();
watch(() => [props.items, props.report], buildLines);

const txForm = useForm({
    branch_id: props.branch?.id ?? null,
    moved_at: props.date,
    type: 'in' as 'in' | 'out',
    note: '',
    items: [] as Array<{ item_id: number; kind: string; qty: number; unit_id: number | null }>,
});

const opnameForm = useForm({
    report_date: props.date,
    notes: props.report?.notes ?? '',
    items: [] as Array<{ item_id: number; physical_qty: number; physical_unit_id: number | null }>,
});
watch(() => props.date, () => {
    opnameForm.report_date = props.date;
    opnameForm.notes = props.report?.notes ?? '';
});

const line = (item: ItemRow, kind: string, l: Line) =>
    l.qty !== '' && Number(l.qty) > 0 ? [{ item_id: item.id, kind, qty: Number(l.qty), unit_id: l.unit_id === '' ? null : Number(l.unit_id) }] : [];

const submitTx = (type: 'in' | 'out') => {
    txForm.branch_id = props.branch?.id ?? null;
    txForm.moved_at = props.date;
    txForm.type = type;
    txForm.note = type === 'in' ? inNote.value : outNote.value;
    txForm.items =
        type === 'in'
            ? props.items.flatMap((item, idx) => line(item, 'in', inLines.value[idx]))
            : props.items.flatMap((item, idx) => [...line(item, 'out', outLines.value[idx].out), ...line(item, 'waste', outLines.value[idx].waste)]);

    txForm.post('/stock-transactions', {
        preserveScroll: true,
        onSuccess: () => {
            buildLines();
            if (type === 'in') inNote.value = '';
            else outNote.value = '';
        },
    });
};

const submitOpname = () => {
    opnameForm.report_date = props.date;
    opnameForm.items = props.items.flatMap((item, idx) => {
        const l = physLines.value[idx];
        return l.qty === '' ? [] : [{ item_id: item.id, physical_qty: Number(l.qty), physical_unit_id: l.unit_id === '' ? null : Number(l.unit_id) }];
    });
    opnameForm.post('/stock-reports', { preserveScroll: true });
};

const cancelTx = (t: Transaction) => {
    if (confirm('Batalkan transaksi ini? Stok akan dikembalikan.')) {
        router.post(`/stock-transactions/${t.id}/cancel`, {}, { preserveScroll: true });
    }
};

const changeDate = (e: Event) =>
    router.get('/stock-reports', { date: (e.target as HTMLInputElement).value, branch_id: props.branch?.id }, { preserveState: false });

// Admin pusat memilih cabang dulu; tanggal yang sedang dipilih ikut dibawa.
const changeBranch = (e: Event) =>
    router.get('/stock-reports', { branch_id: (e.target as HTMLSelectElement).value || undefined, date: props.date }, { preserveState: false });

const mixed = (item: ItemRow, base: number | string): string => formatMixed(Math.round(Number(base) * 100) / 100, item.unit_options);
const openingOf = (i: ItemRow): number => i.opening_qty ?? i.expected_qty - i.in_qty + i.out_qty + i.waste_qty - i.transfer_qty;
const physBase = (idx: number): number | null => {
    const l = physLines.value[idx];
    return l.qty === '' ? null : Number(l.qty) * factorOf(props.items[idx].unit_options, l.unit_id);
};
const diff = (idx: number): number | null => {
    const p = physBase(idx);
    return p === null ? null : p - props.items[idx].expected_qty;
};

const matches = (item: ItemRow) => item.name.toLowerCase().includes(search.value.trim().toLowerCase());
const byName = computed(() =>
    props.items
        .map((item, idx) => ({ item, idx }))
        .filter(({ item }) => matches(item))
        .sort((a, b) => a.item.name.localeCompare(b.item.name)),
);
const recapList = computed(() => props.items.map((item, idx) => ({ item, idx })).filter(({ item }) => matches(item)));

const movedCount = computed(() => props.items.filter((i) => i.moved).length);
const countIn = computed(() => inLines.value.filter((l) => l.qty !== '' && Number(l.qty) > 0).length);
const countOut = computed(() => outLines.value.filter((l) => (l.out.qty !== '' && Number(l.out.qty) > 0) || (l.waste.qty !== '' && Number(l.waste.qty) > 0)).length);
const txForTab = computed(() => props.transactions.filter((t) => t.type === tab.value));
const timeOf = (iso: string): string => new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
const kindLabel: Record<string, string> = { in: '', out: ' (terpakai)', waste: ' (rusak/expired)' };

const unitCls = 'h-11 rounded-lg border border-gray-200 bg-white px-2 text-sm dark:border-zinc-700 dark:bg-zinc-900';
const numCls = 'h-11 w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 text-right text-base dark:border-zinc-700 dark:bg-zinc-900';
const tabs = computed(() => [
    { key: 'in' as const, label: 'Masuk', count: countIn.value },
    { key: 'out' as const, label: 'Keluar', count: countOut.value },
    { key: 'opname' as const, label: 'Opname', count: movedCount.value },
]);
</script>

<template>
    <Head title="Stok Harian - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Stok Harian</h1>
                <p v-if="!isAdmin" class="text-sm text-gray-500">{{ branch?.branch_name ?? 'Akun belum ditempatkan di cabang' }}</p>
                <p v-else class="text-sm text-gray-500">Pilih cabang, lalu catat barang masuk dan keluar atas nama pusat.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-if="isAdmin"
                    :value="branch?.id ?? ''"
                    class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm font-medium dark:border-zinc-700 dark:bg-zinc-900"
                    @change="changeBranch"
                >
                    <option value="">Pilih cabang...</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.branch_name }}</option>
                </select>
                <input
                    v-if="branch"
                    type="date"
                    :value="date"
                    :max="today"
                    class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                    @change="changeDate"
                />
                <Badge v-if="report" :class="statusMap[report.status]?.cls">Opname: {{ statusMap[report.status]?.label }}</Badge>
            </div>
        </div>

        <div v-if="report?.status === 'revision_needed'" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <b>Opname perlu revisi:</b> {{ report.revision_notes }}. Koreksi transaksi atau hitungan fisik, lalu kirim ulang.
        </div>
        <div v-else-if="!open" class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-gray-300">
            Stok tanggal ini sudah ditutup (opname {{ report ? statusMap[report.status]?.label.toLowerCase() : 'sudah dikirim' }}). Data hanya bisa dilihat.
        </div>

        <div v-if="isAdmin && !branch" class="rounded-xl border border-dashed p-10 text-center text-sm text-gray-400">
            Pilih cabang terlebih dahulu untuk mencatat barang masuk atau keluar.
        </div>

        <Card v-if="branch">
            <CardHeader class="border-b pb-3">
                <CardTitle class="flex items-center gap-2 text-sm font-bold">
                    <ClipboardCheck class="size-4 text-rose-500" /> Stok {{ fmtDate(date) }}
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
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
                        <span v-if="t.count" class="ml-1 rounded-full bg-rose-100 px-1.5 text-xs text-rose-600">{{ t.count }}</span>
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

                <!-- TAB MASUK -->
                <form v-if="tab === 'in'" @submit.prevent="submitTx('in')">
                    <div class="grid gap-3 p-3 md:grid-cols-2">
                        <div
                            v-for="{ item, idx } in byName"
                            :key="item.id"
                            class="space-y-3 rounded-xl border bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950"
                            :class="inLines[idx]?.qty !== '' ? 'border-rose-300' : ''"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-bold text-gray-900 dark:text-white">{{ item.name }}</div>
                                <div class="shrink-0 text-right text-xs text-gray-500">
                                    Stok sekarang<br /><span class="font-semibold text-gray-700 dark:text-gray-300">{{ mixed(item, item.stock_qty) }}</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <input v-model="inLines[idx].qty" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0" :disabled="!open" :class="numCls" />
                                <select v-model="inLines[idx].unit_id" :disabled="!open" :class="unitCls" class="w-28 shrink-0">
                                    <option v-for="u in [...item.unit_options].reverse()" :key="u.unit_id" :value="u.unit_id">{{ u.name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div v-if="byName.length === 0" class="p-8 text-center text-sm text-gray-400">Barang tidak ditemukan.</div>
                    <div class="space-y-3 border-t p-4">
                        <InputError :message="txForm.errors.items || txForm.errors.moved_at" />
                        <input v-model="inNote" placeholder="Catatan (misal: supplier / nomor nota)" :disabled="!open" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        <Button type="submit" class="h-11 w-full" :disabled="txForm.processing || !open || countIn === 0">Simpan Barang Masuk</Button>
                    </div>
                </form>

                <!-- TAB KELUAR -->
                <form v-else-if="tab === 'out'" @submit.prevent="submitTx('out')">
                    <div class="grid gap-3 p-3 md:grid-cols-2">
                        <div
                            v-for="{ item, idx } in byName"
                            :key="item.id"
                            class="space-y-3 rounded-xl border bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950"
                            :class="outLines[idx]?.out.qty !== '' || outLines[idx]?.waste.qty !== '' ? 'border-rose-300' : ''"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-bold text-gray-900 dark:text-white">{{ item.name }}</div>
                                <div class="shrink-0 text-right text-xs text-gray-500">
                                    Stok sekarang<br /><span class="font-semibold text-gray-700 dark:text-gray-300">{{ mixed(item, item.stock_qty) }}</span>
                                </div>
                            </div>
                            <div v-for="(label, key) in { out: 'Terpakai', waste: 'Rusak / Expired' }" :key="key" class="space-y-1">
                                <label class="text-xs font-medium text-gray-500">{{ label }}</label>
                                <div class="flex gap-2">
                                    <input v-model="outLines[idx][key].qty" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0" :disabled="!open" :class="numCls" />
                                    <select v-model="outLines[idx][key].unit_id" :disabled="!open" :class="unitCls" class="w-28 shrink-0">
                                        <option v-for="u in [...item.unit_options].reverse()" :key="u.unit_id" :value="u.unit_id">{{ u.name }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="byName.length === 0" class="p-8 text-center text-sm text-gray-400">Barang tidak ditemukan.</div>
                    <div class="space-y-3 border-t p-4">
                        <InputError :message="txForm.errors.items || txForm.errors.moved_at" />
                        <input v-model="outNote" placeholder="Catatan (opsional)" :disabled="!open" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        <Button type="submit" class="h-11 w-full" :disabled="txForm.processing || !open || countOut === 0">Simpan Barang Keluar</Button>
                    </div>
                </form>

                <!-- TAB OPNAME -->
                <form v-else @submit.prevent="submitOpname">
                    <p class="border-b p-3 text-xs text-gray-500">
                        Rekap otomatis dari transaksi hari ini ({{ movedCount }} barang bergerak). Hitung fisik bersifat opsional: isi hanya barang yang dihitung,
                        selisihnya otomatis jadi penyesuaian stok.
                    </p>
                    <div class="grid gap-3 p-3 md:grid-cols-2">
                        <div
                            v-for="{ item, idx } in recapList"
                            :key="item.id"
                            class="space-y-3 rounded-xl border bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950"
                            :class="item.moved ? '' : 'opacity-70'"
                        >
                            <div class="font-bold text-gray-900 dark:text-white">{{ item.name }}</div>
                            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                <dt class="text-gray-500">Stok awal</dt>
                                <dd class="text-right font-semibold">{{ mixed(item, openingOf(item)) }}</dd>
                                <dt class="text-gray-500">Masuk</dt>
                                <dd class="text-right font-semibold text-emerald-600">{{ mixed(item, item.in_qty) }}</dd>
                                <dt class="text-gray-500">Keluar (terpakai)</dt>
                                <dd class="text-right font-semibold text-red-600">{{ mixed(item, item.out_qty) }}</dd>
                                <dt class="text-gray-500">Rusak / Expired</dt>
                                <dd class="text-right font-semibold text-red-600">{{ mixed(item, item.waste_qty) }}</dd>
                                <template v-if="item.transfer_qty !== 0">
                                    <dt class="text-gray-500">Transfer (net)</dt>
                                    <dd class="text-right font-semibold">{{ mixed(item, item.transfer_qty) }}</dd>
                                </template>
                                <dt class="border-t pt-1 font-medium text-gray-700 dark:text-gray-300">Sisa seharusnya</dt>
                                <dd class="border-t pt-1 text-right text-sm font-bold">{{ mixed(item, item.expected_qty) }}</dd>
                            </dl>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-gray-500">Hitung fisik</label>
                                <div class="flex gap-2">
                                    <input v-model="physLines[idx].qty" type="number" inputmode="decimal" min="0" step="0.01" placeholder="Belum dihitung" :disabled="!open || isAdmin" :class="numCls" />
                                    <select v-model="physLines[idx].unit_id" :disabled="!open || isAdmin" :class="unitCls" class="w-28 shrink-0">
                                        <option v-for="u in [...item.unit_options].reverse()" :key="u.unit_id" :value="u.unit_id">{{ u.name }}</option>
                                    </select>
                                </div>
                                <p v-if="diff(idx) !== null" class="text-xs font-bold" :class="diff(idx) === 0 ? 'text-emerald-600' : 'text-red-600'">
                                    Selisih {{ diff(idx) === 0 ? '0 (sesuai)' : mixed(item, diff(idx) ?? 0) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div v-if="recapList.length === 0" class="p-8 text-center text-sm text-gray-400">Barang tidak ditemukan.</div>
                    <div class="space-y-3 border-t p-4 pb-6">
                        <InputError :message="opnameForm.errors.items || opnameForm.errors.report_date" />
                        <textarea
                            v-model="opnameForm.notes"
                            rows="2"
                            :disabled="!open || isAdmin"
                            placeholder="Catatan (opsional)"
                            class="w-full rounded-lg border border-gray-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                        />
                        <p v-if="isAdmin" class="text-xs text-gray-500">Opname harian dikirim oleh staff cabang. Di sini pusat hanya melihat rekap.</p>
                        <Button v-else type="submit" class="h-11 w-full" :disabled="opnameForm.processing || !open">Kirim Opname ke Pusat</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card v-if="branch && tab !== 'opname'">
            <CardHeader class="border-b pb-3">
                <CardTitle class="text-sm font-bold">Transaksi {{ tab === 'in' ? 'Masuk' : 'Keluar' }} {{ fmtDate(date) }}</CardTitle>
            </CardHeader>
            <CardContent class="divide-y p-0">
                <div v-if="txForTab.length === 0" class="p-6 text-center text-sm text-gray-400">Belum ada transaksi.</div>
                <div v-for="t in txForTab" :key="t.id" class="flex items-start justify-between gap-3 p-4" :class="t.status === 'cancelled' ? 'opacity-50' : ''">
                    <div class="space-y-0.5 text-sm">
                        <div class="text-xs text-gray-500">
                            {{ timeOf(t.created_at) }} · {{ t.user }}
                            <span v-if="t.note"> · {{ t.note }}</span>
                            <Badge v-if="t.status === 'cancelled'" variant="secondary" class="ml-1">Dibatalkan oleh {{ t.cancelled_by }}</Badge>
                        </div>
                        <div v-for="i in t.items" :key="i.id" :class="t.status === 'cancelled' ? 'line-through' : ''">
                            {{ i.name }} — <b>{{ i.qty }} {{ i.unit }}</b>{{ kindLabel[i.type] }}
                        </div>
                    </div>
                    <Button v-if="open && t.status === 'active'" type="button" size="sm" variant="outline" class="shrink-0 gap-1 text-red-600" @click="cancelTx(t)">
                        <Undo2 class="size-3.5" /> Batal
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card v-if="history.length">
            <CardHeader class="border-b pb-3"><CardTitle class="text-sm font-bold">Riwayat Opname</CardTitle></CardHeader>
            <CardContent class="divide-y p-0">
                <button
                    v-for="h in history"
                    :key="h.id"
                    class="flex w-full items-center justify-between p-3 text-left text-sm hover:bg-gray-50 dark:hover:bg-zinc-900"
                    @click="router.get('/stock-reports', { date: h.report_date.slice(0, 10), branch_id: branch?.id })"
                >
                    <span>{{ fmtDate(h.report_date) }}</span>
                    <Badge :class="statusMap[h.status]?.cls">{{ statusMap[h.status]?.label }}</Badge>
                </button>
            </CardContent>
        </Card>
    </div>
</template>
