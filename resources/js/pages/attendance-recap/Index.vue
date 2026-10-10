<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ClipboardList } from '@lucide/vue';
import { Card, CardContent } from '@/components/ui/card';

interface Row {
    employee_id: number;
    full_name: string;
    nik: string;
    scheduled_days: number;
    off_days: number;
    on_time: number;
    late: number;
    alpha: number;
    leave_days: number;
    days: Array<{
        date: string;
        weekday: string;
        status: string;
        shift_name: string | null;
        start_time: string | null;
        end_time: string | null;
        clock_in_time: string | null;
        clock_out_time: string | null;
    }>;
}

const props = defineProps<{
    branches: Array<{ id: number; branch_name: string }>;
    branchId: number;
    month: string;
    monthLabel: string;
    recap: { rows: Row[]; totals: Record<string, number> } | null;
}>();

const go = (params: Record<string, string>) =>
    router.get('/attendance-recap', { branch_id: props.branchId, month: props.month, ...params }, { preserveState: true });

const statusMap: Record<string, { label: string; cls: string }> = {
    hadir_tepat_waktu: { label: 'Tepat Waktu', cls: 'bg-emerald-100 text-emerald-700' },
    terlambat: { label: 'Terlambat', cls: 'bg-amber-100 text-amber-700' },
    pulang_cepat: { label: 'Pulang Cepat', cls: 'bg-orange-100 text-orange-700' },
    alpha: { label: 'Alpha', cls: 'bg-red-100 text-red-700' },
    izin: { label: 'Izin', cls: 'bg-blue-100 text-blue-700' },
    sakit: { label: 'Sakit', cls: 'bg-purple-100 text-purple-700' },
    cuti_tahunan: { label: 'Cuti', cls: 'bg-sky-100 text-sky-700' },
    libur: { label: 'Libur', cls: 'bg-red-50 text-red-600' },
    belum: { label: 'Belum', cls: 'bg-gray-100 text-gray-500' },
};
const badge = (st: string) => statusMap[st] ?? { label: st, cls: 'bg-gray-100 text-gray-600' };
const fmtDate = (d: string) => d.split('-').reverse().join('/');

const cols: Array<{ key: keyof Row; label: string; cls?: string }> = [
    { key: 'scheduled_days', label: 'Hari Kerja' },
    { key: 'on_time', label: 'Tepat Waktu', cls: 'text-emerald-600' },
    { key: 'late', label: 'Terlambat', cls: 'text-amber-600' },
    { key: 'alpha', label: 'Alpha', cls: 'text-red-600' },
    { key: 'leave_days', label: 'Izin/Sakit/Cuti', cls: 'text-blue-600' },
    { key: 'off_days', label: 'Libur' },
];
</script>

<template>
    <Head title="Rekap Kehadiran - Momoyo Ops" />

    <div class="flex-1 space-y-5 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-black tracking-tight text-gray-900 dark:text-white">
                    <ClipboardList class="size-6 text-rose-500" /> Rekap Kehadiran
                </h1>
                <p class="text-sm text-gray-500">
                    {{ monthLabel }} · berdasarkan cabang saat jadwal/absensi dicatat. Alpha = hari kerja terjadwal yang sudah lewat tanpa absen.
                </p>
            </div>
            <div class="flex gap-2">
                <select
                    :value="branchId"
                    class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                    @change="go({ branch_id: ($event.target as HTMLSelectElement).value })"
                >
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.branch_name }}</option>
                </select>
                <input
                    type="month"
                    :value="month"
                    class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                    @change="go({ month: ($event.target as HTMLInputElement).value })"
                />
            </div>
        </div>

        <Card v-if="recap">
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50 text-xs dark:bg-zinc-900">
                            <th class="p-3 text-left">Karyawan</th>
                            <th v-for="c in cols" :key="c.key" class="p-3 text-center">{{ c.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in recap.rows" :key="r.employee_id" class="border-b">
                            <td class="p-3">
                                <div class="font-bold">{{ r.full_name }}</div>
                                <div class="text-xs text-gray-400">{{ r.nik }}</div>
                            </td>
                            <td v-for="c in cols" :key="c.key" class="p-3 text-center font-mono font-bold" :class="c.cls">
                                {{ r[c.key] }}
                            </td>
                        </tr>
                        <tr v-if="!recap.rows.length">
                            <td :colspan="cols.length + 1" class="p-8 text-center text-gray-400">Belum ada data.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="recap.rows.length">
                        <tr class="bg-gray-50 font-black dark:bg-zinc-900">
                            <td class="p-3">Total</td>
                            <td
                                v-for="c in cols"
                                :key="c.key"
                                class="p-3 text-center font-mono"
                                :class="c.cls"
                            >
                                {{ recap.totals[c.key] }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </CardContent>
        </Card>

        <!-- LAPORAN KEHADIRAN KARYAWAN (DETAIL PER TANGGAL) -->
        <div v-if="recap" class="space-y-3">
            <div>
                <h2 class="text-lg font-black text-gray-900 dark:text-white">Laporan Kehadiran Karyawan</h2>
                <p class="text-sm text-gray-500">Detail kehadiran per karyawan beserta tanggal absensinya. Klik nama untuk membuka.</p>
            </div>
            <Card v-for="r in recap.rows" :key="r.employee_id">
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4">
                        <div>
                            <div class="font-bold">{{ r.full_name }}</div>
                            <div class="text-xs text-gray-400">{{ r.nik }}</div>
                        </div>
                        <div class="flex gap-3 text-xs font-bold">
                            <span class="text-emerald-600">{{ r.on_time }} tepat</span>
                            <span class="text-amber-600">{{ r.late }} telat</span>
                            <span class="text-red-600">{{ r.alpha }} alpha</span>
                            <span class="text-blue-600">{{ r.leave_days }} izin/cuti</span>
                            <span class="text-gray-500">{{ r.off_days }} libur</span>
                        </div>
                    </summary>
                    <CardContent class="overflow-x-auto border-t p-0">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-zinc-900">
                                    <th class="p-2.5 text-left">Tanggal</th>
                                    <th class="p-2.5 text-left">Shift</th>
                                    <th class="p-2.5 text-center">Masuk</th>
                                    <th class="p-2.5 text-center">Pulang</th>
                                    <th class="p-2.5 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="d in r.days" :key="d.date" class="border-t" :class="d.status === 'libur' ? 'bg-red-50/60' : ''">
                                    <td class="p-2.5" :class="d.status === 'libur' ? 'font-bold text-red-600' : ''">
                                        {{ d.weekday }}, {{ fmtDate(d.date) }}
                                    </td>
                                    <td class="p-2.5 text-gray-600">
                                        <template v-if="d.shift_name">
                                            {{ d.shift_name }}
                                            <span class="font-mono text-gray-400">{{ d.start_time }}–{{ d.end_time }}</span>
                                        </template>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="p-2.5 text-center font-mono">{{ d.clock_in_time || '-' }}</td>
                                    <td class="p-2.5 text-center font-mono">{{ d.clock_out_time || '-' }}</td>
                                    <td class="p-2.5 text-center">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="badge(d.status).cls">
                                            {{ badge(d.status).label }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!r.days.length">
                                    <td colspan="5" class="p-6 text-center text-gray-400">Belum ada jadwal/absensi di bulan ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </details>
            </Card>
        </div>
    </div>
</template>
