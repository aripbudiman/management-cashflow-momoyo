<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3 } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { fmtDate, rupiah } from '@/lib/format';

interface Totals {
    wallets: Record<string, number>;
    total_omset: number;
    total_expense: number;
    total_setor: number;
}
interface DailyRow extends Totals {
    date: string;
    branch_name: string | null;
    closing_by: string | null;
    status: string;
}
interface MonthlyRow extends Totals {
    branch_id: number;
    branch_name: string;
    days: number;
    verified_days: number;
}

const props = defineProps<{
    mode: 'daily' | 'monthly';
    month: string;
    monthLabel: string;
    branchId: number;
    branches: Array<{ id: number; branch_name: string }>;
    wallets: Record<string, string>;
    report: { rows: Array<DailyRow | MonthlyRow>; totals: Totals };
}>();

const go = (params: Record<string, string | number>) =>
    router.get('/cashflow-reports', { mode: props.mode, month: props.month, branch_id: props.branchId, ...params }, { preserveState: true });

const statusMap: Record<string, { label: string; cls: string }> = {
    submitted: { label: 'Menunggu', cls: 'bg-amber-100 text-amber-700' },
    verified_by_finance: { label: 'Terverifikasi', cls: 'bg-emerald-100 text-emerald-700' },
    revision_needed: { label: 'Revisi', cls: 'bg-red-100 text-red-700' },
};
const selectCls = 'h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';
const n = (v: number) => (v ? new Intl.NumberFormat('id-ID').format(v) : '-');
</script>

<template>
    <Head title="Laporan Cashflow - Momoyo Ops" />

    <div class="flex-1 space-y-5 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-black tracking-tight text-gray-900 dark:text-white">
                    <BarChart3 class="size-6 text-rose-500" /> Laporan Cashflow
                </h1>
                <p class="text-sm text-gray-500">{{ monthLabel }} · total setor = tunai di kasir − pengeluaran harian</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex overflow-hidden rounded-md border text-sm">
                    <button class="px-3 py-1.5" :class="mode === 'daily' ? 'bg-rose-600 font-bold text-white' : ''" @click="go({ mode: 'daily' })">Harian</button>
                    <button class="px-3 py-1.5" :class="mode === 'monthly' ? 'bg-rose-600 font-bold text-white' : ''" @click="go({ mode: 'monthly' })">Bulanan</button>
                </div>
                <select v-if="mode === 'daily'" :value="branchId" :class="selectCls" @change="go({ branch_id: ($event.target as HTMLSelectElement).value })">
                    <option :value="0">Semua Cabang</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.branch_name }}</option>
                </select>
                <input type="month" :value="month" :class="selectCls" @change="go({ month: ($event.target as HTMLInputElement).value })" />
            </div>
        </div>

        <Card class="overflow-hidden">
            <CardHeader class="border-b p-4">
                <CardTitle class="text-base font-bold">{{ mode === 'daily' ? 'Cashflow Harian' + (branchId ? '' : ' · Semua Cabang') : 'Rekap Bulanan Semua Cabang' }}</CardTitle>
                <CardDescription class="text-xs">Nominal dalam Rupiah</CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-left text-xs">
                    <thead class="border-b bg-gray-50 text-gray-500 dark:bg-zinc-900/60">
                        <tr>
                            <th v-if="mode === 'daily'" class="px-3 py-3 font-bold">Tanggal</th>
                            <th v-if="mode === 'daily'" class="px-3 py-3 font-bold">Cabang</th>
                            <th v-if="mode === 'daily'" class="px-3 py-3 font-bold">Kasir</th>
                            <th v-else class="px-3 py-3 font-bold">Cabang</th>
                            <th v-if="mode === 'monthly'" class="px-3 py-3 text-center font-bold">Hari (Verif.)</th>
                            <th v-for="(label, k) in wallets" :key="k" class="px-3 py-3 text-right font-bold">{{ label }}</th>
                            <th class="px-3 py-3 text-right font-bold">Total Omset</th>
                            <th class="px-3 py-3 text-right font-bold">Pengeluaran</th>
                            <th class="px-3 py-3 text-right font-bold">Total Setor</th>
                            <th v-if="mode === 'daily'" class="px-3 py-3 font-bold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(r, i) in report.rows" :key="i" class="hover:bg-gray-50/50 dark:hover:bg-zinc-800/30">
                            <template v-if="mode === 'daily'">
                                <td class="px-3 py-2.5 font-bold">{{ fmtDate((r as DailyRow).date) }}</td>
                                <td class="px-3 py-2.5">{{ (r as DailyRow).branch_name ?? '-' }}</td>
                                <td class="px-3 py-2.5">{{ (r as DailyRow).closing_by ?? '-' }}</td>
                            </template>
                            <template v-else>
                                <td class="px-3 py-2.5 font-bold">{{ (r as MonthlyRow).branch_name }}</td>
                                <td class="px-3 py-2.5 text-center">{{ (r as MonthlyRow).days }} ({{ (r as MonthlyRow).verified_days }})</td>
                            </template>
                            <td v-for="(label, k) in wallets" :key="k" class="px-3 py-2.5 text-right font-mono">{{ n(r.wallets[k]) }}</td>
                            <td class="px-3 py-2.5 text-right font-mono font-bold">{{ n(r.total_omset) }}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-red-600">{{ n(r.total_expense) }}</td>
                            <td class="px-3 py-2.5 text-right font-mono font-bold text-emerald-700">{{ n(r.total_setor) }}</td>
                            <td v-if="mode === 'daily'" class="px-3 py-2.5">
                                <Badge variant="outline" class="border text-[10px] font-bold" :class="statusMap[(r as DailyRow).status]?.cls">{{ statusMap[(r as DailyRow).status]?.label }}</Badge>
                            </td>
                        </tr>
                        <tr v-if="!report.rows.length">
                            <td :colspan="wallets ? Object.keys(wallets).length + 6 : 6" class="py-12 text-center text-gray-400">Belum ada data pada periode ini.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="report.rows.length">
                        <tr class="border-t-2 bg-gray-50 font-black dark:bg-zinc-900">
                            <td :colspan="mode === 'daily' ? 3 : 2" class="px-3 py-3">TOTAL</td>
                            <td v-for="(label, k) in wallets" :key="k" class="px-3 py-3 text-right font-mono">{{ n(report.totals.wallets[k]) }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ n(report.totals.total_omset) }}</td>
                            <td class="px-3 py-3 text-right font-mono text-red-600">{{ n(report.totals.total_expense) }}</td>
                            <td class="px-3 py-3 text-right font-mono text-emerald-700">{{ n(report.totals.total_setor) }}</td>
                            <td v-if="mode === 'daily'" />
                        </tr>
                    </tfoot>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
