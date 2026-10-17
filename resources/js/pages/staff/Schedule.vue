<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';

interface Day {
    date: string;
    day: number;
    weekday: string;
    is_today: boolean;
    is_past: boolean;
    is_off: boolean;
    is_scheduled: boolean;
    leave_label?: string | null;
    shift_name: string | null;
    start_time: string | null;
    end_time: string | null;
    attendance: { status: string; clock_in_time: string | null; clock_out_time: string | null } | null;
}

defineProps<{ month: string; monthLabel: string; prevMonth: string; nextMonth: string; period: Day[] }>();

const statusLabel: Record<string, { label: string; cls: string }> = {
    hadir_tepat_waktu: { label: 'Tepat Waktu', cls: 'bg-emerald-100 text-emerald-700' },
    terlambat: { label: 'Terlambat', cls: 'bg-amber-100 text-amber-700' },
    pulang_cepat: { label: 'Pulang Cepat', cls: 'bg-orange-100 text-orange-700' },
};
</script>

<template>
    <Head title="Jadwal Shift" />

    <div>
        <header class="rounded-b-[2rem] bg-gradient-to-b from-red-600 via-rose-600 to-amber-600 px-5 pt-6 pb-8 text-white">
            <h1 class="text-xl font-black">Jadwal Shift</h1>
            <div class="mt-3 flex items-center justify-between rounded-2xl bg-white/10 px-2 py-1.5">
                <Link :href="`/my-schedule?month=${prevMonth}`" class="rounded-xl p-2 hover:bg-white/10"><ChevronLeft class="size-5" /></Link>
                <span class="font-bold">{{ monthLabel }}</span>
                <Link :href="`/my-schedule?month=${nextMonth}`" class="rounded-xl p-2 hover:bg-white/10"><ChevronRight class="size-5" /></Link>
            </div>
        </header>

        <div class="-mt-3 px-4">
            <div class="divide-y overflow-hidden rounded-3xl bg-white shadow-sm dark:divide-zinc-800 dark:bg-zinc-900">
                <div
                    v-for="d in period"
                    :key="d.date"
                    class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                    :class="[d.is_today ? 'bg-rose-50 dark:bg-rose-950/20' : '', d.is_off ? 'bg-red-50/60 dark:bg-red-950/20' : '']"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-10 text-center" :class="d.is_off ? 'text-red-600' : 'text-gray-900 dark:text-white'">
                            <div class="text-lg leading-none font-black">{{ d.day }}</div>
                            <div class="text-[10px] font-medium uppercase">{{ d.weekday.slice(0, 3) }}</div>
                        </div>
                        <div class="min-w-0">
                            <div v-if="d.leave_label" class="font-bold text-blue-600">{{ d.leave_label }}</div>
                            <div v-else-if="d.is_off" class="font-bold text-red-600">Libur</div>
                            <template v-else-if="d.is_scheduled">
                                <div class="font-bold">{{ d.shift_name }}</div>
                                <div class="font-mono text-xs text-gray-500">{{ d.start_time }} – {{ d.end_time }}</div>
                            </template>
                            <div v-else class="text-xs text-gray-400">Belum dijadwalkan</div>
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <template v-if="d.attendance">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="statusLabel[d.attendance.status]?.cls ?? 'bg-gray-100 text-gray-600'">{{ statusLabel[d.attendance.status]?.label ?? d.attendance.status }}</span>
                            <div class="mt-0.5 font-mono text-[11px] text-gray-500">{{ d.attendance.clock_in_time ?? '-' }} → {{ d.attendance.clock_out_time ?? '-' }}</div>
                        </template>
                        <span v-else-if="d.is_past && d.is_scheduled && !d.is_off && !d.leave_label" class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">Tidak Hadir</span>
                        <span v-else-if="d.is_today" class="text-xs font-bold text-rose-700">Hari ini</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
