<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Edit3, Landmark, Pencil, Plus, Trash2, Wallet } from '@lucide/vue';
import { ref } from 'vue';
import ExpenseProofField from '@/components/ExpenseProofField.vue';
import InputError from '@/components/InputError.vue';
import ProofThumbs from '@/components/ProofThumbs.vue';
import RupiahInput from '@/components/RupiahInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fmtDate, rupiah } from '@/lib/format';

interface BalanceRow {
    branch_id: number;
    branch_name: string;
    opening_balance: number;
    setoran_in: number;
    central_expense: number;
    balance: number;
}
interface Expense {
    id: number;
    expense_date: string;
    branch_id: number | null;
    category: string;
    nominal: string;
    description: string | null;
    branch: { id: number; branch_name: string } | null;
    creator: { name: string } | null;
    proofs?: Array<{ id: number; url: string }>;
}

const props = defineProps<{
    filters: { month: string; branch_id: string | number | null; category: string | null };
    branches: Array<{ id: number; branch_name: string }>;
    categories: string[];
    balances: { rows: BalanceRow[]; total_balance: number; owner_expense: number };
    expenses: { data: Expense[]; links: Array<{ url: string | null; label: string; active: boolean }> };
    filteredTotal: number;
}>();

const go = (params: Record<string, string | null>) =>
    router.get('/central-expenses', { ...props.filters, ...params }, { preserveState: true, preserveScroll: true });

// --- Saldo awal per cabang (inline edit)
const editingBalance = ref<number | null>(null);
const balanceValue = ref<number | ''>('');
const startBalance = (b: BalanceRow) => {
    editingBalance.value = b.branch_id;
    balanceValue.value = b.opening_balance;
};
const saveBalance = (b: BalanceRow) => {
    router.put(`/branches/${b.branch_id}/opening-balance`, { opening_balance: Number(balanceValue.value) || 0 }, {
        preserveScroll: true,
        onSuccess: () => (editingBalance.value = null),
    });
};

// --- Form pengeluaran pusat
const isOpen = ref(false);
const editingId = ref<number | null>(null);
const today = new Date().toLocaleDateString('en-CA');
const form = useForm<{ expense_date: string; source: string; category: string; nominal: number | ''; description: string; proofs: File[]; remove_proof_ids: number[] }>({
    expense_date: today,
    source: 'pusat',
    category: 'Gaji',
    nominal: '',
    description: '',
    proofs: [],
    remove_proof_ids: [],
});
const existingProofs = ref<Array<{ id: number; url: string }>>([]);

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.expense_date = today;
    existingProofs.value = [];
    isOpen.value = true;
};
const openEdit = (e: Expense) => {
    editingId.value = e.id;
    form.clearErrors();
    form.expense_date = e.expense_date.slice(0, 10);
    form.source = e.branch_id ? String(e.branch_id) : 'pusat';
    form.category = e.category;
    form.nominal = Number(e.nominal);
    form.description = e.description ?? '';
    form.proofs = [];
    form.remove_proof_ids = [];
    existingProofs.value = e.proofs ?? [];
    isOpen.value = true;
};
const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => (isOpen.value = false) };
    // source 'pusat' = uang bos langsung (tanpa potong saldo cabang)
    // Upload file butuh multipart; update dikirim sebagai POST + _method=PUT
    const multipart = { ...opts, forceFormData: true };
    const editing = editingId.value;
    form.transform(({ source, ...d }) => ({ ...d, branch_id: source === 'pusat' ? '' : String(source), ...(editing ? { _method: 'put' } : {}) }));
    form.post(editing ? `/central-expenses/${editing}` : '/central-expenses', multipart);
};
const remove = (e: Expense) => {
    if (confirm(`Hapus pengeluaran ${rupiah(e.nominal)} (${e.category})?`)) router.delete(`/central-expenses/${e.id}`, { preserveScroll: true });
};

const selectCls = 'h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';
</script>

<template>
    <Head title="Pengeluaran Pusat - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Pengeluaran Pusat</h1>
                <p class="text-sm text-gray-500">Catat gaji & belanja pusat. Pilih dibebankan ke kas cabang, atau dibayar langsung dari uang bos.</p>
            </div>
            <Button class="gap-1.5" @click="openCreate"><Plus class="size-4" /> Catat Pengeluaran</Button>
        </div>

        <!-- SALDO KAS PER CABANG -->
        <Card>
            <CardHeader class="border-b pb-3">
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2 text-sm font-bold"><Wallet class="size-4 text-rose-500" /> Saldo Kas per Cabang</CardTitle>
                    <span class="text-xs text-gray-500">Saldo = saldo awal + setoran terverifikasi − pengeluaran pusat</span>
                </div>
            </CardHeader>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50 text-xs text-gray-500 dark:bg-zinc-900">
                            <th class="p-3 text-left">Cabang</th>
                            <th class="p-3 text-right">Saldo Awal</th>
                            <th class="p-3 text-right">Setoran Masuk</th>
                            <th class="p-3 text-right">Pengeluaran Pusat</th>
                            <th class="p-3 text-right">Saldo Kas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="b in balances.rows" :key="b.branch_id">
                            <td class="p-3 font-bold">{{ b.branch_name }}</td>
                            <td class="p-3 text-right font-mono">
                                <div v-if="editingBalance === b.branch_id" class="ml-auto flex w-52 items-center gap-1">
                                    <RupiahInput v-model="balanceValue" class="h-8 rounded-lg" />
                                    <Button size="icon" class="size-8 shrink-0" @click="saveBalance(b)"><Check class="size-4" /></Button>
                                </div>
                                <button v-else type="button" class="group inline-flex items-center gap-1.5 hover:text-rose-600" title="Ubah saldo awal" @click="startBalance(b)">
                                    {{ rupiah(b.opening_balance) }} <Pencil class="size-3 text-gray-300 group-hover:text-rose-500" />
                                </button>
                            </td>
                            <td class="p-3 text-right font-mono text-emerald-700">{{ rupiah(b.setoran_in) }}</td>
                            <td class="p-3 text-right font-mono text-red-600">{{ rupiah(b.central_expense) }}</td>
                            <td class="p-3 text-right font-mono font-black" :class="b.balance < 0 ? 'text-red-600' : 'text-gray-900 dark:text-white'">{{ rupiah(b.balance) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-black dark:bg-zinc-900">
                            <td class="p-3" colspan="4">Total Saldo Kas Semua Cabang</td>
                            <td class="p-3 text-right font-mono" :class="balances.total_balance < 0 ? 'text-red-600' : ''">{{ rupiah(balances.total_balance) }}</td>
                        </tr>
                    </tfoot>
                </table>
                <p class="border-t px-4 py-2.5 text-xs text-gray-500">
                    Pengeluaran yang dibayar langsung uang bos (tidak memotong cabang): <b class="font-mono">{{ rupiah(balances.owner_expense) }}</b>
                </p>
            </CardContent>
        </Card>

        <!-- DAFTAR PENGELUARAN -->
        <Card>
            <CardHeader class="border-b pb-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <CardTitle class="flex items-center gap-2 text-sm font-bold"><Landmark class="size-4 text-rose-500" /> Riwayat Pengeluaran Pusat</CardTitle>
                    <div class="flex flex-wrap gap-2">
                        <input type="month" :value="filters.month" :class="selectCls" @change="go({ month: ($event.target as HTMLInputElement).value })" />
                        <select :value="filters.branch_id ?? ''" :class="selectCls" @change="go({ branch_id: ($event.target as HTMLSelectElement).value || null })">
                            <option value="">Semua sumber</option>
                            <option value="pusat">Uang Bos / Kas Pusat</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">Kas {{ b.branch_name }}</option>
                        </select>
                        <select :value="filters.category ?? ''" :class="selectCls" @change="go({ category: ($event.target as HTMLSelectElement).value || null })">
                            <option value="">Semua kategori</option>
                            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="!expenses.data.length" class="p-10 text-center text-sm text-gray-400">Belum ada pengeluaran pusat pada filter ini.</div>
                <div v-else class="divide-y">
                    <div v-for="e in expenses.data" :key="e.id" class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold">{{ e.category }}</span>
                                <Badge variant="outline" class="text-[10px]" :class="e.branch ? 'border-sky-200 text-sky-700' : 'border-amber-300 text-amber-700'">{{ e.branch ? `Kas ${e.branch.branch_name}` : 'Uang Bos' }}</Badge>
                            </div>
                            <div class="truncate text-xs text-gray-500">{{ fmtDate(e.expense_date) }}<template v-if="e.description"> · {{ e.description }}</template><template v-if="e.creator"> · {{ e.creator.name }}</template></div>
                            <ProofThumbs :proofs="e.proofs" />
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <b class="mr-2 font-mono text-red-600">{{ rupiah(e.nominal) }}</b>
                            <Button size="icon" variant="ghost" @click="openEdit(e)"><Edit3 class="size-4" /></Button>
                            <Button size="icon" variant="ghost" class="text-rose-500" @click="remove(e)"><Trash2 class="size-4" /></Button>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between border-t bg-gray-50 px-4 py-3 text-sm font-black dark:bg-zinc-900">
                    <span>Total (sesuai filter)</span><span class="font-mono text-red-600">{{ rupiah(filteredTotal) }}</span>
                </div>
                <div v-if="expenses.links.length > 3" class="flex flex-wrap justify-center gap-1 border-t p-3">
                    <template v-for="(l, i) in expenses.links" :key="i">
                        <button v-if="l.url" type="button" class="rounded-md px-3 py-1 text-xs" :class="l.active ? 'bg-rose-600 font-bold text-white' : 'hover:bg-gray-100'" @click="router.get(l.url, {}, { preserveState: true, preserveScroll: true })" v-html="l.label" />
                    </template>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="isOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader><DialogTitle>{{ editingId ? 'Ubah Pengeluaran Pusat' : 'Catat Pengeluaran Pusat' }}</DialogTitle></DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label>Dibayar dari</Label>
                        <select v-model="form.source" :class="`${selectCls} w-full`">
                            <option value="pusat">Uang Bos / Kas Pusat (tidak potong saldo cabang)</option>
                            <option v-for="b in branches" :key="b.id" :value="String(b.id)">Kas {{ b.branch_name }}</option>
                        </select>
                        <InputError :message="(form.errors as Record<string, string>).branch_id" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Tanggal</Label>
                            <Input v-model="form.expense_date" type="date" :max="today" />
                            <InputError :message="form.errors.expense_date" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Kategori</Label>
                            <select v-model="form.category" :class="`${selectCls} w-full`"><option v-for="c in categories" :key="c" :value="c">{{ c }}</option></select>
                            <InputError :message="form.errors.category" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Nominal</Label>
                        <RupiahInput v-model="form.nominal" placeholder="0" />
                        <InputError :message="form.errors.nominal" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Keterangan <span class="text-gray-400">(opsional)</span></Label>
                        <Input v-model="form.description" placeholder="Gaji Oktober, beli mesin, dll" />
                        <InputError :message="form.errors.description" />
                    </div>
                    <ExpenseProofField v-model:files="form.proofs" v-model:removed="form.remove_proof_ids" :existing="existingProofs" />
                    <InputError :message="(form.errors as Record<string, string>)['proofs.0']" />
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="isOpen = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
