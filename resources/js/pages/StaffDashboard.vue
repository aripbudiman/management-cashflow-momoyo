<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Boxes,
    Camera,
    CalendarCheck,
    CalendarDays,
    CheckCircle2,
    Clock,
    MapPin,
    Package,
    ScanFace,
    Truck,
    UserRound,
    Wallet,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AttendanceModal from '@/components/AttendanceModal.vue';
import FaceRegistrationModal from '@/components/FaceRegistrationModal.vue';
import { rupiah } from '@/lib/format';

interface Props {
    employee: { id: number; full_name: string; nik: string; face_photo_url: string | null; is_face_registered: boolean; attendance_type: 'radius' | 'flexible' };
    branch: { id: number; branch_name: string; branch_code: string; latitude: number; longitude: number; radius_meters: number } | null;
    shifts: Array<{ id: number; shift_name: string; start_time: string; end_time: string; late_tolerance_minutes: number }>;
    todayAttendance: { clock_in_time: string | null; clock_out_time: string | null; status: string } | null;
    todaySchedule: { is_off: boolean; shift_name: string | null; start_time: string | null; end_time: string | null } | null;
    todayLeave: string | null;
    cashflow: { status: string; total_omset: number; total_expense: number; total_setor: number; tunai: number; qris: number; lainnya: number } | null;
    todayDate: string;
    todayDateFormatted: string;
}

const props = defineProps<Props>();

// Jam berjalan (untuk cincin durasi kerja)
const now = ref(new Date());
let timer: ReturnType<typeof setInterval>;
onMounted(() => (timer = setInterval(() => (now.value = new Date()), 30000)));
onBeforeUnmount(() => clearInterval(timer));

const toMinutes = (hhmm: string) => {
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
};

const clockedIn = computed(() => !!props.todayAttendance?.clock_in_time);
const clockedOut = computed(() => !!props.todayAttendance?.clock_out_time);

const workedMinutes = computed(() => {
    const a = props.todayAttendance;
    if (!a?.clock_in_time) return 0;
    const end = a.clock_out_time ? toMinutes(a.clock_out_time) : now.value.getHours() * 60 + now.value.getMinutes();
    return Math.max(0, end - toMinutes(a.clock_in_time));
});
const targetMinutes = computed(() => {
    const s = props.todaySchedule;
    if (s?.start_time && s.end_time) return Math.max(60, toMinutes(s.end_time) - toMinutes(s.start_time));
    return 8 * 60;
});
const ringProgress = computed(() => Math.min(1, workedMinutes.value / targetMinutes.value));
const ringLen = 2 * Math.PI * 34;
const workedLabel = computed(() => Math.floor(workedMinutes.value / 60));

const dayStatus = computed(() => {
    if (props.todayLeave) return { text: props.todayLeave, cls: 'text-blue-600' };
    if (props.todaySchedule?.is_off) return { text: 'Libur', cls: 'text-red-600' };
    if (clockedOut.value) return { text: 'Shift Selesai', cls: 'text-emerald-600' };
    if (clockedIn.value) return { text: props.todayAttendance?.status === 'terlambat' ? 'Terlambat • Berlangsung' : 'Shift Berlangsung', cls: 'text-amber-600' };
    return { text: 'Belum Absen', cls: 'text-gray-500' };
});

const canPunch = computed(() => !props.todayLeave && !props.todaySchedule?.is_off && !clockedOut.value);
const punchMode = computed<'clock_in' | 'clock_out'>(() => (clockedIn.value ? 'clock_out' : 'clock_in'));

// Modal absensi
const isFaceModalOpen = ref(false);
const isAttendanceModalOpen = ref(false);
const attendanceMode = ref<'clock_in' | 'clock_out'>('clock_in');
const punch = () => {
    if (!props.employee.is_face_registered) {
        isFaceModalOpen.value = true;
        return;
    }
    attendanceMode.value = punchMode.value;
    isAttendanceModalOpen.value = true;
};
const onAttendanceSuccess = () => {
    isAttendanceModalOpen.value = false;
    router.reload();
};

const initials = computed(() => props.employee.full_name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase());

const cashflowStatus: Record<string, { label: string; cls: string }> = {
    submitted: { label: 'Menunggu Verifikasi', cls: 'bg-amber-100 text-amber-700' },
    verified_by_finance: { label: 'Terverifikasi', cls: 'bg-emerald-100 text-emerald-700' },
    revision_needed: { label: 'Perlu Revisi', cls: 'bg-red-100 text-red-700' },
};

const menus = [
    { href: '/attendance', title: 'Absensi Saya', desc: 'Log kehadiran & foto', icon: ScanFace, tone: 'bg-emerald-50 text-emerald-600' },
    { href: '/cashflows', title: 'Input Kasir Harian', desc: 'Pemasukan, pengeluaran & setoran', icon: Wallet, highlight: true },
    { href: '/stock-reports', title: 'Stok Harian', desc: 'Input stock of name', icon: Package, tone: 'bg-amber-50 text-amber-600' },
    { href: '/stock-monitor', title: 'Pantau Stok', desc: 'Stok & kartu stok', icon: Boxes, tone: 'bg-sky-50 text-sky-600' },
    { href: '/stock-transfers', title: 'Transfer Stok', desc: 'Kirim & terima antar cabang', icon: Truck, tone: 'bg-orange-50 text-orange-600' },
    { href: '/leaves', title: 'Izin & Cuti', desc: 'Pengajuan sakit/izin/cuti', icon: CalendarCheck, tone: 'bg-blue-50 text-blue-600' },
    { href: '/my-schedule', title: 'Jadwal Shift', desc: 'Rolling shift & libur', icon: CalendarDays, tone: 'bg-teal-50 text-teal-600' },
    { href: '/my-profile', title: 'Profil Saya', desc: 'Data pribadi & rekening', icon: UserRound, tone: 'bg-purple-50 text-purple-600' },
];
</script>

<template>
    <Head title="Beranda" />

    <div>
        <!-- HEADER HIJAU -->
        <header class="rounded-b-[2.5rem] bg-gradient-to-b from-red-600 via-rose-600 to-amber-600 px-5 pt-6 pb-24 text-white shadow-lg">
            <div class="flex items-center gap-3">
                <div class="size-14 shrink-0 overflow-hidden rounded-full border-2 border-white/50 bg-white/20">
                    <img v-if="employee.face_photo_url" :src="employee.face_photo_url" :alt="employee.full_name" class="size-full object-cover" />
                    <div v-else class="flex size-full items-center justify-center text-lg font-black">{{ initials }}</div>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-bold tracking-[0.2em] text-rose-100 uppercase">Selamat Datang</div>
                    <div class="truncate text-xl font-black">{{ employee.full_name }}</div>
                    <div class="flex items-center gap-1 text-sm text-rose-100">
                        <MapPin class="size-3.5" /> <span class="truncate">{{ branch?.branch_name ?? 'Belum ada cabang' }}</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="-mt-20 space-y-6 px-4">
            <!-- KARTU HARI INI -->
            <section class="rounded-3xl bg-white p-5 shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0 space-y-3">
                        <div class="text-xs font-bold tracking-wider text-gray-400 uppercase">Hari ini • {{ todayDateFormatted }}</div>
                        <div class="flex items-stretch gap-4">
                            <div>
                                <div class="text-xs text-gray-500">Clock In:</div>
                                <div class="text-lg font-black text-rose-700">{{ todayAttendance?.clock_in_time ? `${todayAttendance.clock_in_time} WIB` : '--:--' }}</div>
                            </div>
                            <div class="w-px bg-gray-200"></div>
                            <div>
                                <div class="text-xs text-gray-500">Clock Out:</div>
                                <div class="text-lg font-black text-gray-900 dark:text-white">{{ todayAttendance?.clock_out_time ? `${todayAttendance.clock_out_time} WIB` : '--:--' }}</div>
                            </div>
                        </div>
                    </div>
                    <!-- Cincin durasi kerja -->
                    <div class="relative size-20 shrink-0">
                        <svg viewBox="0 0 80 80" class="size-full -rotate-90">
                            <circle cx="40" cy="40" r="34" fill="none" stroke="#ffe4e6" stroke-width="6" />
                            <circle cx="40" cy="40" r="34" fill="none" stroke="#e11d48" stroke-width="6" stroke-linecap="round" :stroke-dasharray="ringLen" :stroke-dashoffset="ringLen * (1 - ringProgress)" />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center leading-tight">
                            <span class="text-xl font-black text-gray-900 dark:text-white">{{ workedLabel }}</span>
                            <span class="text-[10px] text-gray-500">Jam</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-end justify-between gap-3">
                    <div class="min-w-0 text-sm">
                        <div v-if="todayLeave" class="font-bold text-blue-600">{{ todayLeave }} (disetujui)</div>
                        <div v-else-if="todaySchedule?.is_off" class="font-bold text-red-600">Hari Libur</div>
                        <div v-else-if="todaySchedule?.shift_name" class="font-bold text-gray-900 dark:text-white">{{ todaySchedule.shift_name }} ({{ todaySchedule.start_time }} - {{ todaySchedule.end_time }})</div>
                        <div v-else class="font-bold text-gray-500">Belum dijadwalkan</div>
                        <div class="mt-0.5 flex items-center gap-1 text-xs font-semibold" :class="dayStatus.cls">
                            <CheckCircle2 v-if="clockedOut" class="size-3.5" /><Clock v-else class="size-3.5" /> {{ dayStatus.text }}
                        </div>
                    </div>
                    <button
                        type="button"
                        class="flex shrink-0 items-center gap-2 rounded-2xl px-5 py-3 text-sm font-black text-white shadow transition-transform active:scale-95 disabled:cursor-not-allowed disabled:bg-gray-300"
                        :class="punchMode === 'clock_out' ? 'bg-amber-600' : 'bg-rose-700'"
                        :disabled="!canPunch"
                        @click="punch"
                    >
                        <Camera class="size-5" /> {{ clockedOut ? 'Selesai' : punchMode === 'clock_out' ? 'Clock Out' : 'Clock In' }}
                    </button>
                </div>

                <button v-if="!employee.is_face_registered" type="button" class="mt-4 flex w-full items-center gap-2 rounded-2xl bg-rose-50 px-3 py-2.5 text-left text-xs font-semibold text-rose-700" @click="isFaceModalOpen = true">
                    <AlertCircle class="size-4 shrink-0" /> Face ID belum terdaftar — ketuk untuk mendaftarkan wajah
                </button>
            </section>

            <!-- MENU UTAMA -->
            <section>
                <h2 class="mb-3 text-sm font-black tracking-wide text-gray-800 uppercase dark:text-gray-200">Menu Utama Karyawan & Kasir</h2>
                <div class="grid grid-cols-2 gap-3">
                    <Link
                        v-for="m in menus"
                        :key="m.href"
                        :href="m.href"
                        class="flex flex-col gap-2 rounded-3xl p-4 shadow-sm transition-transform active:scale-[0.98]"
                        :class="m.highlight ? 'bg-gradient-to-br from-rose-500 to-red-600 text-white' : 'bg-white dark:bg-zinc-900'"
                    >
                        <div class="flex size-11 items-center justify-center rounded-2xl" :class="m.highlight ? 'bg-white/20' : m.tone">
                            <component :is="m.icon" class="size-6" />
                        </div>
                        <div>
                            <div class="text-[15px] leading-tight font-black" :class="m.highlight ? '' : 'text-gray-900 dark:text-white'">{{ m.title }}</div>
                            <div class="mt-0.5 text-xs leading-snug" :class="m.highlight ? 'text-rose-100' : 'text-gray-500'">{{ m.desc }}</div>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- CASHFLOW HARI INI -->
            <section class="rounded-3xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-black text-gray-900 dark:text-white">Cashflow Kasir Hari Ini</h2>
                        <p class="text-xs text-gray-500">{{ branch?.branch_name }}</p>
                    </div>
                    <Link href="/cashflows" class="text-sm font-bold whitespace-nowrap text-rose-700">+ Input Kasir ›</Link>
                </div>

                <div v-if="cashflow" class="mt-4 space-y-3">
                    <div class="grid grid-cols-3 gap-2 rounded-2xl bg-gray-50 p-3 text-center dark:bg-zinc-800">
                        <div><div class="text-[11px] text-gray-500">Tunai</div><div class="text-sm font-black">{{ rupiah(cashflow.tunai) }}</div></div>
                        <div><div class="text-[11px] text-gray-500">QRIS</div><div class="text-sm font-black">{{ rupiah(cashflow.qris) }}</div></div>
                        <div><div class="text-[11px] text-gray-500">Lainnya</div><div class="text-sm font-black">{{ rupiah(cashflow.lainnya) }}</div></div>
                    </div>
                    <div class="flex justify-between text-sm"><span class="text-gray-500">Total Pemasukan</span><b>{{ rupiah(cashflow.total_omset) }}</b></div>
                    <div class="flex justify-between text-sm"><span class="text-gray-500">Pengeluaran</span><b>{{ rupiah(cashflow.total_expense) }}</b></div>
                    <div class="flex items-center justify-between border-t pt-3">
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold" :class="cashflowStatus[cashflow.status]?.cls">{{ cashflowStatus[cashflow.status]?.label }}</span>
                        <span class="text-sm">Setor: <b class="text-rose-700">{{ rupiah(cashflow.total_setor) }}</b></span>
                    </div>
                </div>
                <div v-else class="mt-4 rounded-2xl bg-gray-50 p-4 text-center text-sm text-gray-500 dark:bg-zinc-800">
                    Belum ada closing hari ini.
                    <Link href="/cashflows" class="mt-1 block font-bold text-rose-700">Input kasir sekarang</Link>
                </div>
            </section>
        </div>

        <FaceRegistrationModal v-model:open="isFaceModalOpen" :employee-name="employee.full_name" :is-updating="employee.is_face_registered" @success="isFaceModalOpen = false; router.reload()" />
        <AttendanceModal
            v-model:open="isAttendanceModalOpen"
            :mode="attendanceMode"
            :branch="branch"
            :shifts="shifts"
            :employee-face-url="employee.face_photo_url"
            :employee-name="employee.full_name"
            :attendance-type="employee.attendance_type"
            @success="onAttendanceSuccess"
        />
    </div>
</template>
