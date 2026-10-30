<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, Camera, CheckCircle2, Plus, Send, Trash2, Wallet, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import RupiahInput from '@/components/RupiahInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fmtDate, rupiah } from '@/lib/format';

interface Cashflow {
    id: number;
    status: string;
    notes: string | null;
    revision_notes: string | null;
    total_omset: string;
    total_expense: string;
    total_net_cash: string;
    details: Array<{ payment_method: string; nominal: string }>;
    expenses: Array<{ description: string; nominal: string; stock_transaction_id?: number | null; photos?: Array<{ id: number; photo_full_url: string }> }>;
    setoran_transfers: Array<{ transfer_amount: string; proofs?: Array<{ id: number; photo_full_url: string }> }>;
}

const props = defineProps<{
    branch: { id: number; branch_name: string; branch_code: string } | null;
    date: string;
    today: string;
    wallets: Record<string, string>;
    cashflow: Cashflow | null;
    purchases: Array<{ id: number; label: string; nominal: number; items: string[] }>;
    history: Array<{ id: number; report_date: string; total_omset: string; total_net_cash: string; status: string; revision_notes: string | null }>;
}>();

type ExpenseRow = { description: string; nominal: number | ''; stock_transaction_id: number | null; photos: File[]; keep: Array<{ id: number; url: string }> };

const form = useForm<{
    report_date: string;
    wallets: Record<string, number | ''>;
    expenses: ExpenseRow[];
    transfer_amount: number | '';
    proofs: File[];
    keep_proofs: Array<{ id: number; url: string }>;
    notes: string;
}>({ report_date: props.date, wallets: {}, expenses: [], transfer_amount: '', proofs: [], keep_proofs: [], notes: '' });

// Preview bukti: object URL di-cache per file dan dibebaskan saat diganti/hapus
const urlCache = new WeakMap<File, string>();
const previewUrl = (f: File): string => {
    let u = urlCache.get(f);
    if (!u) {
        u = URL.createObjectURL(f);
        urlCache.set(f, u);
    }
    return u;
};
const releaseUrl = (f: File) => {
    const u = urlCache.get(f);
    if (u) {
        URL.revokeObjectURL(u);
        urlCache.delete(f);
    }
};
const amountTouched = ref(false);

const purchaseRow = (p: { id: number; label: string; nominal: number }): ExpenseRow => ({
    description: p.label,
    nominal: p.nominal > 0 ? p.nominal : '',
    stock_transaction_id: p.id,
    photos: [],
    keep: [],
});

const init = () => {
    form.clearErrors();
    form.report_date = props.date;
    const w: Record<string, number | ''> = {};
    Object.keys(props.wallets).forEach((k) => {
        const found = props.cashflow?.details.find((d) => d.payment_method === k);
        w[k] = found ? Number(found.nominal) : '';
    });
    form.wallets = w;
    // Closing baru: barang masuk hari itu otomatis jadi baris pengeluaran (bisa dihapus). Closing yang sudah ada memakai data tersimpan.
    form.expenses = props.cashflow
        ? props.cashflow.expenses.map((e) => ({
              description: e.description,
              nominal: Number(e.nominal),
              stock_transaction_id: e.stock_transaction_id ?? null,
              photos: [],
              keep: (e.photos ?? []).map((p) => ({ id: p.id, url: p.photo_full_url })),
          }))
        : props.purchases.map(purchaseRow);
    form.notes = props.cashflow?.notes ?? '';
    const tr = props.cashflow?.setoran_transfers[0];
    form.proofs.forEach(releaseUrl);
    form.transfer_amount = tr ? Number(tr.transfer_amount) : '';
    form.proofs = [];
    form.keep_proofs = (tr?.proofs ?? []).map((p) => ({ id: p.id, url: p.photo_full_url }));
    amountTouched.value = !!props.cashflow;
};
const editing = ref(false);
watch(
    () => [props.date, props.cashflow],
    () => {
        init();
        // Closing yang sudah terkirim ditampilkan sebagai ringkasan; form hanya muncul untuk input baru / revisi / saat klik Ubah
        editing.value = props.cashflow?.status === 'revision_needed';
    },
    { immediate: true },
);
const startEdit = () => {
    init();
    editing.value = true;
};

const err = (key: string): string | undefined => (form.errors as Record<string, string>)[key];

const num = (v: number | ''): number => Number(v) || 0;
const totalOmset = computed(() => Object.values(form.wallets).reduce<number>((a, v) => a + num(v), 0));
const totalExpense = computed(() => form.expenses.reduce((a, e) => a + num(e.nominal), 0));
const totalSetor = computed(() => num(form.wallets.tunai ?? '') - totalExpense.value);
const totalTransfer = computed(() => num(form.transfer_amount));
const mismatch = computed(() => totalTransfer.value !== totalSetor.value);

watch(totalSetor, (v) => {
    if (!amountTouched.value) form.transfer_amount = v > 0 ? v : '';
});

const readonly = computed(() => props.cashflow?.status === 'verified_by_finance');
const statusMap: Record<string, { label: string; cls: string }> = {
    submitted: { label: 'Menunggu Verifikasi', cls: 'bg-amber-100 text-amber-700' },
    verified_by_finance: { label: 'Terverifikasi', cls: 'bg-emerald-100 text-emerald-700' },
    revision_needed: { label: 'Perlu Revisi', cls: 'bg-red-100 text-red-700' },
};

const changeDate = (e: Event) => router.get('/cashflows', { date: (e.target as HTMLInputElement).value }, { preserveState: true, preserveScroll: true });
const addExpense = () => form.expenses.push({ description: '', nominal: '', stock_transaction_id: null, photos: [], keep: [] });
// Barang masuk hari itu yang tidak (atau belum) masuk ke daftar pengeluaran
const availablePurchases = computed(() => props.purchases.filter((p) => !form.expenses.some((e) => e.stock_transaction_id === p.id)));
const purchaseItems = (e: ExpenseRow): string[] => props.purchases.find((p) => p.id === e.stock_transaction_id)?.items ?? [e.description.replace(/^Barang masuk:\s*/, '')];
const addPurchase = (p: { id: number; label: string; nominal: number }) => form.expenses.push(purchaseRow(p));
const MAX_PROOFS = 5;
const addProofs = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const room = MAX_PROOFS - form.proofs.length - form.keep_proofs.length;
    form.proofs.push(...Array.from(input.files ?? []).slice(0, Math.max(0, room)));
    input.value = '';
};
const removeProof = (idx: number) => {
    const [f] = form.proofs.splice(idx, 1);
    if (f) releaseUrl(f);
};
const preview = ref<{ url: string; name: string } | null>(null);
const openPreview = (f: File) => (preview.value = { url: previewUrl(f), name: f.name });
const openPreviewUrl = (url: string, name: string) => (preview.value = { url, name });

const MAX_EXPENSE_PHOTOS = 5;
const addExpensePhotos = (e: ExpenseRow, ev: Event) => {
    const input = ev.target as HTMLInputElement;
    const room = MAX_EXPENSE_PHOTOS - e.photos.length - e.keep.length;
    e.photos.push(...Array.from(input.files ?? []).slice(0, Math.max(0, room)));
    input.value = '';
};
const removeExpensePhoto = (e: ExpenseRow, idx: number) => {
    const [f] = e.photos.splice(idx, 1);
    if (f) releaseUrl(f);
};
const removeExpense = (i: number) => {
    form.expenses[i].photos.forEach(releaseUrl);
    form.expenses.splice(i, 1);
};
onBeforeUnmount(() => {
    form.proofs.forEach(releaseUrl);
    form.expenses.forEach((e) => e.photos.forEach(releaseUrl));
});

const submit = () => {
    if (mismatch.value && !confirm('Total transfer tidak sama dengan total setor. Tetap kirim? (Admin Pusat bisa meminta revisi)')) return;
    form.transform(({ keep_proofs, ...d }) => ({
        ...d,
        keep_proof_ids: keep_proofs.map((k) => k.id),
        expenses: d.expenses.map((e) => ({
            description: e.description,
            nominal: e.nominal,
            stock_transaction_id: e.stock_transaction_id,
            photos: e.photos,
            keep_photo_ids: e.keep.map((k) => k.id),
        })),
    })).post('/cashflows', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    });
};
</script>

<template>
    <Head title="Input Cashflow Harian - Momoyo Ops" />

    <div class="mx-auto max-w-3xl flex-1 space-y-5 p-4 sm:p-6 lg:p-8">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-black tracking-tight text-gray-900 dark:text-white">
                <Wallet class="size-6 text-rose-500" /> Input Cashflow Harian
            </h1>
            <p class="text-sm text-gray-500">{{ branch?.branch_name ?? 'Anda belum terdaftar di cabang' }} · closing harian & setoran ke Admin Pusat</p>
        </div>

        <div v-if="!branch" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
            Anda belum ditempatkan di cabang aktif. Hubungi Admin Pusat.
        </div>

        <template v-else>
            <Card>
                <CardContent class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="space-y-1">
                        <Label for="date" class="text-xs font-semibold">Tanggal Closing</Label>
                        <Input id="date" type="date" :value="date" :max="today" class="h-10 w-44 rounded-xl" @change="changeDate" />
                    </div>
                    <Badge v-if="cashflow" :class="statusMap[cashflow.status]?.cls" class="text-xs font-bold">
                        {{ statusMap[cashflow.status]?.label }}
                    </Badge>
                </CardContent>
            </Card>

            <div v-if="cashflow?.status === 'revision_needed'" class="flex gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <AlertCircle class="mt-0.5 size-4 shrink-0" />
                <div><b>Admin Pusat meminta revisi:</b> {{ cashflow.revision_notes }}<br />Perbaiki data lalu kirim ulang (upload ulang bukti transfer).</div>
            </div>
            <div v-if="readonly" class="flex gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                <CheckCircle2 class="mt-0.5 size-4 shrink-0" /> Closing tanggal ini sudah diverifikasi dan tidak bisa diubah.
            </div>

            <!-- Ringkasan closing yang sudah terkirim -->
            <Card v-if="cashflow && !editing" class="border-emerald-200">
                <CardHeader class="border-b pb-3">
                    <CardTitle class="flex items-center gap-2 text-sm font-bold">
                        <CheckCircle2 class="size-4 text-emerald-600" /> Closing {{ fmtDate(date) }} sudah terkirim
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 p-4 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Total Omset</span><b class="font-mono">{{ rupiah(cashflow.total_omset) }}</b></div>
                    <div class="flex justify-between"><span class="text-gray-500">Total Pengeluaran</span><b class="font-mono">{{ rupiah(cashflow.total_expense) }}</b></div>
                    <div class="flex justify-between border-t pt-2"><span class="font-semibold">Total Setor</span><b class="font-mono text-rose-600">{{ rupiah(cashflow.total_net_cash) }}</b></div>
                    <div class="flex justify-between"><span class="text-gray-500">Nominal Transfer</span><b class="font-mono">{{ rupiah(cashflow.setoran_transfers[0]?.transfer_amount) }}</b></div>
                    <div v-if="cashflow.setoran_transfers[0]?.proofs?.length" class="flex flex-wrap gap-2 pt-1">
                        <button v-for="p in cashflow.setoran_transfers[0].proofs" :key="p.id" type="button" class="size-14 overflow-hidden rounded-xl border shadow-sm hover:ring-2 hover:ring-rose-500" @click="openPreviewUrl(p.photo_full_url, 'Bukti transfer')">
                            <img :src="p.photo_full_url" alt="Bukti transfer" class="size-full object-cover" />
                        </button>
                    </div>
                    <p class="text-xs text-gray-400">{{ readonly ? 'Sudah diverifikasi Admin Pusat.' : 'Menunggu verifikasi Admin Pusat.' }} Pilih tanggal lain di atas untuk closing tanggal berikutnya.</p>
                    <Button v-if="!readonly" type="button" variant="outline" class="w-full rounded-xl" @click="startEdit">Ubah / Kirim Ulang</Button>
                </CardContent>
            </Card>

            <form v-else class="space-y-5" @submit.prevent="submit">
                <fieldset :disabled="readonly" class="space-y-5">
                    <!-- Pemasukan per wallet -->
                    <Card>
                        <CardHeader class="border-b pb-3"><CardTitle class="text-sm font-bold">1. Pemasukan per Wallet</CardTitle></CardHeader>
                        <CardContent class="grid grid-cols-2 gap-3 p-4">
                            <div v-for="(label, key) in wallets" :key="key" class="space-y-1">
                                <Label :for="`w-${key}`" class="text-xs font-semibold">{{ label }}</Label>
                                <RupiahInput :id="`w-${key}`" v-model="form.wallets[key]" class="h-10 rounded-xl" />
                                <InputError :message="err(`wallets.${key}`)" />
                            </div>
                            <div class="col-span-2 flex justify-between rounded-xl bg-gray-50 px-3 py-2 text-sm dark:bg-zinc-900">
                                <span class="text-gray-500">Total Omset</span><b class="font-mono">{{ rupiah(totalOmset) }}</b>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Pengeluaran -->
                    <Card>
                        <CardHeader class="border-b pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-bold">2. Pengeluaran Hari Ini</CardTitle>
                                <Button type="button" size="sm" variant="outline" class="h-8 gap-1 rounded-xl" @click="addExpense"><Plus class="size-3.5" /> Tambah</Button>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-2 p-4">
                            <p v-if="!form.expenses.length" class="text-xs text-gray-400">Tidak ada pengeluaran. Klik Tambah (contoh: telur 20.000, parkir 15.000).</p>
                            <div v-for="(e, i) in form.expenses" :key="i" class="space-y-2.5 rounded-xl border p-3" :class="e.stock_transaction_id ? 'border-sky-200 bg-sky-50/50 dark:border-sky-900 dark:bg-sky-950/20' : 'border-gray-200 dark:border-zinc-800'">
                                <!-- Barang masuk: ringkasan item (keterangan otomatis, nominal bisa disesuaikan dengan nota) -->
                                <template v-if="e.stock_transaction_id">
                                    <div class="flex items-center justify-between gap-2">
                                        <Badge class="bg-sky-100 text-[11px] text-sky-700 hover:bg-sky-100">Barang Masuk / Belanja</Badge>
                                        <Button type="button" size="sm" variant="ghost" class="h-7 gap-1 px-2 text-xs text-rose-500 hover:text-rose-600" title="Tidak dimasukkan ke pengeluaran" @click="removeExpense(i)"><Trash2 class="size-3.5" /> Hapus</Button>
                                    </div>
                                    <ul class="space-y-0.5 text-sm text-gray-700 dark:text-zinc-300">
                                        <li v-for="(it, ii) in purchaseItems(e)" :key="ii" class="flex gap-2"><span class="text-sky-400">•</span><span>{{ it }}</span></li>
                                    </ul>
                                    <div class="flex items-center justify-between gap-3 border-t border-sky-100 pt-2 dark:border-sky-900">
                                        <Label class="text-xs text-gray-500">Total belanja</Label>
                                        <div class="w-40"><RupiahInput v-model="e.nominal" class="h-10 rounded-xl bg-white" /></div>
                                    </div>
                                    <InputError :message="err(`expenses.${i}.nominal`)" />
                                    <InputError :message="err(`expenses.${i}.description`)" />
                                </template>
                                <div v-else class="flex items-start gap-2">
                                    <div class="flex-1 space-y-1">
                                        <Input v-model="e.description" placeholder="Keterangan (telur, parkir…)" class="h-10 rounded-xl" />
                                        <InputError :message="err(`expenses.${i}.description`)" />
                                    </div>
                                    <div class="w-32 space-y-1">
                                        <RupiahInput v-model="e.nominal" class="h-10 rounded-xl" />
                                        <InputError :message="err(`expenses.${i}.nominal`)" />
                                    </div>
                                    <Button type="button" size="icon" variant="ghost" class="size-10 text-rose-500" @click="removeExpense(i)"><Trash2 class="size-4" /></Button>
                                </div>
                                <!-- Bukti pengeluaran (opsional, maks 5) -->
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <button v-for="k in e.keep" :key="`k${k.id}`" type="button" class="group relative size-10 overflow-hidden rounded-lg border shadow-sm hover:ring-2 hover:ring-rose-500" title="Preview" @click="openPreviewUrl(k.url, 'Bukti pengeluaran')">
                                        <img :src="k.url" alt="Bukti pengeluaran" class="size-full object-cover" />
                                        <span class="absolute top-0 right-0 hidden size-4 items-center justify-center rounded-bl bg-black/60 text-white group-hover:flex" title="Hapus" @click.stop="e.keep.splice(e.keep.indexOf(k), 1)"><X class="size-3" /></span>
                                    </button>
                                    <button v-for="(f, pi) in e.photos" :key="`n${pi}`" type="button" class="group relative size-10 overflow-hidden rounded-lg border shadow-sm hover:ring-2 hover:ring-rose-500" title="Preview" @click="openPreview(f)">
                                        <img :src="previewUrl(f)" alt="Bukti pengeluaran" class="size-full object-cover" />
                                        <span class="absolute top-0 right-0 hidden size-4 items-center justify-center rounded-bl bg-black/60 text-white group-hover:flex" title="Hapus" @click.stop="removeExpensePhoto(e, pi)"><X class="size-3" /></span>
                                    </button>
                                    <label v-if="e.keep.length + e.photos.length < MAX_EXPENSE_PHOTOS" class="flex size-10 cursor-pointer items-center justify-center rounded-lg border border-dashed border-gray-300 text-gray-400 hover:border-rose-400 hover:text-rose-500" title="Tambah foto bukti (opsional)">
                                        <Camera class="size-4" />
                                        <input type="file" accept="image/*" multiple class="hidden" @change="addExpensePhotos(e, $event)" />
                                    </label>
                                    <span v-if="!e.keep.length && !e.photos.length" class="text-[11px] text-gray-400">Bukti (opsional)</span>
                                </div>
                                <InputError :message="err(`expenses.${i}.photos`)" />
                            </div>
                            <InputError :message="form.errors.expenses" />
                            <div v-if="availablePurchases.length" class="space-y-1.5 rounded-xl border border-dashed border-sky-300 bg-sky-50/60 p-3 text-xs dark:bg-sky-950/20">
                                <p class="font-semibold text-sky-700">Barang masuk hari ini yang tidak dimasukkan ke pengeluaran</p>
                                <div v-for="p in availablePurchases" :key="p.id" class="flex items-center gap-2">
                                    <span class="flex-1 text-gray-600">{{ p.label }}<template v-if="p.nominal"> · {{ rupiah(p.nominal) }}</template></span>
                                    <Button type="button" size="sm" variant="outline" class="h-7 gap-1 rounded-lg" @click="addPurchase(p)"><Plus class="size-3" /> Masukkan</Button>
                                </div>
                            </div>
                            <div class="flex justify-between rounded-xl bg-gray-50 px-3 py-2 text-sm dark:bg-zinc-900">
                                <span class="text-gray-500">Total Pengeluaran</span><b class="font-mono">{{ rupiah(totalExpense) }}</b>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Setoran -->
                    <Card class="border-rose-200">
                        <CardHeader class="border-b pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-bold">3. Setoran ke Pusat</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3 p-4">
                            <div class="flex justify-between rounded-xl bg-rose-50 px-3 py-3 dark:bg-rose-950/30">
                                <span class="text-sm font-semibold text-rose-700">Total Setor (Tunai − Pengeluaran)</span>
                                <b class="font-mono text-lg text-rose-700">{{ rupiah(totalSetor) }}</b>
                            </div>
                            <p v-if="totalSetor < 0" class="-mt-1 flex items-start gap-1.5 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700"><AlertCircle class="mt-0.5 size-3.5 shrink-0" /> Pengeluaran lebih besar dari Tunai di Kasir. Isi dulu Tunai di Kasir (bagian 1), atau hapus/sesuaikan pengeluaran.</p>

                            <div class="space-y-1">
                                <Label class="text-xs font-semibold">Nominal Transfer</Label>
                                <RupiahInput v-model="form.transfer_amount" placeholder="Nominal transfer" class="h-10 rounded-xl" @update:model-value="amountTouched = true" />
                                <InputError :message="form.errors.transfer_amount" />
                            </div>

                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Bukti Transfer <span class="text-rose-500">*</span> <span class="font-normal text-gray-400">(maks {{ MAX_PROOFS }} foto)</span></Label>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="k in form.keep_proofs" :key="`k${k.id}`" type="button" class="group relative size-16 overflow-hidden rounded-xl border shadow-sm hover:ring-2 hover:ring-rose-500" title="Preview" @click="openPreviewUrl(k.url, 'Bukti transfer')">
                                        <img :src="k.url" alt="Bukti transfer" class="size-full object-cover" />
                                        <span class="absolute top-0 right-0 hidden size-5 items-center justify-center rounded-bl-lg bg-black/60 text-white group-hover:flex" title="Hapus" @click.stop="form.keep_proofs.splice(form.keep_proofs.indexOf(k), 1)"><X class="size-3.5" /></span>
                                    </button>
                                    <button v-for="(f, pi) in form.proofs" :key="`n${pi}`" type="button" class="group relative size-16 overflow-hidden rounded-xl border shadow-sm hover:ring-2 hover:ring-rose-500" title="Preview" @click="openPreview(f)">
                                        <img :src="previewUrl(f)" alt="Bukti transfer" class="size-full object-cover" />
                                        <span class="absolute top-0 right-0 hidden size-5 items-center justify-center rounded-bl-lg bg-black/60 text-white group-hover:flex" title="Hapus" @click.stop="removeProof(pi)"><X class="size-3.5" /></span>
                                    </button>
                                    <label v-if="form.keep_proofs.length + form.proofs.length < MAX_PROOFS" class="flex size-16 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-xl border border-dashed border-gray-300 text-[10px] text-gray-400 hover:border-rose-400 hover:text-rose-500" title="Tambah foto bukti transfer">
                                        <Camera class="size-4" />
                                        <span>Tambah</span>
                                        <input type="file" accept="image/*" multiple class="hidden" @change="addProofs" />
                                    </label>
                                </div>
                                <InputError :message="form.errors.proofs" />
                            </div>
                            <p v-if="mismatch && totalTransfer" class="text-xs font-semibold text-amber-600">
                                Total transfer {{ rupiah(totalTransfer) }} berbeda dengan total setor {{ rupiah(totalSetor) }}.
                            </p>
                        </CardContent>
                    </Card>

                    <div class="space-y-1.5">
                        <Label for="notes" class="text-xs font-semibold">Catatan (opsional)</Label>
                        <textarea id="notes" v-model="form.notes" rows="2" class="w-full rounded-xl border border-gray-300 p-3 text-xs dark:border-zinc-800 dark:bg-zinc-950" />
                    </div>
                    <InputError :message="form.errors.report_date" />
                    <Button type="submit" class="h-11 w-full gap-2 rounded-xl" :disabled="form.processing">
                        <Send class="size-4" /> {{ cashflow ? 'Kirim Ulang Closing' : 'Kirim Closing Harian' }}
                    </Button>
                </fieldset>
            </form>

            <Card>
                <CardHeader class="border-b pb-3"><CardTitle class="text-sm font-bold">Riwayat Closing</CardTitle></CardHeader>
                <CardContent class="p-0">
                    <div v-if="!history.length" class="p-6 text-center text-xs text-gray-400">Belum ada closing.</div>
                    <button
                        v-for="h in history"
                        :key="h.id"
                        type="button"
                        class="flex w-full items-center justify-between gap-3 border-b p-3 text-left text-xs last:border-0 hover:bg-gray-50 dark:hover:bg-zinc-900/50"
                        @click="router.get('/cashflows', { date: h.report_date.slice(0, 10) }, { preserveState: true })"
                    >
                        <div>
                            <div class="font-bold">{{ fmtDate(h.report_date) }}</div>
                            <div class="text-gray-500">Setor {{ rupiah(h.total_net_cash) }}</div>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold" :class="statusMap[h.status]?.cls">{{ statusMap[h.status]?.label }}</span>
                    </button>
                </CardContent>
            </Card>
        </template>

        <Dialog :open="!!preview" @update:open="(v: boolean) => !v && (preview = null)">
            <DialogContent class="max-h-[90vh] max-w-xl overflow-auto rounded-3xl p-4">
                <DialogHeader>
                    <DialogTitle class="truncate text-sm font-bold">{{ preview?.name }}</DialogTitle>
                </DialogHeader>
                <img v-if="preview" :src="preview.url" alt="Preview bukti" class="mx-auto max-h-[70vh] rounded-xl object-contain" />
            </DialogContent>
        </Dialog>
    </div>
</template>
