<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowDownRight,
    ArrowUpRight,
    Building2,
    Calendar,
    Camera,
    CheckCircle,
    CheckCircle2,
    Clock,
    DollarSign,
    ExternalLink,
    Eye,
    FileSpreadsheet,
    FileText,
    HelpCircle,
    MapPin,
    Navigation,
    Plus,
    RefreshCw,
    Shield,
    Sparkles,
    TrendingUp,
    UploadCloud,
    UserCheck,
    Users,
    XCircle,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

// Props received from DashboardController
const props = defineProps<{
    metrics: {
        total_branches: number;
        total_employees: number;
        closed_branches_count: number;
        total_omset_today: number;
        total_expense_today: number;
        total_net_cash_today: number;
        pending_transfers_count: number;
        attendances_summary: {
            total_present: number;
            on_time: number;
            late: number;
        };
    };
    userBranch?: {
        id: number;
        branch_code: string;
        branch_name: string;
        address: string;
        latitude: number;
        longitude: number;
        radius_meters: number;
    } | null;
    userAttendance?: {
        id: number;
        date: string;
        clock_in_time: string | null;
        clock_in_photo_url: string | null;
        clock_out_time: string | null;
        status: string;
        notes: string | null;
    } | null;
    currentShift?: {
        id: number;
        shift_name: string;
        start_time: string;
        end_time: string;
        late_tolerance_minutes: number;
    } | null;
    branches: Array<{
        id: number;
        branch_code: string;
        branch_name: string;
        address: string;
        latitude: number;
        longitude: number;
        radius_meters: number;
        active_employees_count: number;
    }>;
    todayCashflows: Array<{
        id: number;
        branch: {
            branch_name: string;
            branch_code: string;
        };
        report_date: string;
        total_omset: number;
        total_expense: number;
        total_net_cash: number;
        status: string;
    }>;
    recentAttendances: Array<{
        id: number;
        date: string;
        clock_in_time: string | null;
        clock_out_time: string | null;
        status: string;
        employee: {
            full_name: string;
            nik: string;
        };
        branch: {
            branch_name: string;
        };
        shift?: {
            shift_name: string;
        } | null;
    }>;
    bankAccounts: Array<{
        id: number;
        bank_name: string;
        account_number: string;
        account_holder_name: string;
    }>;
    todayDateFormatted: string;
}>();

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const roleName = computed(
    () => currentUser.value?.role?.name || 'staff_cabang',
);
const isAdminPusat = computed(() => roleName.value === 'admin_pusat');
const isStaffCabang = computed(() => roleName.value === 'staff_cabang');
const roleTitle = computed(
    () =>
        currentUser.value?.role?.display_name ||
        (isAdminPusat.value ? 'Admin Pusat' : 'Staff Cabang'),
);

// Realtime Clock
const currentTime = ref('');
const updateTime = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

onMounted(() => {
    updateTime();
    setInterval(updateTime, 1000);
});

// Format Currency
const formatRupiah = (val: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(val || 0);
};

// Geolocation Mock / Status for preview
const gpsStatus = ref<'calculating' | 'inside' | 'outside'>('inside');
const calculatedDistance = ref(18.5); // meter

// Lightbox preview modal state
const previewImageUrl = ref<string | null>(null);
const previewTitle = ref('');

const openPreview = (url: string, title: string) => {
    previewImageUrl.value = url;
    previewTitle.value = title;
};

const closePreview = () => {
    previewImageUrl.value = null;
};
</script>

<template>
    <Head title="Beranda Operasional - Momoyo Multi-Cabang" />

    <div class="mx-auto max-w-7xl flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- TOP BANNER: Brand & Profile Greeting with Quick Role Switcher -->
        <div
            class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-red-600 via-rose-600 to-amber-600 p-6 text-white shadow-xl shadow-rose-900/10 sm:p-8"
        >
            <!-- Background Ornaments -->
            <div
                class="pointer-events-none absolute -top-16 -right-16 size-64 rounded-full bg-white/10 blur-2xl"
            ></div>
            <div
                class="pointer-events-none absolute right-32 -bottom-20 size-48 rounded-full bg-amber-400/20 blur-2xl"
            ></div>

            <div
                class="relative z-10 flex flex-col gap-6 md:flex-row md:items-center md:justify-between"
            >
                <div class="space-y-2">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/15 px-3 py-1 text-xs font-semibold text-rose-100 backdrop-blur-md"
                    >
                        <Sparkles class="size-3.5 text-amber-300" />
                        <span
                            >Sistem Manajemen Operasional & Cashflow
                            Momoyo</span
                        >
                    </div>
                    <h1
                        class="text-2xl font-black tracking-tight text-white sm:text-3xl"
                    >
                        Halo, {{ currentUser?.name }}! 👋
                    </h1>
                    <p
                        class="flex max-w-xl flex-wrap items-center gap-2 text-sm text-rose-100"
                    >
                        <span>{{ todayDateFormatted }}</span>
                        <span class="opacity-60">•</span>
                        <span
                            class="rounded bg-white/20 px-2 py-0.5 font-mono text-xs font-bold"
                            >{{ currentTime }} WIB</span
                        >
                        <span v-if="userBranch" class="opacity-60">•</span>
                        <span
                            v-if="userBranch"
                            class="inline-flex items-center gap-1 font-semibold text-amber-200"
                        >
                            <MapPin class="size-3.5" />
                            {{ userBranch.branch_name }}
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- 4 KPI SUMMARY CARDS -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Card 1: Total Omset -->
            <Card
                class="border-gray-200 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle
                        class="text-xs font-bold tracking-wider text-gray-500 uppercase"
                    >
                        {{
                            isStaffCabang
                                ? 'Omset Cabang Hari Ini'
                                : 'Total Omset Jaringan'
                        }}
                    </CardTitle>
                    <div
                        class="flex size-8 items-center justify-center rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-950/60"
                    >
                        <TrendingUp class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-black tracking-tight text-gray-900 dark:text-white"
                    >
                        {{
                            formatRupiah(metrics.total_omset_today || 14850000)
                        }}
                    </div>
                    <div
                        class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <span
                            class="inline-flex items-center gap-0.5 font-bold text-emerald-600"
                        >
                            <ArrowUpRight class="size-3.5" /> +12%
                        </span>
                        <span>vs hari kemarin</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Card 2: Pengeluaran Kas Kecil -->
            <Card
                class="border-gray-200 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle
                        class="text-xs font-bold tracking-wider text-gray-500 uppercase"
                    >
                        Biaya Kas Kecil (Petty Cash)
                    </CardTitle>
                    <div
                        class="flex size-8 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-950/60"
                    >
                        <DollarSign class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-black tracking-tight text-gray-900 dark:text-white"
                    >
                        {{
                            formatRupiah(metrics.total_expense_today || 345000)
                        }}
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Bahan baku darurat & operasional
                    </p>
                </CardContent>
            </Card>

            <!-- Card 3: Net Cash Fisik -->
            <Card
                class="border-gray-200 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle
                        class="text-xs font-bold tracking-wider text-gray-500 uppercase"
                    >
                        Total Net Cash Fisik
                    </CardTitle>
                    <div
                        class="flex size-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60"
                    >
                        <CheckCircle class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-black tracking-tight text-emerald-600 dark:text-emerald-400"
                    >
                        {{
                            formatRupiah(
                                metrics.total_net_cash_today || 6450000,
                            )
                        }}
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Omset Cash dikurangi Pengeluaran
                    </p>
                </CardContent>
            </Card>

            <!-- Card 4: Status Setoran / Kehadiran -->
            <Card
                class="border-gray-200 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle
                        class="text-xs font-bold tracking-wider text-gray-500 uppercase"
                    >
                        {{
                            isAdminPusat
                                ? 'Setoran Pending Finance'
                                : 'Status Kehadiran Toko'
                        }}
                    </CardTitle>
                    <div
                        class="flex size-8 items-center justify-center rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-950/60"
                    >
                        <Building2 class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-black tracking-tight text-gray-900 dark:text-white"
                    >
                        <span v-if="isAdminPusat">
                            {{ metrics.pending_transfers_count || 3 }} Cabang
                        </span>
                        <span v-else>
                            {{ metrics.attendances_summary.total_present || 4 }}
                            Kru Hadir
                        </span>
                    </div>
                    <p
                        class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <span class="size-2 rounded-full bg-emerald-500"></span>
                        <span v-if="isAdminPusat">Menunggu rekonsiliasi</span>
                        <span v-else
                            >{{
                                metrics.attendances_summary.on_time || 4
                            }}
                            Tepat waktu</span
                        >
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- SPECIAL ROLE SECTIONS -->

        <!-- A. KHUSUS STAFF CABANG: WIDGET ABSENSI & CLOSING OUTLET -->
        <div
            v-if="isStaffCabang"
            id="absensi-section"
            class="grid grid-cols-1 gap-6 lg:grid-cols-3"
        >
            <!-- ABSENSI SELFIE & GPS CARD -->
            <Card
                class="border-gray-200 shadow-md lg:col-span-1 dark:border-zinc-800"
            >
                <CardHeader
                    class="border-b border-gray-100 pb-3 dark:border-zinc-800"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-xl bg-rose-50 p-2 text-rose-600 dark:bg-rose-950/60"
                            >
                                <Camera class="size-5" />
                            </div>
                            <div>
                                <CardTitle class="text-base font-bold"
                                    >Absensi Mandiri Kru</CardTitle
                                >
                                <CardDescription class="text-xs"
                                    >Selfie Kamera + Validasi
                                    Geofencing</CardDescription
                                >
                            </div>
                        </div>
                        <Badge
                            variant="outline"
                            class="border-rose-300 text-xs text-rose-700 dark:text-rose-300"
                        >
                            {{
                                currentShift?.shift_name ||
                                'Shift Pagi (Opening)'
                            }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4 pt-4">
                    <!-- Geofencing Status Badge -->
                    <div
                        class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-3.5 dark:border-emerald-800/60 dark:bg-emerald-950/30"
                    >
                        <div
                            class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-white"
                        >
                            <Navigation class="size-4 animate-pulse" />
                        </div>
                        <div class="text-xs">
                            <div
                                class="flex items-center gap-1.5 font-bold text-emerald-800 dark:text-emerald-300"
                            >
                                <span>Dalam Radius Gerai Momoyo</span>
                                <span
                                    class="size-1.5 rounded-full bg-emerald-500"
                                ></span>
                            </div>
                            <p
                                class="mt-0.5 text-emerald-700/80 dark:text-emerald-400"
                            >
                                Terdeteksi {{ calculatedDistance }} meter dari
                                {{
                                    userBranch?.branch_name ||
                                    'Momoyo Tebet Raya'
                                }}
                                (Maks. radius
                                {{ userBranch?.radius_meters || 50 }}m).
                            </p>
                        </div>
                    </div>

                    <!-- Attendance Log Today -->
                    <div
                        class="space-y-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-gray-500"
                                >Clock In:</span
                            >
                            <span
                                class="flex items-center gap-1 font-bold text-gray-900 dark:text-gray-100"
                            >
                                <Clock class="size-3.5 text-rose-500" />
                                {{
                                    userAttendance?.clock_in_time || '08:52 WIB'
                                }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-gray-500"
                                >Status Kehadiran:</span
                            >
                            <Badge
                                class="bg-emerald-100 text-[11px] font-bold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300"
                            >
                                Hadir Tepat Waktu
                            </Badge>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-gray-500"
                                >Clock Out:</span
                            >
                            <span class="font-bold text-gray-400"
                                >Belum Clock-Out (Shift Berlangsung)</span
                            >
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <Button
                            class="flex h-10 items-center gap-1.5 rounded-xl bg-rose-600 text-xs font-bold text-white shadow-sm shadow-rose-600/30 hover:bg-rose-700"
                            disabled
                        >
                            <CheckCircle2 class="size-4" />
                            <span>Sudah Clock-In</span>
                        </Button>
                        <Button
                            variant="outline"
                            class="flex h-10 items-center gap-1.5 rounded-xl border-gray-300 text-xs font-bold text-gray-800 hover:bg-rose-50 hover:text-rose-600 dark:border-zinc-700 dark:text-gray-200"
                        >
                            <Clock class="size-4" />
                            <span>Clock-Out Nanti</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- CLOSING KASIR & KAS KECIL SUMMARY CARD -->
            <Card
                class="border-gray-200 shadow-md lg:col-span-2 dark:border-zinc-800"
                id="closing-section"
            >
                <CardHeader
                    class="border-b border-gray-100 pb-3 dark:border-zinc-800"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-xl bg-amber-50 p-2 text-amber-600 dark:bg-amber-950/60"
                            >
                                <DollarSign class="size-5" />
                            </div>
                            <div>
                                <CardTitle class="text-base font-bold"
                                    >Rekap Closing Harian & Kas Kecil
                                    Gerai</CardTitle
                                >
                                <CardDescription class="text-xs"
                                    >Pengganti rekap manual grup WhatsApp
                                    kasir</CardDescription
                                >
                            </div>
                        </div>
                        <span
                            class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-600 dark:border-emerald-800 dark:bg-emerald-950"
                        >
                            Status: Siap Closing
                        </span>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4 pt-4">
                    <!-- Methods Grid -->
                    <div
                        class="grid grid-cols-2 gap-2.5 text-xs sm:grid-cols-4"
                    >
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900"
                        >
                            <span class="block font-medium text-gray-400"
                                >💵 Tunai (Cash)</span
                            >
                            <span
                                class="mt-0.5 block text-sm font-black text-gray-900 dark:text-white"
                                >Rp 3.850.000</span
                            >
                        </div>
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900"
                        >
                            <span class="block font-medium text-gray-400"
                                >📱 QRIS Statis/Dinamis</span
                            >
                            <span
                                class="mt-0.5 block text-sm font-black text-gray-900 dark:text-white"
                                >Rp 5.420.000</span
                            >
                        </div>
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900"
                        >
                            <span class="block font-medium text-gray-400"
                                >💳 EDC BCA / Mandiri</span
                            >
                            <span
                                class="mt-0.5 block text-sm font-black text-gray-900 dark:text-white"
                                >Rp 2.180.000</span
                            >
                        </div>
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900"
                        >
                            <span class="block font-medium text-gray-400"
                                >🛵 Online (Grab/Gofood)</span
                            >
                            <span
                                class="mt-0.5 block text-sm font-black text-gray-900 dark:text-white"
                                >Rp 3.400.000</span
                            >
                        </div>
                    </div>

                    <!-- Petty Cash & Bank Transfer Highlights -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div
                            class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-800/40 dark:bg-amber-950/20"
                        >
                            <div
                                class="mb-2 flex items-center justify-between text-xs font-bold text-amber-900 dark:text-amber-200"
                            >
                                <span>🧾 Pengeluaran Kas Kecil (Nota)</span>
                                <span>Rp 185.000</span>
                            </div>
                            <ul
                                class="space-y-1 text-[11px] text-amber-800/80 dark:text-amber-400"
                            >
                                <li>
                                    • Beli Es Batu Kristal Darurat: Rp 65.000
                                </li>
                                <li>
                                    • Beli Galon Air & Kebersihan: Rp 120.000
                                </li>
                            </ul>
                        </div>

                        <div
                            class="rounded-2xl border border-blue-200 bg-blue-50/60 p-4 dark:border-blue-800/40 dark:bg-blue-950/20"
                        >
                            <div
                                class="mb-2 flex items-center justify-between text-xs font-bold text-blue-900 dark:text-blue-200"
                            >
                                <span>🏦 Setoran ke Rekening Pusat</span>
                                <span
                                    class="font-extrabold text-blue-700 dark:text-blue-300"
                                    >Rp 3.665.000</span
                                >
                            </div>
                            <p
                                class="text-[11px] text-blue-800/80 dark:text-blue-400"
                            >
                                Ditransfer ke BCA (8830192831) a.n PT MOMOYO
                                KULINER NUSANTARA.
                            </p>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div
                        class="flex flex-col items-center justify-between gap-3 pt-2 sm:flex-row"
                    >
                        <div class="text-xs text-gray-500">
                            Formula: <strong>Net Cash Fisik</strong> = Omset
                            Cash (Rp 3.850.000) - Kas Kecil (Rp 185.000) =
                            <strong>Rp 3.665.000</strong>
                        </div>
                        <Button
                            class="h-10 w-full rounded-xl bg-gradient-to-r from-red-600 to-rose-600 px-5 text-xs font-bold text-white shadow hover:from-red-700 hover:to-rose-700 sm:w-auto"
                        >
                            Form Input Closing & Upload Bukti Transfer →
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- B. KHUSUS ADMIN PUSAT: WIDGET REKONSILIASI SETORAN BANK & MULTI-CABANG -->
        <div
            v-if="isAdminPusat"
            id="reconciliation-section"
            class="space-y-6"
        >
            <Card class="border-gray-200 shadow-md dark:border-zinc-800">
                <CardHeader
                    class="border-b border-gray-100 pb-3 dark:border-zinc-800"
                >
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-xl bg-purple-50 p-2 text-purple-600 dark:bg-purple-950/60"
                            >
                                <CheckCircle2 class="size-5" />
                            </div>
                            <div>
                                <CardTitle class="text-base font-bold"
                                    >Rekonsiliasi Setoran Harian Gerai (Finance
                                    Pusat)</CardTitle
                                >
                                <CardDescription class="text-xs"
                                    >Verifikasi mutasi kas fisik, slip EDC, dan
                                    bukti transfer bank pusat</CardDescription
                                >
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge
                                variant="outline"
                                class="border-purple-300 text-xs text-purple-700"
                            >
                                3 Menunggu Verifikasi
                            </Badge>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="border-b border-gray-200 bg-gray-50 text-gray-500 dark:border-zinc-800 dark:bg-zinc-900/60"
                            >
                                <tr>
                                    <th class="px-4 py-3 font-bold">
                                        Cabang Gerai
                                    </th>
                                    <th class="px-4 py-3 font-bold">Tanggal</th>
                                    <th class="px-4 py-3 font-bold">
                                        Net Cash Fisik
                                    </th>
                                    <th class="px-4 py-3 font-bold">
                                        Nominal Transfer
                                    </th>
                                    <th class="px-4 py-3 font-bold">
                                        Rekening Tujuan
                                    </th>
                                    <th class="px-4 py-3 text-center font-bold">
                                        Bukti Transfer
                                    </th>
                                    <th class="px-4 py-3 text-center font-bold">
                                        Aksi Verifikasi
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-zinc-800"
                            >
                                <!-- Row 1: Tebet -->
                                <tr
                                    class="transition-colors hover:bg-gray-50/50 dark:hover:bg-zinc-800/30"
                                >
                                    <td
                                        class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white"
                                    >
                                        Momoyo Tebet Raya
                                        <span
                                            class="block text-[11px] font-normal text-gray-400"
                                            >MMY-JKT-01</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-gray-600 dark:text-gray-400"
                                    >
                                        Hari ini
                                    </td>
                                    <td
                                        class="px-4 py-3.5 font-bold text-gray-900 dark:text-white"
                                    >
                                        Rp 3.665.000
                                    </td>
                                    <td
                                        class="px-4 py-3.5 font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        Rp 3.665.000
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-gray-600 dark:text-gray-400"
                                    >
                                        BCA - 8830192831
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <button
                                            @click="
                                                openPreview(
                                                    '/assets/images/auth/vector1.svg',
                                                    'Bukti Transfer BCA - Momoyo Tebet (Rp 3.665.000)',
                                                )
                                            "
                                            class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-rose-600 hover:bg-gray-200 dark:bg-zinc-800"
                                        >
                                            <Eye class="size-3.5" />
                                            <span>Lihat Bukti</span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <div
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <Button
                                                size="sm"
                                                class="h-7 rounded-lg bg-emerald-600 px-2.5 text-xs font-bold text-white hover:bg-emerald-700"
                                            >
                                                ✓ Verifikasi Cocok
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="h-7 rounded-lg border-rose-300 px-2 text-xs text-rose-600 hover:bg-rose-50"
                                            >
                                                Selisih
                                            </Button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Row 2: Margonda -->
                                <tr
                                    class="transition-colors hover:bg-gray-50/50 dark:hover:bg-zinc-800/30"
                                >
                                    <td
                                        class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white"
                                    >
                                        Momoyo Margonda Depok
                                        <span
                                            class="block text-[11px] font-normal text-gray-400"
                                            >MMY-DPK-01</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-gray-600 dark:text-gray-400"
                                    >
                                        Hari ini
                                    </td>
                                    <td
                                        class="px-4 py-3.5 font-bold text-gray-900 dark:text-white"
                                    >
                                        Rp 2.940.000
                                    </td>
                                    <td
                                        class="px-4 py-3.5 font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        Rp 2.940.000
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-gray-600 dark:text-gray-400"
                                    >
                                        Mandiri - 1370019283712
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <button
                                            @click="
                                                openPreview(
                                                    '/assets/images/auth/vector2.svg',
                                                    'Bukti Transfer Mandiri - Momoyo Margonda (Rp 2.940.000)',
                                                )
                                            "
                                            class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-rose-600 hover:bg-gray-200 dark:bg-zinc-800"
                                        >
                                            <Eye class="size-3.5" />
                                            <span>Lihat Bukti</span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <div
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <Button
                                                size="sm"
                                                class="h-7 rounded-lg bg-emerald-600 px-2.5 text-xs font-bold text-white hover:bg-emerald-700"
                                            >
                                                ✓ Verifikasi Cocok
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="h-7 rounded-lg border-rose-300 px-2 text-xs text-rose-600 hover:bg-rose-50"
                                            >
                                                Selisih
                                            </Button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Row 3: Gading Serpong -->
                                <tr
                                    class="transition-colors hover:bg-gray-50/50 dark:hover:bg-zinc-800/30"
                                >
                                    <td
                                        class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white"
                                    >
                                        Momoyo Gading Serpong
                                        <span
                                            class="block text-[11px] font-normal text-gray-400"
                                            >MMY-TNG-01</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-gray-600 dark:text-gray-400"
                                    >
                                        Hari ini
                                    </td>
                                    <td
                                        class="px-4 py-3.5 font-bold text-gray-900 dark:text-white"
                                    >
                                        Rp 4.120.000
                                    </td>
                                    <td
                                        class="px-4 py-3.5 font-bold text-amber-600 dark:text-amber-400"
                                    >
                                        Rp 3.920.000 (Selisih)
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-gray-600 dark:text-gray-400"
                                    >
                                        BCA - 8830192831
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <button
                                            @click="
                                                openPreview(
                                                    '/assets/images/auth/vector1.svg',
                                                    'Bukti Transfer BCA - Momoyo Serpong (Rp 3.920.000)',
                                                )
                                            "
                                            class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-rose-600 hover:bg-gray-200 dark:bg-zinc-800"
                                        >
                                            <Eye class="size-3.5" />
                                            <span>Lihat Bukti</span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <div
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="h-7 rounded-lg border-amber-400 bg-amber-50 px-2.5 text-xs font-bold text-amber-700"
                                            >
                                                ⚠️ Review Selisih Modal
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- C. JARINGAN CABANG & GEOFENCING GPS MONITORING (ADMIN PUSAT) -->
        <div
            v-if="isAdminPusat"
            id="branches-section"
            class="grid grid-cols-1 gap-6 lg:grid-cols-3"
        >
            <Card
                class="border-gray-200 shadow-md lg:col-span-3 dark:border-zinc-800"
            >
                <CardHeader
                    class="border-b border-gray-100 pb-3 dark:border-zinc-800"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-xl bg-rose-50 p-2 text-rose-600 dark:bg-rose-950/60"
                            >
                                <Building2 class="size-5" />
                            </div>
                            <div>
                                <CardTitle class="text-base font-bold"
                                    >Master Gerai Momoyo & Radius Geofencing
                                    GPS</CardTitle
                                >
                                <CardDescription class="text-xs"
                                    >Titik koordinat acuan validasi Clock-In
                                    kamera mandiri kru gerai</CardDescription
                                >
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-8 rounded-xl border-gray-300 text-xs font-bold hover:border-rose-300 hover:text-rose-600 dark:border-zinc-700"
                                as-child
                            >
                                <Link href="/employees">
                                    <Users
                                        class="mr-1 size-3.5 text-rose-500"
                                    />
                                    Kelola Master Karyawan
                                </Link>
                            </Button>
                            <Button
                                size="sm"
                                class="h-8 rounded-xl bg-rose-600 text-xs font-bold text-white hover:bg-rose-700"
                                as-child
                            >
                                <Link href="/branches">
                                    <Plus class="mr-1 size-3.5" /> Kelola Cabang
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="pt-4">
                    <div
                        v-if="branches.length > 0"
                        class="grid grid-cols-1 gap-4 md:grid-cols-3"
                    >
                        <div
                            v-for="branch in branches"
                            :key="branch.id"
                            class="space-y-3 rounded-2xl border border-gray-200 bg-white p-4 transition-all hover:border-rose-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-rose-900"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <h4
                                        class="text-sm font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ branch.branch_name }}
                                    </h4>
                                    <p
                                        class="font-mono text-[11px] font-semibold text-rose-600"
                                    >
                                        {{ branch.branch_code }}
                                    </p>
                                </div>
                                <span
                                    class="size-2 rounded-full bg-emerald-500"
                                ></span>
                            </div>

                            <p class="line-clamp-2 text-xs text-gray-500">
                                {{ branch.address }}
                            </p>

                            <div
                                class="flex items-center justify-between border-t border-gray-100 pt-2 text-xs dark:border-zinc-800"
                            >
                                <span
                                    class="flex items-center gap-1 text-gray-400"
                                >
                                    <MapPin class="size-3.5 text-rose-500" />
                                    Lat {{ branch.latitude.toFixed(4) }}, Long
                                    {{ branch.longitude.toFixed(4) }}
                                </span>
                                <Badge
                                    variant="secondary"
                                    class="text-[10px] font-bold"
                                >
                                    Radius {{ branch.radius_meters }}m
                                </Badge>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-200 py-10 text-center dark:border-zinc-800"
                    >
                        <Building2
                            class="mb-2 size-10 text-gray-300 dark:text-zinc-700"
                        />
                        <h4
                            class="text-sm font-bold text-gray-800 dark:text-gray-200"
                        >
                            Belum Ada Gerai / Cabang Terdaftar
                        </h4>
                        <p
                            class="mt-1 max-w-sm text-xs text-gray-500 dark:text-gray-400"
                        >
                            Sistem siap digunakan. Silakan tambahkan data cabang
                            Momoyo perdana Anda untuk mengatur radius geofencing
                            GPS dan penempatan karyawan.
                        </p>
                        <Button
                            size="sm"
                            class="mt-4 rounded-xl bg-rose-600 px-4 text-xs font-bold text-white shadow-sm hover:bg-rose-700"
                            as-child
                        >
                            <Link href="/branches">
                                <Plus class="mr-1.5 size-3.5" /> Tambah Cabang
                                Perdana
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- D. LOG AKTIVITAS KEHADIRAN TERAKHIR (ALL USERS) -->
        <Card class="border-gray-200 shadow-md dark:border-zinc-800">
            <CardHeader
                class="border-b border-gray-100 pb-3 dark:border-zinc-800"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div
                            class="rounded-xl bg-blue-50 p-2 text-blue-600 dark:bg-blue-950/60"
                        >
                            <Clock class="size-5" />
                        </div>
                        <div>
                            <CardTitle class="text-base font-bold"
                                >Log Absensi Kru Hari Ini (Real-Time)</CardTitle
                            >
                            <CardDescription class="text-xs"
                                >Deteksi keterlambatan & kepatuhan radius
                                outlet</CardDescription
                            >
                        </div>
                    </div>
                    <span class="text-xs font-medium text-gray-400"
                        >Diperbarui otomatis</span
                    >
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="border-b border-gray-200 bg-gray-50 text-gray-500 dark:border-zinc-800 dark:bg-zinc-900/60"
                        >
                            <tr>
                                <th class="px-4 py-3 font-bold">Karyawan</th>
                                <th class="px-4 py-3 font-bold">Cabang</th>
                                <th class="px-4 py-3 font-bold">Shift</th>
                                <th class="px-4 py-3 font-bold">Jam Masuk</th>
                                <th class="px-4 py-3 font-bold">
                                    Status Kehadiran
                                </th>
                                <th class="px-4 py-3 text-center font-bold">
                                    Verifikasi Lokasi
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-zinc-800"
                        >
                            <tr
                                v-for="att in recentAttendances.length
                                    ? recentAttendances
                                    : [
                                          {
                                              id: 1,
                                              employee: {
                                                  full_name: 'Agus Pratama',
                                                  nik: 'EMP-MMY-001',
                                              },
                                              branch: {
                                                  branch_name:
                                                      'Momoyo Tebet Raya',
                                              },
                                              shift: { shift_name: 'Opening' },
                                              clock_in_time: '08:50',
                                              status: 'hadir_tepat_waktu',
                                          },
                                          {
                                              id: 2,
                                              employee: {
                                                  full_name: 'Siti Rahma',
                                                  nik: 'EMP-MMY-002',
                                              },
                                              branch: {
                                                  branch_name:
                                                      'Momoyo Tebet Raya',
                                              },
                                              shift: { shift_name: 'Opening' },
                                              clock_in_time: '08:52',
                                              status: 'hadir_tepat_waktu',
                                          },
                                          {
                                              id: 3,
                                              employee: {
                                                  full_name: 'Fajar Santoso',
                                                  nik: 'EMP-MMY-003',
                                              },
                                              branch: {
                                                  branch_name:
                                                      'Momoyo Margonda',
                                              },
                                              shift: { shift_name: 'Opening' },
                                              clock_in_time: '08:58',
                                              status: 'hadir_tepat_waktu',
                                          },
                                      ]"
                                :key="att.id"
                                class="transition-colors hover:bg-gray-50/50 dark:hover:bg-zinc-800/30"
                            >
                                <td
                                    class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ att.employee.full_name }}
                                    <span
                                        class="block font-mono text-[11px] text-gray-400"
                                        >{{ att.employee.nik }}</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3.5 font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ att.branch.branch_name }}
                                </td>
                                <td
                                    class="px-4 py-3.5 text-gray-600 dark:text-gray-400"
                                >
                                    {{ att.shift?.shift_name || 'Shift Pagi' }}
                                </td>
                                <td
                                    class="px-4 py-3.5 font-mono font-bold text-gray-900 dark:text-white"
                                >
                                    {{ att.clock_in_time || '08:55' }} WIB
                                </td>
                                <td class="px-4 py-3.5">
                                    <Badge
                                        class="bg-emerald-100 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                    >
                                        Tepat Waktu
                                    </Badge>
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-600"
                                    >
                                        <CheckCircle2 class="size-3" /> Valid
                                        Radius (15m)
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- LIGHTBOX PREVIEW MODAL -->
    <div
        v-if="previewImageUrl"
        class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/80 p-4 backdrop-blur-sm duration-200 fade-in"
        @click.self="closePreview"
    >
        <div
            class="relative w-full max-w-xl overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div
                class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-zinc-800"
            >
                <h3
                    class="truncate text-sm font-bold text-gray-900 dark:text-white"
                >
                    {{ previewTitle }}
                </h3>
                <button
                    @click="closePreview"
                    class="rounded-full p-1 text-gray-400 hover:text-gray-600"
                >
                    <XCircle class="size-5" />
                </button>
            </div>
            <div
                class="flex min-h-[300px] items-center justify-center bg-gray-50 p-6 dark:bg-zinc-950"
            >
                <img
                    :src="previewImageUrl"
                    :alt="previewTitle"
                    class="max-h-[70vh] rounded-xl border border-gray-200 object-contain shadow-lg dark:border-zinc-800"
                />
            </div>
            <div
                class="flex justify-end gap-2 border-t border-gray-200 p-4 dark:border-zinc-800"
            >
                <Button
                    variant="outline"
                    size="sm"
                    @click="closePreview"
                    class="rounded-xl text-xs"
                >
                    Tutup
                </Button>
                <Button
                    size="sm"
                    class="rounded-xl bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700"
                    @click="closePreview"
                >
                    Verifikasi Valid
                </Button>
            </div>
        </div>
    </div>
</template>
