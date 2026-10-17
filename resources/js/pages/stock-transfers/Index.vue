<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowRight, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMixed } from '@/lib/units';
import type { UnitOption } from '@/lib/units';

interface Transfer {
    id: number;
    qty: string;
    status: string;
    sent_at: string;
    note: string | null;
    from_branch_id: number;
    to_branch_id: number;
    from_branch: { branch_name: string };
    to_branch: { branch_name: string };
    item: { name: string; unit: string; unit_options: UnitOption[] };
    sent_by: { name: string } | null;
    received_by: { name: string } | null;
}

const props = defineProps<{
    branchId: number | null;
    canSend: boolean;
    transfers: Transfer[];
    branches: Array<{ id: number; branch_name: string }>;
    items: Array<{ id: number; name: string; unit: string; unit_options: UnitOption[]; default_out_unit_id: number | null }>;
}>();

const statusMap: Record<string, { label: string; cls: string }> = {
    sent: { label: 'Dalam Pengiriman', cls: 'bg-amber-100 text-amber-700' },
    received: { label: 'Diterima', cls: 'bg-emerald-100 text-emerald-700' },
    cancelled: { label: 'Dibatalkan', cls: 'bg-gray-100 text-gray-600' },
};

const isOpen = ref(false);
const form = useForm({
    to_branch_id: '' as number | '',
    item_id: '' as number | '',
    qty: '' as number | '',
    unit_id: '' as number | '',
    note: '',
});

const selectedItem = computed(() => props.items.find((i) => i.id === form.item_id) ?? null);
watch(() => form.item_id, () => (form.unit_id = selectedItem.value?.default_out_unit_id ?? ''));

const qtyText = (t: Transfer): string => formatMixed(Number(t.qty), t.item.unit_options);
const selectCls = 'h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';

const submit = () =>
    form.post('/stock-transfers', {
        preserveScroll: true,
        onSuccess: () => {
            isOpen.value = false;
            form.reset();
        },
    });

const receive = (t: Transfer) => confirm(`Terima ${qtyText(t)} ${t.item.name}?`) && router.post(`/stock-transfers/${t.id}/receive`, {}, { preserveScroll: true });
const cancel = (t: Transfer) => confirm('Batalkan transfer? Stok dikembalikan ke cabang asal.') && router.post(`/stock-transfers/${t.id}/cancel`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Transfer Stok - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Transfer Antar Cabang</h1>
                <p class="text-sm text-gray-500">Stok asal berkurang saat dikirim, stok tujuan bertambah saat dikonfirmasi.</p>
            </div>
            <Button v-if="canSend" class="gap-1.5" @click="isOpen = true"><Plus class="size-4" /> Kirim Barang</Button>
        </div>

        <Card>
            <CardContent class="p-0">
                <div v-if="transfers.length === 0" class="p-8 text-center text-sm text-gray-400">Belum ada transfer.</div>
                <div v-else class="divide-y">
                    <div v-for="t in transfers" :key="t.id" class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2 font-bold text-gray-900 dark:text-white">
                                {{ t.from_branch.branch_name }} <ArrowRight class="size-4 text-gray-400" /> {{ t.to_branch.branch_name }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ qtyText(t) }} {{ t.item.name }} · {{ t.sent_at.slice(0, 10).split('-').reverse().join('/') }}
                                · oleh {{ t.sent_by?.name }}<span v-if="t.note"> · {{ t.note }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge :class="statusMap[t.status]?.cls">{{ statusMap[t.status]?.label }}</Badge>
                            <template v-if="t.status === 'sent'">
                                <Button v-if="canSend && t.to_branch_id === branchId" size="sm" @click="receive(t)">Terima</Button>
                                <Button v-if="!canSend || t.from_branch_id === branchId" size="sm" variant="outline" class="text-red-600" @click="cancel(t)">Batalkan</Button>
                            </template>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="isOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader><DialogTitle>Kirim Barang ke Cabang Lain</DialogTitle></DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label>Cabang Tujuan</Label>
                        <select v-model="form.to_branch_id" :class="selectCls">
                            <option value="">Pilih cabang...</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.branch_name }}</option>
                        </select>
                        <InputError :message="form.errors.to_branch_id" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Barang</Label>
                        <select v-model="form.item_id" :class="selectCls">
                            <option value="">Pilih barang...</option>
                            <option v-for="i in items" :key="i.id" :value="i.id">{{ i.name }}</option>
                        </select>
                        <InputError :message="form.errors.item_id" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Jumlah</Label>
                        <div class="flex gap-2">
                            <Input v-model="form.qty" type="number" min="0" step="0.01" />
                            <select v-model="form.unit_id" :class="selectCls" class="w-32" :disabled="!selectedItem">
                                <option v-for="u in [...(selectedItem?.unit_options ?? [])].reverse()" :key="u.unit_id" :value="u.unit_id">{{ u.name }}</option>
                            </select>
                        </div>
                        <InputError :message="form.errors.qty" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Catatan (opsional)</Label>
                        <Input v-model="form.note" />
                    </div>
                    <DialogFooter><Button type="submit" :disabled="form.processing">Kirim</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
