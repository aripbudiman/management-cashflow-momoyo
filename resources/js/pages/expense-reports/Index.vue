<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Receipt } from '@lucide/vue';
import { ref } from 'vue';
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { fmtDate, rupiah } from '@/lib/format';

interface ExpenseRow {
    id: number;
    description: string;
    nominal: number;
    branch_name: string | null;
    photos: Array<{ id: number; url: string }>;
}

const props = defineProps<{
    branches: Array<{ id: number; branch_name: string }>;
    branchId: number;
    month: string;
    monthLabel: string;
    report: {
        days: Array<{ date: string; weekday: string; rows: ExpenseRow[]; total: number }>;
        grand_total: number;
        count: number;
    };
}>();

const go = (params: Record<string, string | number>) =>
    router.get('/expense-reports', { month: props.month, branch_id: props.branchId, ...params }, { preserveState: true });

const preview = ref<{ url: string; name: string } | null>(null);

const selectCls = 'h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';
</script>

<template>
    <Head title="Laporan Pengeluaran - Momoyo Ops" />

    <div class="flex-1 space-y-5 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-black tracking-tight text-gray-900 dark:text-white">
                    <Receipt class="size-6 text-rose-500" /> Laporan Pengeluaran Kas
                </h1>
                <p class="text-sm text-gray-500">{{ monthLabel }} · rincian pengeluaran harian dari closing cabang</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select :value="branchId" :class="selectCls" @change="go({ branch_id: ($event.target as HTMLSelectElement).value })">
                    <option :value="0">Semua Cabang</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.branch_name }}</option>
                </select>
                <input type="month" :value="month" :class="selectCls" @change="go({ month: ($event.target as HTMLInputElement).value })" />
            </div>
        </div>

        <Card class="overflow-hidden">
            <CardHeader class="border-b p-4">
                <CardTitle class="text-base font-bold">Pengeluaran {{ branchId ? '' : '· Semua Cabang' }}</CardTitle>
                <CardDescription class="text-xs">{{ report.count }} item pengeluaran</CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="border-b bg-gray-50 text-gray-500 dark:bg-zinc-900/60">
                        <tr>
                            <th class="w-28 px-3 py-3 text-center font-bold">Tanggal</th>
                            <th v-if="!branchId" class="px-3 py-3 font-bold">Cabang</th>
                            <th class="px-3 py-3 font-bold">Keterangan</th>
                            <th class="px-3 py-3 font-bold">Bukti</th>
                            <th class="px-3 py-3 text-right font-bold">Jumlah</th>
                            <th class="w-40 px-3 py-3 text-right font-bold">Total Harian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="d in report.days" :key="d.date">
                            <tr v-for="(r, i) in d.rows" :key="r.id" class="border-b" :class="i === d.rows.length - 1 ? 'border-b-2 border-gray-300 dark:border-zinc-600' : 'border-gray-100 dark:border-zinc-800'">
                                <td v-if="i === 0" :rowspan="d.rows.length" class="border-r bg-gray-50/60 px-3 py-2.5 text-center align-middle dark:bg-zinc-900/40">
                                    <div class="text-base font-black">{{ fmtDate(d.date).slice(0, 2) }}</div>
                                    <div class="text-[10px] text-gray-500">{{ d.weekday }}</div>
                                </td>
                                <td v-if="!branchId" class="px-3 py-2.5 text-gray-600">{{ r.branch_name ?? '-' }}</td>
                                <td class="px-3 py-2.5 font-medium">{{ r.description }}</td>
                                <td class="px-3 py-2.5">
                                    <div v-if="r.photos.length" class="flex gap-1">
                                        <button v-for="p in r.photos" :key="p.id" type="button" class="size-8 overflow-hidden rounded-md border hover:ring-2 hover:ring-rose-500" title="Preview" @click="preview = { url: p.url, name: `${r.description} · ${fmtDate(d.date)}` }">
                                            <img :src="p.url" alt="Bukti" class="size-full object-cover" />
                                        </button>
                                    </div>
                                    <span v-else class="text-gray-300">-</span>
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono">{{ rupiah(r.nominal) }}</td>
                                <td v-if="i === 0" :rowspan="d.rows.length" class="border-l bg-gray-50/60 px-3 py-2.5 text-right align-middle font-mono text-sm font-black dark:bg-zinc-900/40">
                                    {{ rupiah(d.total) }}
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!report.days.length">
                            <td :colspan="branchId ? 5 : 6" class="py-12 text-center text-gray-400">
                                <Receipt class="mx-auto mb-2 size-10 text-gray-300" />
                                <p class="text-sm font-bold">Tidak ada pengeluaran pada periode ini</p>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="report.days.length">
                        <tr class="bg-gray-100 font-black dark:bg-zinc-900">
                            <td :colspan="branchId ? 4 : 5" class="px-3 py-3 text-right">JUMLAH TOTAL</td>
                            <td class="px-3 py-3 text-right font-mono text-sm text-rose-600">{{ rupiah(report.grand_total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </CardContent>
        </Card>

        <ImagePreviewDialog v-model="preview" />
    </div>
</template>
