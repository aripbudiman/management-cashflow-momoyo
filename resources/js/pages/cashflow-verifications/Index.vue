<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, Eye, FileSpreadsheet, RefreshCw, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fmtDate, rupiah } from '@/lib/format';

interface Cashflow {
    id: number;
    report_date: string;
    status: string;
    total_omset: string;
    total_expense: string;
    total_net_cash: string;
    notes: string | null;
    revision_notes: string | null;
    branch: { branch_name: string };
    closing_by: { name: string } | null;
    verified_by: { name: string } | null;
    details: Array<{ payment_method: string; nominal: string }>;
    expenses: Array<{ id: number; description: string; nominal: string; photos: Array<{ id: number; photo_full_url: string }> }>;
    setoran_transfers: Array<{
        id: number;
        transfer_amount: string;
        proofs: Array<{ id: number; photo_full_url: string }>;
    }>;
}

const props = defineProps<{
    cashflows: { data: Cashflow[]; links: Array<{ url: string | null; label: string; active: boolean }>; current_page: number; last_page: number; total: number };
    branches: Array<{ id: number; branch_name: string }>;
    wallets: Record<string, string>;
    filters: { branch_id?: string; status?: string; month?: string };
}>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const statusMap: Record<string, { label: string; cls: string }> = {
    submitted: { label: 'Menunggu', cls: 'bg-amber-100 text-amber-700' },
    verified_by_finance: { label: 'Terverifikasi', cls: 'bg-emerald-100 text-emerald-700' },
    revision_needed: { label: 'Perlu Revisi', cls: 'bg-red-100 text-red-700' },
};

const filter = (k: string, v: string) => router.get('/cashflow-verifications', { ...props.filters, [k]: v || undefined }, { preserveState: true, preserveScroll: true });
const reset = () => router.get('/cashflow-verifications', {}, { preserveState: true });
const selectCls =
    'h-10 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-rose-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300';

const detail = ref<Cashflow | null>(null);
const preview = ref<{ url: string; name: string } | null>(null);
const revising = ref(false);
const notes = ref('');

const transferred = (c: Cashflow) => c.setoran_transfers.reduce((a, t) => a + Number(t.transfer_amount), 0);
const matches = (c: Cashflow) => transferred(c) === Number(c.total_net_cash);
const walletValue = (c: Cashflow, k: string) => Number(c.details.find((d) => d.payment_method === k)?.nominal ?? 0);

const open = (c: Cashflow) => {
    detail.value = c;
    revising.value = false;
    notes.value = '';
};
const close = () => (detail.value = null);

const approve = () => {
    if (!detail.value) return;
    router.post(`/cashflow-verifications/${detail.value.id}/verify`, {}, { preserveScroll: true, onSuccess: close });
};
const revise = () => {
    if (!detail.value) return;
    router.post(`/cashflow-verifications/${detail.value.id}/revise`, { revision_notes: notes.value }, { preserveScroll: true, onSuccess: close });
};
</script>

<template>
    <Head title="Verifikasi Setoran - Momoyo Ops" />

    <div class="flex-1 space-y-5 p-4 sm:p-6 lg:p-8">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-black tracking-tight text-gray-900 dark:text-white">
                <FileSpreadsheet class="size-6 text-rose-500" /> Verifikasi Setoran Cabang
            </h1>
            <p class="text-sm text-gray-500">Cek bukti transfer, rekonsiliasi dengan total setor, lalu setujui atau minta revisi.</p>
        </div>

        <Card class="border-gray-200 shadow-sm dark:border-zinc-800">
            <CardContent class="p-4">
                <div class="grid grid-cols-1 items-center gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <select :value="filters.branch_id ?? ''" :class="selectCls" @change="filter('branch_id', ($event.target as HTMLSelectElement).value)">
                        <option value="">Semua Cabang Gerai</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.branch_name }}</option>
                    </select>
                    <select :value="filters.status ?? ''" :class="selectCls" @change="filter('status', ($event.target as HTMLSelectElement).value)">
                        <option value="">Semua Status</option>
                        <option value="submitted">Menunggu</option>
                        <option value="verified_by_finance">Terverifikasi</option>
                        <option value="revision_needed">Perlu Revisi</option>
                    </select>
                    <Input type="month" :model-value="filters.month ?? ''" class="h-10 rounded-xl text-xs" @change="filter('month', ($event.target as HTMLInputElement).value)" />
                    <div>
                        <Button variant="outline" size="icon" title="Reset Filter" class="size-10 rounded-xl text-gray-500" @click="reset"><RefreshCw class="size-4" /></Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card class="overflow-hidden border-gray-200 shadow-md dark:border-zinc-800">
            <CardHeader class="border-b border-gray-100 p-4 dark:border-zinc-800">
                <CardTitle class="text-base font-bold">Setoran Masuk</CardTitle>
                <CardDescription class="text-xs">Total {{ cashflows.total }} closing</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-gray-200 bg-gray-50 text-gray-500 dark:border-zinc-800 dark:bg-zinc-900/60">
                            <tr>
                                <th class="px-4 py-3 font-bold">Tanggal</th>
                                <th class="px-4 py-3 font-bold">Cabang</th>
                                <th class="px-4 py-3 font-bold">Closing oleh</th>
                                <th class="px-4 py-3 text-right font-bold">Omset</th>
                                <th class="px-4 py-3 text-right font-bold">Pengeluaran</th>
                                <th class="px-4 py-3 text-right font-bold">Total Setor</th>
                                <th class="px-4 py-3 text-right font-bold">Transfer</th>
                                <th class="px-4 py-3 font-bold">Status</th>
                                <th class="px-4 py-3 text-center font-bold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="c in cashflows.data" :key="c.id" class="transition-colors hover:bg-gray-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-4 py-3.5 font-bold">{{ fmtDate(c.report_date) }}</td>
                                <td class="px-4 py-3.5">{{ c.branch.branch_name }}</td>
                                <td class="px-4 py-3.5">{{ c.closing_by?.name ?? '-' }}</td>
                                <td class="px-4 py-3.5 text-right font-mono">{{ rupiah(c.total_omset) }}</td>
                                <td class="px-4 py-3.5 text-right font-mono">{{ rupiah(c.total_expense) }}</td>
                                <td class="px-4 py-3.5 text-right font-mono font-bold">{{ rupiah(c.total_net_cash) }}</td>
                                <td class="px-4 py-3.5 text-right font-mono" :class="matches(c) ? 'text-emerald-600' : 'font-bold text-red-600'">{{ rupiah(transferred(c)) }}</td>
                                <td class="px-4 py-3.5">
                                    <Badge variant="outline" class="border text-[10px] font-bold" :class="statusMap[c.status]?.cls">{{ statusMap[c.status]?.label }}</Badge>
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <Button size="sm" variant="outline" class="h-8 gap-1 rounded-xl text-xs font-semibold" @click="open(c)"><Eye class="size-3.5" /> Detail</Button>
                                </td>
                            </tr>
                            <tr v-if="!cashflows.data.length">
                                <td colspan="9" class="py-12 text-center text-gray-400">
                                    <FileSpreadsheet class="mx-auto mb-2 size-10 text-gray-300" />
                                    <p class="text-sm font-bold">Belum ada setoran masuk</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="cashflows.last_page > 1" class="flex items-center justify-between border-t border-gray-100 p-4 text-xs dark:border-zinc-800">
                    <span class="text-gray-500">Menampilkan halaman <strong>{{ cashflows.current_page }}</strong> dari <strong>{{ cashflows.last_page }}</strong></span>
                    <div class="flex items-center gap-1">
                        <template v-for="(link, i) in cashflows.links" :key="i">
                            <Button
                                v-if="link.url"
                                variant="outline"
                                size="sm"
                                :class="['h-8 rounded-lg px-3 text-xs', link.active ? 'border-rose-600 bg-rose-600 font-bold text-white' : '']"
                                @click="router.get(link.url, {}, { preserveScroll: true, preserveState: true })"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Dialog :open="!!detail" @update:open="(v: boolean) => !v && close()">
            <DialogContent v-if="detail" class="max-h-[90vh] max-w-2xl overflow-y-auto rounded-3xl p-6">
                <DialogHeader>
                    <DialogTitle class="text-lg font-black">{{ detail.branch.branch_name }} · {{ fmtDate(detail.report_date) }}</DialogTitle>
                    <DialogDescription class="text-xs">Closing oleh {{ detail.closing_by?.name ?? '-' }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-2">
                        <div v-for="(label, k) in wallets" :key="k" class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-zinc-900">
                            <span class="text-gray-500">{{ label }}</span><b class="font-mono">{{ rupiah(walletValue(detail, k)) }}</b>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1 font-bold text-gray-500 uppercase">Pengeluaran</div>
                        <div v-if="!detail.expenses.length" class="text-gray-400">Tidak ada.</div>
                        <div v-for="e in detail.expenses" :key="e.id" class="border-b py-1">
                            <div class="flex justify-between"><span>{{ e.description }}</span><span class="font-mono">{{ rupiah(e.nominal) }}</span></div>
                            <div v-if="e.photos.length" class="mt-1 flex flex-wrap gap-1.5">
                                <button v-for="p in e.photos" :key="p.id" type="button" class="size-10 overflow-hidden rounded-lg border hover:ring-2 hover:ring-rose-500" title="Preview" @click="preview = { url: p.photo_full_url, name: `Bukti ${e.description}` }">
                                    <img :src="p.photo_full_url" alt="Bukti pengeluaran" class="size-full object-cover" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1 rounded-xl border p-3">
                        <div class="flex justify-between"><span>Tunai di Kasir</span><span class="font-mono">{{ rupiah(walletValue(detail, 'tunai')) }}</span></div>
                        <div class="flex justify-between"><span>− Pengeluaran</span><span class="font-mono">{{ rupiah(detail.total_expense) }}</span></div>
                        <div class="flex justify-between border-t pt-1 font-bold"><span>Total Setor</span><span class="font-mono">{{ rupiah(detail.total_net_cash) }}</span></div>
                        <div class="flex justify-between font-bold" :class="matches(detail) ? 'text-emerald-600' : 'text-red-600'">
                            <span>Total Transfer {{ matches(detail) ? '(sesuai ✓)' : '(TIDAK SESUAI)' }}</span><span class="font-mono">{{ rupiah(transferred(detail)) }}</span>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1 font-bold text-gray-500 uppercase">Bukti Transfer</div>
                        <div class="flex flex-wrap gap-2">
                            <template v-for="t in detail.setoran_transfers" :key="t.id">
                                <button v-for="p in t.proofs" :key="p.id" type="button" class="size-24 overflow-hidden rounded-xl border hover:ring-2 hover:ring-rose-500" title="Preview" @click="preview = { url: p.photo_full_url, name: 'Bukti transfer' }">
                                    <img :src="p.photo_full_url" alt="Bukti transfer" class="size-full object-cover" />
                                </button>
                            </template>
                        </div>
                    </div>

                    <div v-if="detail.notes" class="rounded-lg bg-gray-50 p-2.5 text-gray-600 dark:bg-zinc-900">Catatan cabang: {{ detail.notes }}</div>
                    <div v-if="detail.revision_notes" class="rounded-lg bg-red-50 p-2.5 text-red-700">Catatan revisi: {{ detail.revision_notes }}</div>

                    <div v-if="revising" class="space-y-1.5">
                        <Label class="text-xs font-semibold">Alasan revisi <span class="text-rose-500">*</span></Label>
                        <textarea v-model="notes" rows="3" class="w-full rounded-xl border border-gray-300 p-3 text-xs dark:border-zinc-800 dark:bg-zinc-950" />
                        <InputError :message="errors.revision_notes" />
                    </div>
                    <InputError :message="errors.status" />
                </div>

                <DialogFooter v-if="detail.status === 'submitted'">
                    <template v-if="!revising">
                        <Button variant="outline" class="gap-1 rounded-xl text-red-600" @click="revising = true"><X class="size-4" /> Minta Revisi</Button>
                        <Button class="gap-1 rounded-xl bg-emerald-600 hover:bg-emerald-700" :disabled="!matches(detail)" @click="approve"><Check class="size-4" /> Setujui</Button>
                    </template>
                    <template v-else>
                        <Button variant="outline" class="rounded-xl" @click="revising = false">Batal</Button>
                        <Button class="rounded-xl bg-red-600 hover:bg-red-700" :disabled="!notes.trim()" @click="revise">Kirim Revisi</Button>
                    </template>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ImagePreviewDialog v-model="preview" />
    </div>
</template>
