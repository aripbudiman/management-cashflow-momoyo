<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { fmtDate } from '@/lib/format';
import { formatQty } from '@/lib/units';
import type { QtyMode, UnitOption } from '@/lib/units';

interface Movement {
    id: number;
    type: string;
    qty: string;
    moved_at: string;
    note: string | null;
    user: { name: string } | null;
}

const props = defineProps<{
    branches: Array<{ id: number; branch_name: string }>;
    items: Array<{ id: number; name: string; unit: string; unit_options: UnitOption[]; default_unit_id: number | null }>;
    filters: { branch_id: number; item_id: number | null; from: string; to: string };
    types: Record<string, string>;
    opening: number;
    movements: Movement[];
}>();

const selected = computed(() => props.items.find((i) => i.id === props.filters.item_id) ?? null);
const mode = ref<string>('default');

const qtyMode = computed<QtyMode>(() => {
    if (mode.value === 'mixed' || mode.value === 'base') return mode.value;
    if (mode.value === 'default') return selected.value?.default_unit_id ?? 'base';
    return Number(mode.value);
});

const show = (base: number | string, signed = false): string => {
    if (!selected.value) return String(base);
    const text = formatQty(Number(base), selected.value.unit_options, qtyMode.value);
    return signed && Number(base) > 0 ? `+${text}` : text;
};

const rows = computed(() => {
    let balance = Number(props.opening);
    return props.movements.map((m) => {
        balance = Math.round((balance + Number(m.qty)) * 100) / 100;
        return { ...m, balance };
    });
});

const go = (k: string, v: string) => {
    if (k === 'item_id') mode.value = 'default';
    router.get('/stock-monitor/card', { ...props.filters, [k]: v || undefined }, { preserveState: true });
};
const selectCls = 'h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';
</script>

<template>
    <Head title="Kartu Stok - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Kartu Stok</h1>
                <p class="text-sm text-gray-500">Riwayat masuk, keluar, dan saldo per barang.</p>
            </div>
            <Button as-child variant="outline"><Link href="/stock-monitor">Kembali ke Pantauan</Link></Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <select :value="filters.branch_id" :class="selectCls" @change="go('branch_id', ($event.target as HTMLSelectElement).value)">
                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.branch_name }}</option>
            </select>
            <select :value="filters.item_id ?? ''" :class="selectCls" @change="go('item_id', ($event.target as HTMLSelectElement).value)">
                <option value="">Pilih barang...</option>
                <option v-for="i in items" :key="i.id" :value="i.id">{{ i.name }}</option>
            </select>
            <select v-if="selected" v-model="mode" :class="selectCls">
                <option value="default">Satuan default</option>
                <option value="mixed">Campuran</option>
                <option v-for="u in [...selected.unit_options].reverse()" :key="u.unit_id" :value="u.unit_id">{{ u.name }}</option>
            </select>
            <input type="date" :value="filters.from" :class="selectCls" @change="go('from', ($event.target as HTMLInputElement).value)" />
            <input type="date" :value="filters.to" :class="selectCls" @change="go('to', ($event.target as HTMLInputElement).value)" />
        </div>

        <Card v-if="filters.item_id">
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-zinc-900">
                        <tr>
                            <th class="p-3 text-left">Tanggal</th>
                            <th class="p-3 text-left">Jenis</th>
                            <th class="p-3 text-right">Qty</th>
                            <th class="p-3 text-right">Saldo</th>
                            <th class="p-3 text-left">Oleh / Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr class="bg-gray-50/60 dark:bg-zinc-900/60">
                            <td class="p-3" colspan="3">Saldo awal {{ fmtDate(filters.from) }}</td>
                            <td class="p-3 text-right text-xs font-bold">{{ show(opening) }}</td>
                            <td />
                        </tr>
                        <tr v-for="m in rows" :key="m.id">
                            <td class="p-3">{{ fmtDate(m.moved_at) }}</td>
                            <td class="p-3">{{ types[m.type] ?? m.type }}</td>
                            <td class="p-3 text-right text-xs font-bold" :class="Number(m.qty) > 0 ? 'text-emerald-600' : 'text-red-600'">
                                {{ show(m.qty, true) }}
                            </td>
                            <td class="p-3 text-right text-xs">{{ show(m.balance) }}</td>
                            <td class="p-3 text-xs text-gray-500">{{ m.user?.name }}<span v-if="m.note"> · {{ m.note }}</span></td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="rows.length === 0" class="p-8 text-center text-sm text-gray-400">Tidak ada mutasi pada periode ini.</div>
            </CardContent>
        </Card>
        <p v-else class="text-sm text-gray-400">Pilih barang untuk melihat kartu stok.</p>
    </div>
</template>
