<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Camera, CheckCircle2, ChevronRight, CalendarCheck, Package } from '@lucide/vue';
import { computed } from 'vue';
import { adminMenuGroups } from '@/lib/adminMenus';
import { rupiah } from '@/lib/format';

const props = defineProps<{
    name: string;
    dateLabel: string;
    metrics: {
        total_omset_today: number;
        total_expense_today: number;
        closed_branches_count: number;
        total_branches: number;
        pending_cashflows_count?: number;
        pending_leaves_count?: number;
        pending_stock_reports_count?: number;
    };
    employee?: { full_name: string; face_photo_url: string | null; is_face_registered: boolean } | null;
    attendance?: { clock_in_time: string | null; clock_out_time: string | null } | null;
}>();

const emit = defineEmits<{ (e: 'punch', mode: 'clock_in' | 'clock_out'): void; (e: 'register-face'): void }>();

const laba = computed(() => Number(props.metrics.total_omset_today || 0) - Number(props.metrics.total_expense_today || 0));
const initials = computed(() => props.name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase());

const clockedIn = computed(() => !!props.attendance?.clock_in_time);
const clockedOut = computed(() => !!props.attendance?.clock_out_time);

const tasks = computed(() => [
    { href: '/cashflow-verifications?status=submitted', label: 'Setoran menunggu verifikasi', count: props.metrics.pending_cashflows_count ?? 0, icon: CheckCircle2 },
    { href: '/leaves?status=pending', label: 'Pengajuan izin/cuti', count: props.metrics.pending_leaves_count ?? 0, icon: CalendarCheck },
    { href: '/stock-verifications?status=submitted', label: 'Laporan stok menunggu', count: props.metrics.pending_stock_reports_count ?? 0, icon: Package },
]);
</script>

<template>
    <div>
        <header class="rounded-b-[2.5rem] bg-gradient-to-br from-red-600 via-rose-600 to-amber-600 px-5 pt-6 pb-24 text-white shadow-lg">
            <div class="flex items-center gap-3">
                <div class="size-14 shrink-0 overflow-hidden rounded-full border-2 border-white/50 bg-white/20">
                    <img v-if="employee?.face_photo_url" :src="employee.face_photo_url" :alt="name" class="size-full object-cover" />
                    <div v-else class="flex size-full items-center justify-center text-lg font-black">{{ initials }}</div>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-bold tracking-[0.2em] text-rose-100 uppercase">Selamat Datang</div>
                    <div class="truncate text-xl font-black">{{ name }}</div>
                    <div class="text-sm text-rose-100">Admin Pusat · {{ dateLabel }}</div>
                </div>
            </div>
        </header>

        <div class="-mt-20 space-y-5 px-4">
            <!-- RINGKASAN HARI INI -->
            <section class="rounded-3xl bg-white p-5 shadow-xl dark:bg-zinc-900">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-xs font-black tracking-wider text-gray-400 uppercase">Ringkasan Hari Ini</h2>
                    <span class="text-[11px] font-semibold text-gray-500">{{ metrics.closed_branches_count }}/{{ metrics.total_branches }} cabang closing</span>
                </div>
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between"><span class="text-sm text-gray-500">Total Pemasukan</span><b class="font-mono text-gray-900 dark:text-white">{{ rupiah(metrics.total_omset_today) }}</b></div>
                    <div class="flex items-center justify-between"><span class="text-sm text-gray-500">Total Pengeluaran</span><b class="font-mono text-gray-900 dark:text-white">{{ rupiah(metrics.total_expense_today) }}</b></div>
                    <div class="flex items-center justify-between border-t pt-2.5"><span class="text-sm font-semibold">Laba Bersih</span><b class="font-mono text-lg" :class="laba < 0 ? 'text-red-600' : 'text-emerald-600'">{{ rupiah(laba) }}</b></div>
                </div>
            </section>

            <!-- ABSENSI SAYA (jika admin punya profil karyawan) -->
            <section v-if="employee" class="rounded-3xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                <h2 class="mb-3 text-xs font-black tracking-wider text-gray-400 uppercase">Absensi Saya</h2>
                <div class="flex items-center justify-between gap-3">
                    <div class="flex gap-4">
                        <div><div class="text-xs text-gray-500">Clock In</div><div class="font-black text-rose-600">{{ attendance?.clock_in_time ? attendance.clock_in_time.slice(0, 5) : '--:--' }}</div></div>
                        <div class="w-px bg-gray-200"></div>
                        <div><div class="text-xs text-gray-500">Clock Out</div><div class="font-black">{{ attendance?.clock_out_time ? attendance.clock_out_time.slice(0, 5) : '--:--' }}</div></div>
                    </div>
                    <button
                        v-if="!employee.is_face_registered"
                        type="button"
                        class="rounded-2xl bg-rose-50 px-4 py-2.5 text-xs font-bold text-rose-700"
                        @click="emit('register-face')"
                    >
                        Daftarkan Face ID
                    </button>
                    <button
                        v-else
                        type="button"
                        class="flex items-center gap-2 rounded-2xl px-5 py-3 text-sm font-black text-white shadow transition-transform active:scale-95 disabled:bg-gray-300"
                        :class="clockedIn ? 'bg-amber-600' : 'bg-rose-600'"
                        :disabled="clockedOut"
                        @click="emit('punch', clockedIn ? 'clock_out' : 'clock_in')"
                    >
                        <Camera class="size-5" /> {{ clockedOut ? 'Selesai' : clockedIn ? 'Clock Out' : 'Clock In' }}
                    </button>
                </div>
            </section>

            <!-- PERLU TINDAKAN -->
            <section class="overflow-hidden rounded-3xl bg-white shadow-sm dark:bg-zinc-900">
                <h2 class="px-5 pt-4 pb-2 text-xs font-black tracking-wider text-gray-400 uppercase">Perlu Tindakan</h2>
                <Link v-for="t in tasks" :key="t.href" :href="t.href" class="flex items-center gap-3 border-t border-gray-100 px-5 py-3.5 text-sm dark:border-zinc-800">
                    <component :is="t.icon" class="size-5 text-rose-500" />
                    <span class="flex-1 font-semibold">{{ t.label }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-black" :class="t.count ? 'bg-rose-600 text-white' : 'bg-gray-100 text-gray-400'">{{ t.count }}</span>
                    <ChevronRight class="size-4 text-gray-300" />
                </Link>
            </section>

            <!-- MENU -->
            <section v-for="g in adminMenuGroups" :key="g.label">
                <h2 class="mb-2.5 text-sm font-black tracking-wide text-gray-800 uppercase dark:text-gray-200">{{ g.label }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    <Link v-for="m in g.items" :key="m.href" :href="m.href" class="flex flex-col gap-2 rounded-3xl bg-white p-4 shadow-sm transition-transform active:scale-[0.98] dark:bg-zinc-900">
                        <div class="flex size-11 items-center justify-center rounded-2xl" :class="m.tone"><component :is="m.icon" class="size-6" /></div>
                        <div>
                            <div class="text-[15px] leading-tight font-black text-gray-900 dark:text-white">{{ m.title }}</div>
                            <div class="mt-0.5 text-xs leading-snug text-gray-500">{{ m.desc }}</div>
                        </div>
                    </Link>
                </div>
            </section>
        </div>
    </div>
</template>
