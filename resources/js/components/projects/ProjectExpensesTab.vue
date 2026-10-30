<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Edit3, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ExpenseProofField from '@/components/ExpenseProofField.vue';
import InputError from '@/components/InputError.vue';
import ProofThumbs from '@/components/ProofThumbs.vue';
import RupiahInput from '@/components/RupiahInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fmtDate, rupiah } from '@/lib/format';
import type { Project } from '@/lib/project';

export interface ProjectExpense {
    id: number;
    expense_date: string;
    category: string;
    description: string;
    nominal: number;
    creator?: { name: string } | null;
    proofs?: Array<{ id: number; url: string }>;
}
export interface ProfitSummary {
    nilai_kontrak: number;
    total_expense: number;
    net_profit: number;
    margin: number;
    by_category: Record<string, number>;
}

const props = defineProps<{ project: Project; expenses: ProjectExpense[]; profit: ProfitSummary; categories: string[] }>();

const selectCls = 'h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';
const today = new Date().toLocaleDateString('en-CA');

const isOpen = ref(false);
const editingId = ref<number | null>(null);
const form = useForm<{ expense_date: string; category: string; description: string; nominal: number | ''; proofs: File[]; remove_proof_ids: number[] }>({
    expense_date: today,
    category: 'Material',
    description: '',
    nominal: '',
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
const openEdit = (e: ProjectExpense) => {
    editingId.value = e.id;
    form.clearErrors();
    form.expense_date = e.expense_date.slice(0, 10);
    form.category = e.category;
    form.description = e.description;
    form.nominal = Number(e.nominal);
    form.proofs = [];
    form.remove_proof_ids = [];
    existingProofs.value = e.proofs ?? [];
    isOpen.value = true;
};
const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => (isOpen.value = false) };
    // Upload file butuh multipart; update dikirim sebagai POST + _method=PUT
    const multipart = { ...opts, forceFormData: true };
    if (editingId.value) form.transform((d) => ({ ...d, _method: 'put' })).post(`/project-expenses/${editingId.value}`, multipart);
    else form.post(`/projects/${props.project.id}/expenses`, multipart);
};
const remove = (e: ProjectExpense) => {
    if (confirm(`Hapus pengeluaran "${e.description}" (${rupiah(e.nominal)})?`)) router.delete(`/project-expenses/${e.id}`, { preserveScroll: true });
};
</script>

<template>
    <div class="space-y-4">
        <!-- Ringkasan laba: nilai kontrak/invoice tidak berubah, hanya dipakai menghitung keuntungan bersih -->
        <Card>
            <CardContent class="space-y-2 p-5 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">Nilai Kontrak (dasar invoice)</span><b class="font-mono">{{ rupiah(profit.nilai_kontrak) }}</b></div>
                <div class="flex justify-between"><span class="text-gray-500">Total Pengeluaran</span><b class="font-mono text-red-600">− {{ rupiah(profit.total_expense) }}</b></div>
                <div class="flex items-center justify-between border-t pt-2">
                    <span class="font-semibold">Keuntungan Bersih</span>
                    <span class="text-right">
                        <b class="font-mono text-xl" :class="profit.net_profit < 0 ? 'text-red-600' : 'text-emerald-600'">{{ rupiah(profit.net_profit) }}</b>
                        <span class="block text-[11px] text-gray-500">Margin {{ profit.margin }}%</span>
                    </span>
                </div>
                <p class="text-[11px] text-gray-400">Pengeluaran hanya untuk menghitung keuntungan. Nilai kontrak dan invoice tidak berubah.</p>
                <div v-if="Object.keys(profit.by_category).length" class="flex flex-wrap gap-1.5 border-t pt-2">
                    <Badge v-for="(v, k) in profit.by_category" :key="k" variant="outline" class="text-[11px]">{{ k }}: {{ rupiah(v) }}</Badge>
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end"><Button class="gap-1.5" @click="openCreate"><Plus class="size-4" /> Catat Pengeluaran</Button></div>

        <Card>
            <CardContent class="p-0">
                <div v-if="!expenses.length" class="p-8 text-center text-sm text-gray-400">Belum ada pengeluaran proyek.</div>
                <div v-else class="divide-y">
                    <div v-for="e in expenses" :key="e.id" class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate font-bold">{{ e.description }}</span>
                                <Badge variant="outline" class="text-[10px]">{{ e.category }}</Badge>
                            </div>
                            <div class="text-xs text-gray-500">{{ fmtDate(e.expense_date) }}<template v-if="e.creator"> · {{ e.creator.name }}</template></div>
                            <ProofThumbs :proofs="e.proofs" />
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <b class="mr-2 font-mono text-red-600">{{ rupiah(e.nominal) }}</b>
                            <Button size="icon" variant="ghost" class="size-8" @click="openEdit(e)"><Edit3 class="size-4" /></Button>
                            <Button size="icon" variant="ghost" class="size-8 text-rose-500" @click="remove(e)"><Trash2 class="size-4" /></Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="isOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader><DialogTitle>{{ editingId ? 'Ubah Pengeluaran' : 'Catat Pengeluaran Proyek' }}</DialogTitle></DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Tanggal</Label>
                            <Input v-model="form.expense_date" type="date" :max="today" />
                            <InputError :message="form.errors.expense_date" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Kategori</Label>
                            <select v-model="form.category" :class="selectCls"><option v-for="c in categories" :key="c" :value="c">{{ c }}</option></select>
                            <InputError :message="form.errors.category" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Keterangan</Label>
                        <Input v-model="form.description" placeholder="Semen 20 sak, upah tukang minggu 1, dll" />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Nominal</Label>
                        <RupiahInput v-model="form.nominal" placeholder="0" />
                        <InputError :message="form.errors.nominal" />
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
