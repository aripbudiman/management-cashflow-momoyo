<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Boxes } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface Row {
    id: number;
    name: string;
    unit: string;
    price: string;
    min_stock: string;
    stocks: Record<string, string>;
}

const props = defineProps<{
    branches: Array<{ id: number; branch_name: string }>;
    items: Row[];
    filters: { search?: string };
}>();

const search = ref(props.filters.search ?? '');
const qty = (r: Row, branchId: number): number => Number(r.stocks[branchId] ?? 0);
const isLow = (r: Row, branchId: number): boolean => qty(r, branchId) <= Number(r.min_stock);
const lowCount = (): number => props.items.reduce((n, r) => n + props.branches.filter((b) => isLow(r, b.id)).length, 0);
</script>

<template>
    <Head title="Pantauan Stok - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Pantauan Stok</h1>
                <p class="text-sm text-gray-500">Stok terkini per cabang. Merah = di bawah atau sama dengan stok minimum.</p>
            </div>
            <div class="flex items-center gap-2">
                <Input v-model="search" placeholder="Cari barang..." class="h-9 w-44" @keyup.enter="router.get('/stock-monitor', { search: search || undefined }, { preserveState: true })" />
                <Button as-child variant="outline"><Link href="/stock-monitor/card">Kartu Stok</Link></Button>
            </div>
        </div>

        <div v-if="lowCount() > 0" class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
            <AlertTriangle class="size-4" /> {{ lowCount() }} posisi stok berada di batas minimum.
        </div>

        <Card>
            <CardHeader class="border-b pb-3">
                <CardTitle class="flex items-center gap-2 text-sm font-bold"><Boxes class="size-4 text-rose-500" /> {{ items.length }} Barang</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-zinc-900">
                        <tr>
                            <th class="p-3 text-left">Barang</th>
                            <th class="p-3 text-right">Min</th>
                            <th v-for="b in branches" :key="b.id" class="p-3 text-right">{{ b.branch_name }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="r in items" :key="r.id">
                            <td class="p-3 font-medium">{{ r.name }} <span class="text-xs text-gray-400">({{ r.unit }})</span></td>
                            <td class="p-3 text-right font-mono text-gray-400">{{ Number(r.min_stock) }}</td>
                            <td v-for="b in branches" :key="b.id" class="p-3 text-right font-mono" :class="isLow(r, b.id) ? 'font-bold text-red-600' : ''">
                                <Link :href="`/stock-monitor/card?branch_id=${b.id}&item_id=${r.id}`" class="hover:underline">{{ qty(r, b.id) }}</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="items.length === 0" class="p-8 text-center text-sm text-gray-400">Belum ada barang.</div>
            </CardContent>
        </Card>
    </div>
</template>
