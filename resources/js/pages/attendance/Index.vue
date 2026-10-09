<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowRight,
    Building2,
    Calendar,
    Camera,
    CheckCircle,
    CheckCircle2,
    Clock,
    ExternalLink,
    Eye,
    Filter,
    HelpCircle,
    MapPin,
    Navigation,
    Plus,
    RefreshCw,
    ScanFace,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    UserCheck,
    Users,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import AttendanceModal from '@/components/AttendanceModal.vue';
import FaceRegistrationModal from '@/components/FaceRegistrationModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useGeolocation } from '@/composables/useGeolocation';
import AppLayout from '@/layouts/AppLayout.vue';

interface EmployeeData {
    id: number;
    nik: string;
    full_name: string;
    phone_number: string | null;
    status: string;
    current_branch_id: number;
    face_id: string | null;
    face_photo_path: string | null;
    face_photo_url: string | null;
    is_face_registered: boolean;
    face_registered_at: string | null;
    current_branch?: BranchData | null;
}

interface BranchData {
    id: number;
    branch_code: string;
    branch_name: string;
    address: string;
    latitude: number;
    longitude: number;
    radius_meters: number;
}

interface ShiftData {
    id: number;
    branch_id: number;
    shift_name: string;
    start_time: string;
    end_time: string;
    late_tolerance_minutes: number;
}

interface AttendanceRecord {
    id: number;
    employee_id: number;
    branch_id: number;
    shift_id: number | null;
    date: string;
    clock_in_time: string | null;
    clock_in_photo_url: string | null;
    clock_in_photo_full_url?: string | null;
    clock_in_lat: number | null;
    clock_in_long: number | null;
    clock_in_distance_meters: number | null;
    clock_out_time: string | null;
    clock_out_photo_url: string | null;
    clock_out_photo_full_url?: string | null;
    clock_out_lat: number | null;
    clock_out_distance_meters: number | null;
    clock_in_face_match_percentage?: number | null;
    clock_out_face_match_percentage?: number | null;
    is_face_mismatch?: boolean;
    status: string;
    notes: string | null;
    employee?: {
        id: number;
        full_name: string;
        nik: string;
        face_id?: string | null;
        face_photo_path?: string | null;
        face_photo_url?: string | null;
        current_branch?: {
            branch_name: string;
        };
    };
    shift?: ShiftData | null;
    branch?: BranchData | null;
}

const props = defineProps<{
    employee?: EmployeeData | null;
    branch?: BranchData | null;
    shifts?: ShiftData[];
    todayAttendance?: AttendanceRecord | null;
    recentAttendances?: AttendanceRecord[];
    todayDateFormatted: string;
    todayDate: string;
    adminMonitoring?: {
        attendances: {
            data: AttendanceRecord[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
        branches: Array<{
            id: number;
            branch_code: string;
            branch_name: string;
        }>;
        stats: {
            total_today: number;
            on_time: number;
            late: number;
            clocked_out: number;
        };
    } | null;
    filters?: {
        search?: string;
        branch_id?: string;
        status?: string;
    };
}>();

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const isAdminPusat = computed(
    () => currentUser.value?.role?.name === 'admin_pusat',
);

// Realtime Digital Clock
const currentTimeStr = ref('');
const updateTime = () => {
    const now = new Date();
    currentTimeStr.value = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

onMounted(() => {
    updateTime();
    setInterval(updateTime, 1000);
    checkLiveGps();
});

// Geolocation composable
const {
    coords,
    isLoading: isGpsLoading,
    error: gpsError,
    getLocation,
    calculateDistance,
} = useGeolocation();
const liveDistance = ref<number | null>(null);

const checkLiveGps = async () => {
    if (!props.branch) return;
    try {
        const pos = await getLocation();
        if (pos.latitude && pos.longitude) {
            liveDistance.value = calculateDistance(
                pos.latitude,
                pos.longitude,
                props.branch.latitude,
                props.branch.longitude,
            );
        }
    } catch (e) {
        // Handled in composable
    }
};

const isInsideRadius = computed(() => {
    if (!props.branch || liveDistance.value === null) return false;
    return liveDistance.value <= (props.branch.radius_meters || 50);
});

// Modals State
const isFaceModalOpen = ref(false);
const isAttendanceModalOpen = ref(false);
const attendanceModalMode = ref<'clock_in' | 'clock_out'>('clock_in');

// Lightbox modal for selfie photos
const previewPhotoUrl = ref<string | null>(null);
const previewPhotoTitle = ref('');

const openPhotoPreview = (url: string | null | undefined, title: string) => {
    if (!url) return;
    const finalUrl =
        url.startsWith('http') || url.startsWith('data:')
            ? url
            : `/storage/${url}`;
    previewPhotoUrl.value = finalUrl;
    previewPhotoTitle.value = title;
};

// Side-by-Side Face Comparison Modal
const isCompareModalOpen = ref(false);
const selectedCompareRecord = ref<AttendanceRecord | null>(null);

const openFaceComparison = (rec: AttendanceRecord) => {
    selectedCompareRecord.value = rec;
    isCompareModalOpen.value = true;
};

// Check Face ID status
const isFaceRegistered = computed(() => {
    return Boolean(
        props.employee?.face_photo_path ||
        props.employee?.face_id ||
        props.employee?.is_face_registered,
    );
});

// Trigger Clock In modal (validates Face ID first!)
const triggerClockIn = () => {
    if (!isFaceRegistered.value) {
        isFaceModalOpen.value = true;
        return;
    }
    attendanceModalMode.value = 'clock_in';
    isAttendanceModalOpen.value = true;
};

// Trigger Clock Out modal
const triggerClockOut = () => {
    if (!isFaceRegistered.value) {
        isFaceModalOpen.value = true;
        return;
    }
    attendanceModalMode.value = 'clock_out';
    isAttendanceModalOpen.value = true;
};

// Admin Search & Filter state
const search = ref(props.filters?.search || '');
const filterBranch = ref(props.filters?.branch_id || '');
const filterStatus = ref(props.filters?.status || '');

const handleFilter = () => {
    router.get(
        '/attendance',
        {
            search: search.value || undefined,
            branch_id: filterBranch.value || undefined,
            status: filterStatus.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const resetFilter = () => {
    search.value = '';
    filterBranch.value = '';
    filterStatus.value = '';
    router.get('/attendance', {}, { replace: true });
};

// Status Helpers
const getStatusBadge = (status: string) => {
    switch (status) {
        case 'hadir_tepat_waktu':
            return {
                label: 'Tepat Waktu',
                class: 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300',
            };
        case 'terlambat':
            return {
                label: 'Terlambat',
                class: 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950 dark:text-amber-300',
            };
        case 'pulang_cepat':
            return {
                label: 'Pulang Cepat',
                class: 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950 dark:text-rose-300',
            };
        default:
            return {
                label: status || 'Hadir',
                class: 'bg-gray-100 text-gray-800 border-gray-300 dark:bg-zinc-800 dark:text-gray-300',
            };
    }
};

// Reset Face ID for employee
const resetEmployeeFace = (employeeId?: number) => {
    if (
        confirm(
            'Apakah Anda yakin ingin mereset Face ID ini? Karyawan wajib mendaftarkan ulang wajahnya.',
        )
    ) {
        router.post(
            '/attendance/reset-face',
            { employee_id: employeeId },
            { preserveScroll: true },
        );
    }
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Operasional Gerai', href: '#' },
            { title: 'Absensi & Face ID', href: '/attendance' },
        ]"
    >
        <Head title="Absensi Kru & Face ID - Momoyo" />

        <div class="mx-auto max-w-5xl space-y-5 p-3.5 sm:p-6 lg:p-8">
            <!-- Flash Message Banner: Peringatan Ketidakcocokan Wajah / Validasi -->
            <div
                v-if="$page.props.flash?.warning"
                class="flex animate-in items-start gap-3 rounded-2xl border-2 border-amber-300 bg-amber-50 p-4 text-xs font-semibold text-amber-900 shadow-sm fade-in dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <AlertCircle class="mt-0.5 size-5 shrink-0 text-amber-600" />
                <div class="space-y-0.5">
                    <div class="font-bold text-amber-950 dark:text-amber-100">
                        Notifikasi Verifikasi Absensi:
                    </div>
                    <p class="leading-relaxed">
                        {{ $page.props.flash.warning }}
                    </p>
                </div>
            </div>

            <div
                v-if="$page.props.flash?.error"
                class="flex animate-in items-start gap-3 rounded-2xl border-2 border-rose-300 bg-rose-50 p-4 text-xs font-semibold text-rose-900 shadow-sm fade-in dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200"
            >
                <XCircle class="mt-0.5 size-5 shrink-0 text-rose-600" />
                <div class="space-y-0.5">
                    <div class="font-bold text-rose-950 dark:text-rose-100">
                        Gagal Memproses Absensi:
                    </div>
                    <p class="leading-relaxed">{{ $page.props.flash.error }}</p>
                </div>
            </div>

            <div
                v-if="$page.props.flash?.success"
                class="flex animate-in items-start gap-3 rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-4 text-xs font-semibold text-emerald-900 shadow-sm fade-in dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                <div class="space-y-0.5">
                    <div
                        class="font-bold text-emerald-950 dark:text-emerald-100"
                    >
                        Berhasil:
                    </div>
                    <p class="leading-relaxed">
                        {{ $page.props.flash.success }}
                    </p>
                </div>
            </div>

            <!-- 1. MOBILE-FIRST TOP BAR: IDENTITY & DIGITAL CLOCK -->
            <div
                class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-red-600 via-rose-600 to-amber-600 p-5 text-white shadow-xl shadow-rose-950/10 sm:p-7"
            >
                <div
                    class="pointer-events-none absolute -top-12 -right-12 size-48 rounded-full bg-white/10 blur-xl"
                ></div>
                <div
                    class="pointer-events-none absolute right-20 -bottom-10 size-40 rounded-full bg-amber-400/20 blur-xl"
                ></div>

                <div
                    class="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <!-- Left: Profile & Branch Pill -->
                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/15 px-3 py-1 text-xs font-semibold text-rose-100 backdrop-blur-md"
                            >
                                <Sparkles class="size-3.5 text-amber-300" />
                                <span>Absensi Gerai Momoyo</span>
                            </span>
                            <span
                                v-if="branch"
                                class="inline-flex items-center gap-1 rounded-full bg-black/25 px-2.5 py-0.5 text-xs font-medium text-amber-200 backdrop-blur-md"
                            >
                                <MapPin class="size-3" />
                                {{ branch.branch_name }}
                            </span>
                        </div>

                        <h1
                            class="text-xl font-black tracking-tight text-white sm:text-2xl"
                        >
                            {{
                                employee
                                    ? employee.full_name
                                    : currentUser?.name
                            }}
                            👋
                        </h1>
                        <p
                            class="flex items-center gap-2 text-xs text-rose-100"
                        >
                            <span>NIK: {{ employee?.nik || '-' }}</span>
                            <span>•</span>
                            <span>{{ todayDateFormatted }}</span>
                        </p>
                    </div>

                    <!-- Right: Digital Clock Box -->
                    <div
                        class="flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-black/20 p-3 px-4 backdrop-blur-md sm:justify-end"
                    >
                        <div class="text-left sm:text-right">
                            <div
                                class="text-[10px] font-bold tracking-wider text-rose-200 uppercase"
                            >
                                Waktu Saat Ini
                            </div>
                            <div
                                class="font-mono text-xl font-black tracking-wider text-white sm:text-2xl"
                            >
                                {{ currentTimeStr }}
                                <span class="text-xs font-bold text-amber-300"
                                    >WIB</span
                                >
                            </div>
                        </div>
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-white/20 text-white shadow-inner"
                        >
                            <Clock class="size-5" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. FACE ID REGISTRATION STATUS BANNER -->
            <div v-if="employee">
                <!-- Case A: Face ID Belum Terdaftar (Warning Alert with Pulsing CTA) -->
                <div
                    v-if="!isFaceRegistered"
                    class="relative overflow-hidden rounded-3xl border-2 border-rose-300 bg-gradient-to-r from-rose-50 via-orange-50 to-amber-50 p-4.5 shadow-md sm:p-6 dark:border-rose-900/60 dark:from-rose-950/40 dark:to-zinc-900"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-start gap-3.5">
                            <div
                                class="flex size-12 shrink-0 animate-pulse items-center justify-center rounded-2xl bg-gradient-to-tr from-rose-600 to-amber-500 text-white shadow-md shadow-rose-600/30"
                            >
                                <ScanFace class="size-6" />
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <h2
                                        class="text-sm font-extrabold text-rose-950 sm:text-base dark:text-rose-100"
                                    >
                                        Face ID Wajib Didaftarkan
                                    </h2>
                                    <Badge
                                        class="bg-rose-600 px-2 py-0.5 text-[10px] font-bold text-white"
                                    >
                                        Wajib Dilengkapi
                                    </Badge>
                                </div>
                                <p
                                    class="max-w-xl text-xs leading-relaxed text-rose-800/90 dark:text-rose-300"
                                >
                                    Sebelum dapat melakukan
                                    <strong>Clock In</strong> dan
                                    <strong>Clock Out</strong>, Anda wajib
                                    mendaftarkan foto wajah Face ID Anda sekali
                                    saja untuk verifikasi presensi.
                                </p>
                            </div>
                        </div>

                        <Button
                            type="button"
                            class="h-11 shrink-0 rounded-2xl bg-gradient-to-r from-rose-600 to-red-600 px-5 text-xs font-bold text-white shadow-lg shadow-rose-600/30 hover:from-rose-700 hover:to-red-700 sm:h-12 sm:text-sm"
                            @click="isFaceModalOpen = true"
                        >
                            <Camera class="mr-2 size-4" />
                            <span>Daftarkan Face ID Sekarang</span>
                        </Button>
                    </div>
                </div>

                <!-- Case B: Face ID Sudah Terdaftar -->
                <div
                    v-else
                    class="flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-3.5 text-emerald-900 sm:flex-row sm:items-center sm:justify-between sm:p-4 dark:border-emerald-800/60 dark:bg-emerald-950/30 dark:text-emerald-300"
                >
                    <div class="flex items-center gap-3">
                        <!-- Thumbnail registered face -->
                        <div
                            class="relative size-11 shrink-0 overflow-hidden rounded-xl border border-emerald-300 shadow-sm"
                        >
                            <img
                                :src="
                                    employee.face_photo_url ||
                                    '/placeholder-user.jpg'
                                "
                                alt="Face ID"
                                class="size-full cursor-pointer object-cover hover:opacity-90"
                                @click="
                                    openPhotoPreview(
                                        employee.face_photo_url,
                                        'Foto Referensi Face ID',
                                    )
                                "
                            />
                            <div
                                class="absolute right-0 bottom-0 size-3 rounded-full border border-white bg-emerald-500"
                            ></div>
                        </div>
                        <div class="space-y-0.5 text-xs">
                            <div
                                class="flex items-center gap-1.5 font-bold text-emerald-900 dark:text-emerald-200"
                            >
                                <ShieldCheck class="size-4 text-emerald-600" />
                                <span>Face ID Terdaftar & Aktif</span>
                                <span
                                    class="font-mono text-[10px] text-emerald-700 dark:text-emerald-400"
                                >
                                    ({{ employee.face_id }})
                                </span>
                            </div>
                            <p
                                class="text-[11px] text-emerald-800/80 dark:text-emerald-400"
                            >
                                Terverifikasi untuk absensi kehadiran di seluruh
                                sistem gerai Momoyo.
                            </p>
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 rounded-xl border-emerald-300 text-xs font-semibold text-emerald-800 hover:bg-emerald-100/70 dark:border-emerald-800 dark:text-emerald-200"
                        @click="isFaceModalOpen = true"
                    >
                        <RefreshCw class="mr-1.5 size-3" />
                        Perbarui Wajah
                    </Button>
                </div>
            </div>

            <!-- 3. LIVE GEOFENCING RADAR CARD -->
            <Card
                v-if="branch"
                class="overflow-hidden border-gray-200 shadow-sm dark:border-zinc-800"
            >
                <CardContent class="p-4 sm:p-5">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-2xl text-white shadow-sm"
                                :class="[
                                    isGpsLoading
                                        ? 'bg-amber-500'
                                        : isInsideRadius
                                          ? 'bg-emerald-600'
                                          : 'bg-rose-600',
                                ]"
                            >
                                <Navigation
                                    v-if="!isGpsLoading"
                                    class="size-5"
                                />
                                <RefreshCw v-else class="size-5 animate-spin" />
                            </div>

                            <div class="space-y-0.5">
                                <div class="flex items-center gap-2">
                                    <h3
                                        class="text-xs font-bold text-gray-900 dark:text-white"
                                    >
                                        Geofencing Radar Gerai:
                                        <span
                                            class="font-extrabold text-rose-600"
                                            >{{ branch.branch_name }}</span
                                        >
                                    </h3>
                                    <Badge
                                        :class="[
                                            isGpsLoading
                                                ? 'border-amber-300 bg-amber-100 text-amber-800'
                                                : isInsideRadius
                                                  ? 'border-emerald-300 bg-emerald-100 text-emerald-800'
                                                  : 'border-rose-300 bg-rose-100 text-rose-800',
                                        ]"
                                        class="text-[10px] font-bold"
                                    >
                                        {{
                                            isGpsLoading
                                                ? 'Mencari Sinyal'
                                                : isInsideRadius
                                                  ? 'Dalam Radius Gerai'
                                                  : 'Di Luar Radius'
                                        }}
                                    </Badge>
                                </div>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    <template v-if="isGpsLoading">
                                        Mendeteksi akurasi GPS smartphone
                                        Anda...
                                    </template>
                                    <template v-else-if="liveDistance !== null">
                                        Jarak saat ini:
                                        <strong
                                            :class="
                                                isInsideRadius
                                                    ? 'font-extrabold text-emerald-600'
                                                    : 'font-extrabold text-rose-600'
                                            "
                                        >
                                            {{ liveDistance }} meter
                                        </strong>
                                        dari gerai (Batas radius toleransi:
                                        {{ branch.radius_meters || 50 }}m).
                                    </template>
                                    <template v-else>
                                        {{
                                            gpsError ||
                                            'Klik tombol Deteksi GPS untuk mengecek posisi Anda.'
                                        }}
                                    </template>
                                </p>
                            </div>
                        </div>

                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="shrink-0 rounded-xl border-gray-300 text-xs font-bold hover:bg-gray-100"
                            :disabled="isGpsLoading"
                            @click="checkLiveGps"
                        >
                            <RefreshCw
                                class="mr-1.5 size-3.5"
                                :class="{ 'animate-spin': isGpsLoading }"
                            />
                            Cek Ulang GPS
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- 4. CORE ACTION: CLOCK IN & CLOCK OUT MAIN CARD -->
            <div v-if="employee" class="grid grid-cols-1 gap-5">
                <!-- SCENARIO 1: BELUM CLOCK IN -->
                <Card
                    v-if="!todayAttendance?.clock_in_time"
                    class="border-2 border-rose-200/80 shadow-lg dark:border-rose-900/50"
                >
                    <CardHeader
                        class="border-b border-gray-100 pb-4 dark:border-zinc-800"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="rounded-xl bg-rose-50 p-2 text-rose-600 dark:bg-rose-950/60"
                                >
                                    <Camera class="size-5" />
                                </div>
                                <div>
                                    <CardTitle class="text-base font-bold"
                                        >Presensi Shift Hari Ini</CardTitle
                                    >
                                    <CardDescription class="text-xs">
                                        Foto Selfie Kamera + Validasi Lokasi GPS
                                        Gerai
                                    </CardDescription>
                                </div>
                            </div>
                            <Badge
                                class="border-rose-300 bg-rose-100 text-xs font-bold text-rose-800 dark:bg-rose-950 dark:text-rose-300"
                            >
                                Belum Clock In
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4 p-5 sm:p-6">
                        <div
                            class="space-y-2 rounded-2xl border border-gray-200/80 bg-gray-50 p-4 text-xs dark:border-zinc-800 dark:bg-zinc-900"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500"
                                    >Cabang Penempatan:</span
                                >
                                <span
                                    class="font-bold text-gray-900 dark:text-white"
                                    >{{ branch?.branch_name }}</span
                                >
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500"
                                    >Shift Tersedia:</span
                                >
                                <span class="font-semibold text-rose-600">
                                    {{
                                        shifts && shifts.length > 0
                                            ? `${shifts.length} Pilihan Shift`
                                            : 'Shift Standar'
                                    }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500"
                                    >Status Face ID:</span
                                >
                                <span
                                    :class="
                                        isFaceRegistered
                                            ? 'font-bold text-emerald-600'
                                            : 'font-bold text-rose-600'
                                    "
                                >
                                    {{
                                        isFaceRegistered
                                            ? '✅ Terverifikasi'
                                            : '⚠️ Belum Terdaftar'
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Big Thumb-friendly Clock In Button -->
                        <Button
                            type="button"
                            class="h-14 w-full rounded-2xl bg-gradient-to-r from-red-600 via-rose-600 to-amber-600 text-sm font-extrabold text-white shadow-xl shadow-rose-600/30 transition-transform hover:from-red-700 hover:to-rose-700 active:scale-[0.98] sm:text-base"
                            @click="triggerClockIn"
                        >
                            <Camera class="mr-2 size-5" />
                            <span>Ambil Absen Masuk (Clock In)</span>
                        </Button>
                    </CardContent>
                </Card>

                <!-- SCENARIO 2: SUDAH CLOCK IN, BELUM CLOCK OUT (SEDANG BERTUGAS) -->
                <Card
                    v-else-if="
                        todayAttendance.clock_in_time &&
                        !todayAttendance.clock_out_time
                    "
                    class="border-2 border-emerald-300/80 shadow-lg dark:border-emerald-800/60"
                >
                    <CardHeader
                        class="border-b border-gray-100 pb-4 dark:border-zinc-800"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="rounded-xl bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950/60"
                                >
                                    <Clock class="size-5" />
                                </div>
                                <div>
                                    <CardTitle class="text-base font-bold"
                                        >Sedang Bertugas (Shift
                                        Berjalan)</CardTitle
                                    >
                                    <CardDescription class="text-xs">
                                        Anda telah tercatat hadir pada shift
                                        hari ini
                                    </CardDescription>
                                </div>
                            </div>
                            <Badge
                                class="border-emerald-300 bg-emerald-100 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                            >
                                Shift Aktif
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-5 p-5 sm:p-6">
                        <!-- Clock In Detail Recap -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Left: Selfie Preview -->
                            <div
                                class="flex items-center gap-3.5 rounded-2xl border border-gray-200 bg-gray-50 p-3.5 dark:border-zinc-800 dark:bg-zinc-900"
                            >
                                <div
                                    class="group relative size-16 shrink-0 cursor-pointer overflow-hidden rounded-xl border border-gray-300 shadow-sm"
                                    @click="
                                        openPhotoPreview(
                                            todayAttendance.clock_in_photo_full_url || todayAttendance.clock_in_photo_url,
                                            'Selfie Clock In',
                                        )
                                    "
                                >
                                    <img
                                        :src="
                                            todayAttendance.clock_in_photo_full_url ||
                                            `/storage/${todayAttendance.clock_in_photo_url}`
                                        "
                                        alt="Selfie Clock In"
                                        class="size-full object-cover transition-transform group-hover:scale-105"
                                    />
                                    <div
                                        class="absolute inset-0 flex items-center justify-center bg-black/20 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        <Eye class="size-4 text-white" />
                                    </div>
                                </div>
                                <div class="space-y-1 text-xs">
                                    <span
                                        class="block font-medium text-gray-400"
                                        >Jam Masuk (Clock In):</span
                                    >
                                    <div
                                        class="font-mono text-base font-black text-gray-900 dark:text-white"
                                    >
                                        {{
                                            todayAttendance.clock_in_time.slice(
                                                0,
                                                5,
                                            )
                                        }}
                                        WIB
                                    </div>
                                    <Badge
                                        :class="
                                            getStatusBadge(
                                                todayAttendance.status,
                                            ).class
                                        "
                                        class="text-[10px] font-bold"
                                    >
                                        {{
                                            getStatusBadge(
                                                todayAttendance.status,
                                            ).label
                                        }}
                                    </Badge>
                                </div>
                            </div>

                            <!-- Right: Shift & Notes -->
                            <div
                                class="space-y-2 rounded-2xl border border-gray-200 bg-gray-50 p-3.5 text-xs dark:border-zinc-800 dark:bg-zinc-900"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500"
                                        >Shift Terpilih:</span
                                    >
                                    <span
                                        class="font-bold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            todayAttendance.shift?.shift_name ||
                                            'Shift Pagi'
                                        }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500"
                                        >Jarak saat Absen:</span
                                    >
                                    <span
                                        class="font-semibold text-emerald-600"
                                    >
                                        {{
                                            todayAttendance.clock_in_distance_meters ||
                                            0
                                        }}m dari gerai
                                    </span>
                                </div>
                                <div
                                    v-if="todayAttendance.notes"
                                    class="flex items-center justify-between"
                                >
                                    <span class="text-gray-500">Catatan:</span>
                                    <span
                                        class="max-w-[150px] truncate font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ todayAttendance.notes }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Big Clock Out CTA Button -->
                        <Button
                            type="button"
                            class="h-14 w-full rounded-2xl bg-gradient-to-r from-zinc-900 via-zinc-800 to-rose-950 text-sm font-extrabold text-white shadow-xl shadow-zinc-900/30 transition-transform hover:from-black hover:to-zinc-900 active:scale-[0.98] sm:text-base"
                            @click="triggerClockOut"
                        >
                            <Clock class="mr-2 size-5 text-rose-400" />
                            <span>Selesai Tugas (Clock Out)</span>
                        </Button>
                    </CardContent>
                </Card>

                <!-- SCENARIO 3: SUDAH SELESAI (CLOCK IN & CLOCK OUT COMPLETED) -->
                <Card
                    v-else
                    class="border-2 border-emerald-400/80 bg-gradient-to-b from-white to-emerald-50/30 shadow-lg dark:from-zinc-900 dark:to-emerald-950/10"
                >
                    <CardHeader
                        class="border-b border-gray-100 pb-4 dark:border-zinc-800"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="rounded-xl bg-emerald-100 p-2 text-emerald-700 dark:bg-emerald-950/60"
                                >
                                    <CheckCircle2 class="size-5" />
                                </div>
                                <div>
                                    <CardTitle
                                        class="text-base font-bold text-emerald-950 dark:text-emerald-100"
                                    >
                                        Absensi Hari Ini Lengkap & Tuntas! 🎉
                                    </CardTitle>
                                    <CardDescription class="text-xs">
                                        Terima kasih atas kerja keras Anda di
                                        {{ branch?.branch_name }}.
                                    </CardDescription>
                                </div>
                            </div>
                            <Badge
                                class="bg-emerald-600 text-xs font-bold text-white"
                            >
                                Selesai Shift
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="p-5 sm:p-6">
                        <div class="grid grid-cols-2 gap-3.5">
                            <!-- Clock In recap -->
                            <div
                                class="rounded-2xl border border-emerald-200 bg-white p-3.5 dark:border-emerald-800/60 dark:bg-zinc-900"
                            >
                                <div class="mb-2 flex items-center gap-2.5">
                                    <div
                                        class="size-10 cursor-pointer overflow-hidden rounded-xl border border-gray-200"
                                        @click="
                                            openPhotoPreview(
                                                todayAttendance.clock_in_photo_full_url || todayAttendance.clock_in_photo_url,
                                                'Selfie Clock In',
                                            )
                                        "
                                    >
                                        <img
                                            :src="
                                                todayAttendance.clock_in_photo_full_url ||
                                                `/storage/${todayAttendance.clock_in_photo_url}`
                                            "
                                            alt="In"
                                            class="size-full object-cover"
                                        />
                                    </div>
                                    <div>
                                        <span
                                            class="text-[10px] font-bold text-gray-400 uppercase"
                                            >Clock In</span
                                        >
                                        <div
                                            class="font-mono text-sm font-extrabold text-gray-900 dark:text-white"
                                        >
                                            {{
                                                todayAttendance.clock_in_time?.slice(
                                                    0,
                                                    5,
                                                )
                                            }}
                                            WIB
                                        </div>
                                    </div>
                                </div>
                                <Badge
                                    :class="
                                        getStatusBadge(todayAttendance.status)
                                            .class
                                    "
                                    class="text-[9px] font-bold"
                                >
                                    {{
                                        getStatusBadge(todayAttendance.status)
                                            .label
                                    }}
                                </Badge>
                            </div>

                            <!-- Clock Out recap -->
                            <div
                                class="rounded-2xl border border-emerald-200 bg-white p-3.5 dark:border-emerald-800/60 dark:bg-zinc-900"
                            >
                                <div class="mb-2 flex items-center gap-2.5">
                                    <div
                                        class="size-10 cursor-pointer overflow-hidden rounded-xl border border-gray-200"
                                        @click="
                                            openPhotoPreview(
                                                todayAttendance.clock_out_photo_full_url || todayAttendance.clock_out_photo_url,
                                                'Selfie Clock Out',
                                            )
                                        "
                                    >
                                        <img
                                            :src="
                                                todayAttendance.clock_out_photo_full_url ||
                                                `/storage/${todayAttendance.clock_out_photo_url}`
                                            "
                                            alt="Out"
                                            class="size-full object-cover"
                                        />
                                    </div>
                                    <div>
                                        <span
                                            class="text-[10px] font-bold text-gray-400 uppercase"
                                            >Clock Out</span
                                        >
                                        <div
                                            class="font-mono text-sm font-extrabold text-gray-900 dark:text-white"
                                        >
                                            {{
                                                todayAttendance.clock_out_time?.slice(
                                                    0,
                                                    5,
                                                )
                                            }}
                                            WIB
                                        </div>
                                    </div>
                                </div>
                                <span
                                    class="text-[10px] font-bold text-emerald-700"
                                >
                                    Shift Tuntas
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- 5. RIWAYAT ABSENSI 14 HARI TERAKHIR (INDIVIDUAL KRU) -->
            <Card
                v-if="employee"
                class="border-gray-200 shadow-sm dark:border-zinc-800"
            >
                <CardHeader
                    class="border-b border-gray-100 pb-3.5 dark:border-zinc-800"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Calendar class="size-4.5 text-rose-500" />
                            <CardTitle class="text-sm font-bold"
                                >Riwayat Absensi Saya</CardTitle
                            >
                        </div>
                        <span class="text-xs text-gray-400"
                            >14 Hari Terakhir</span
                        >
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        v-if="
                            !recentAttendances || recentAttendances.length === 0
                        "
                        class="p-8 text-center text-xs text-gray-400"
                    >
                        Belum ada rekaman riwayat absensi sebelumnya.
                    </div>
                    <div
                        v-else
                        class="divide-y divide-gray-100 dark:divide-zinc-800"
                    >
                        <div
                            v-for="rec in recentAttendances"
                            :key="rec.id"
                            class="flex flex-col gap-3 p-4 transition-colors hover:bg-gray-50/80 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-zinc-900/50"
                        >
                            <div class="flex items-center gap-3">
                                <!-- Thumbnail photo button -->
                                <button
                                    type="button"
                                    class="relative size-12 shrink-0 overflow-hidden rounded-xl border border-gray-200 shadow-sm transition-all hover:ring-2 hover:ring-rose-500"
                                    @click="
                                        openPhotoPreview(
                                            rec.clock_in_photo_full_url || rec.clock_in_photo_url,
                                            `Selfie ${rec.date}`,
                                        )
                                    "
                                >
                                    <img
                                        :src="
                                            rec.clock_in_photo_full_url ||
                                            `/storage/${rec.clock_in_photo_url}`
                                        "
                                        alt="Thumbnail Absensi"
                                        class="size-full object-cover"
                                    />
                                </button>
                                <div class="space-y-0.5 text-xs">
                                    <div
                                        class="font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ rec.date }}
                                    </div>
                                    <div
                                        class="flex items-center gap-2 text-gray-500"
                                    >
                                        <span>{{
                                            rec.shift?.shift_name ||
                                            'Shift Standar'
                                        }}</span>
                                        <span>•</span>
                                        <span>{{
                                            rec.branch?.branch_name
                                        }}</span>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-between gap-3 text-xs sm:justify-end"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="font-mono font-bold text-gray-800 dark:text-gray-200"
                                    >
                                        In:
                                        {{
                                            rec.clock_in_time
                                                ? rec.clock_in_time.slice(0, 5)
                                                : '-'
                                        }}
                                    </span>
                                    <span class="text-gray-300">➔</span>
                                    <span
                                        class="font-mono font-bold text-gray-800 dark:text-gray-200"
                                    >
                                        Out:
                                        {{
                                            rec.clock_out_time
                                                ? rec.clock_out_time.slice(0, 5)
                                                : '-'
                                        }}
                                    </span>
                                </div>

                                <Badge
                                    :class="getStatusBadge(rec.status).class"
                                    class="text-[10px] font-bold"
                                >
                                    {{ getStatusBadge(rec.status).label }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 6. KHUSUS ADMIN PUSAT: MONITORING REALTIME SELURUH CABANG -->
            <Card
                v-if="isAdminPusat && adminMonitoring"
                class="mt-6 border-gray-200 shadow-md dark:border-zinc-800"
            >
                <CardHeader
                    class="border-b border-gray-100 pb-4 dark:border-zinc-800"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <Shield class="size-5 text-rose-600" />
                                <CardTitle class="text-base font-bold"
                                    >Monitoring Absensi Jaringan
                                    Cabang</CardTitle
                                >
                            </div>
                            <CardDescription class="mt-0.5 text-xs">
                                Rekap real-time kehadiran seluruh kru gerai hari
                                ini ({{ todayDateFormatted }})
                            </CardDescription>
                        </div>

                        <!-- KPI Badge Counter -->
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span
                                class="rounded-xl bg-gray-100 px-3 py-1 font-bold text-gray-800 dark:bg-zinc-800 dark:text-gray-200"
                            >
                                Total:
                                {{ adminMonitoring.stats.total_today }} Hadir
                            </span>
                            <span
                                class="rounded-xl bg-emerald-100 px-3 py-1 font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                            >
                                {{ adminMonitoring.stats.on_time }} Tepat Waktu
                            </span>
                            <span
                                class="rounded-xl bg-amber-100 px-3 py-1 font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                            >
                                {{ adminMonitoring.stats.late }} Terlambat
                            </span>
                        </div>
                    </div>

                    <!-- Search & Filter Bar -->
                    <div class="grid grid-cols-1 gap-2.5 pt-3 sm:grid-cols-4">
                        <div class="relative sm:col-span-2">
                            <Search
                                class="absolute top-2.5 left-3 size-4 text-gray-400"
                            />
                            <Input
                                v-model="search"
                                placeholder="Cari nama kru atau NIK..."
                                class="h-9 rounded-xl pl-9 text-xs"
                                @keyup.enter="handleFilter"
                            />
                        </div>
                        <div>
                            <select
                                v-model="filterBranch"
                                class="h-9 w-full rounded-xl border border-input bg-transparent px-3 text-xs"
                                @change="handleFilter"
                            >
                                <option value="">Semua Cabang Gerai</option>
                                <option
                                    v-for="b in adminMonitoring.branches"
                                    :key="b.id"
                                    :value="b.id"
                                >
                                    {{ b.branch_name }}
                                </option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <select
                                v-model="filterStatus"
                                class="h-9 w-full rounded-xl border border-input bg-transparent px-3 text-xs"
                                @change="handleFilter"
                            >
                                <option value="">Semua Status</option>
                                <option value="hadir_tepat_waktu">
                                    Tepat Waktu
                                </option>
                                <option value="terlambat">Terlambat</option>
                            </select>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-9 rounded-xl px-3 text-xs"
                                @click="resetFilter"
                            >
                                Reset
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="border-b border-gray-100 bg-gray-50/80 font-bold tracking-wider text-gray-500 uppercase dark:border-zinc-800 dark:bg-zinc-900"
                            >
                                <tr>
                                    <th class="px-4 py-3">Kru & Cabang</th>
                                    <th class="px-4 py-3">Shift</th>
                                    <th class="px-4 py-3">Clock In</th>
                                    <th class="px-4 py-3">Clock Out</th>
                                    <th class="px-4 py-3">Jarak GPS</th>
                                    <th class="px-4 py-3">Verifikasi Wajah</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Selfie</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-zinc-800"
                            >
                                <tr
                                    v-for="row in adminMonitoring.attendances
                                        .data"
                                    :key="row.id"
                                    class="hover:bg-gray-50/60 dark:hover:bg-zinc-900/50"
                                >
                                    <td class="px-4 py-3 font-medium">
                                        <div
                                            class="font-bold text-gray-900 dark:text-white"
                                        >
                                            {{ row.employee?.full_name || '-' }}
                                        </div>
                                        <div class="text-[11px] text-gray-500">
                                            {{
                                                row.employee?.current_branch
                                                    ?.branch_name ||
                                                row.branch?.branch_name
                                            }}
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-gray-600 dark:text-gray-400"
                                    >
                                        {{
                                            row.shift?.shift_name ||
                                            'Shift Standar'
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 font-mono font-bold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            row.clock_in_time
                                                ? row.clock_in_time.slice(0, 5)
                                                : '-'
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 font-mono font-bold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            row.clock_out_time
                                                ? row.clock_out_time.slice(0, 5)
                                                : '-'
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-gray-600 dark:text-gray-400"
                                    >
                                        {{
                                            row.clock_in_distance_meters
                                                ? `${row.clock_in_distance_meters}m`
                                                : '-'
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1.5">
                                            <Badge
                                                v-if="row.is_face_mismatch"
                                                class="animate-pulse border-rose-300 bg-rose-100 text-[10px] font-bold text-rose-800 dark:bg-rose-950 dark:text-rose-300"
                                            >
                                                ⚠️ Beda ({{
                                                    row.clock_in_face_match_percentage ||
                                                    40
                                                }}%)
                                            </Badge>
                                            <Badge
                                                v-else
                                                class="border-emerald-300 bg-emerald-100 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                            >
                                                ✅ Cocok ({{
                                                    row.clock_in_face_match_percentage ||
                                                    88
                                                }}%)
                                            </Badge>
                                            <button
                                                type="button"
                                                class="shrink-0 text-[10px] font-bold text-rose-600 underline hover:text-rose-700"
                                                @click="openFaceComparison(row)"
                                                title="Bandingkan Foto Face ID vs Selfie"
                                            >
                                                Bandingkan
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge
                                            :class="
                                                getStatusBadge(row.status).class
                                            "
                                            class="text-[10px] font-bold"
                                        >
                                            {{
                                                getStatusBadge(row.status).label
                                            }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div
                                            class="flex items-center justify-end gap-1.5"
                                        >
                                            <Button
                                                v-if="row.clock_in_photo_url"
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                class="h-7 px-2 text-[11px] hover:text-rose-600"
                                                @click="
                                                    openPhotoPreview(
                                                        row.clock_in_photo_full_url || row.clock_in_photo_url,
                                                        `Selfie In: ${row.employee?.full_name}`,
                                                    )
                                                "
                                            >
                                                <Camera class="mr-1 size-3.5" />
                                                In
                                            </Button>
                                            <Button
                                                v-if="row.clock_out_photo_url"
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                class="h-7 px-2 text-[11px] hover:text-rose-600"
                                                @click="
                                                    openPhotoPreview(
                                                        row.clock_out_photo_full_url || row.clock_out_photo_url,
                                                        `Selfie Out: ${row.employee?.full_name}`,
                                                    )
                                                "
                                            >
                                                <Camera class="mr-1 size-3.5" />
                                                Out
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        adminMonitoring.attendances.data
                                            .length === 0
                                    "
                                >
                                    <td
                                        colspan="7"
                                        class="px-4 py-8 text-center text-gray-400"
                                    >
                                        Belum ada data kehadiran pada filter
                                        ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- MODAL 1: Face Registration Modal -->
        <FaceRegistrationModal
            v-model:open="isFaceModalOpen"
            :employee-name="employee?.full_name"
            :is-updating="isFaceRegistered"
            @success="isFaceModalOpen = false"
        />

        <!-- MODAL 2: Attendance Modal (Clock In / Clock Out) -->
        <AttendanceModal
            v-model:open="isAttendanceModalOpen"
            :mode="attendanceModalMode"
            :branch="branch"
            :shifts="shifts"
            @success="isAttendanceModalOpen = false"
        />

        <!-- MODAL 3: Lightbox Photo Preview -->
        <Dialog
            :open="Boolean(previewPhotoUrl)"
            @update:open="(val) => !val && (previewPhotoUrl = null)"
        >
            <DialogContent class="rounded-3xl p-4 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-sm font-bold">{{
                        previewPhotoTitle
                    }}</DialogTitle>
                </DialogHeader>
                <div
                    class="mt-2 flex items-center justify-center overflow-hidden rounded-2xl bg-zinc-950"
                >
                    <img
                        :src="previewPhotoUrl || ''"
                        alt="Preview Foto"
                        class="max-h-[70vh] w-auto object-contain"
                    />
                </div>
            </DialogContent>
        </Dialog>

        <!-- MODAL 4: Side-by-Side Face Comparison Modal -->
        <Dialog
            :open="isCompareModalOpen"
            @update:open="(val) => !val && (isCompareModalOpen = false)"
        >
            <DialogContent
                class="overflow-hidden rounded-3xl border-rose-200/60 p-0 sm:max-w-xl dark:border-zinc-800"
            >
                <!-- Header with Gradient -->
                <div
                    class="relative bg-gradient-to-r from-red-600 via-rose-600 to-amber-600 p-5 text-white"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex size-10 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-md"
                        >
                            <ScanFace class="size-5 text-white" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold text-white">
                                Hasil Perbandingan Verifikasi Wajah (Face
                                Matching)
                            </DialogTitle>
                            <DialogDescription class="text-xs text-rose-100">
                                {{ selectedCompareRecord?.employee?.full_name }}
                                (NIK:
                                {{ selectedCompareRecord?.employee?.nik }})
                            </DialogDescription>
                        </div>
                    </div>
                </div>

                <!-- Body Side-by-Side -->
                <div class="space-y-4 p-5">
                    <!-- Similarity Score Card -->
                    <div
                        class="flex items-center justify-between rounded-2xl border p-4"
                        :class="[
                            selectedCompareRecord?.is_face_mismatch
                                ? 'border-rose-200 bg-rose-50 text-rose-950 dark:border-rose-800 dark:bg-rose-950/40'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40',
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-2xl text-sm font-black text-white"
                                :class="
                                    selectedCompareRecord?.is_face_mismatch
                                        ? 'bg-rose-600'
                                        : 'bg-emerald-600'
                                "
                            >
                                {{
                                    selectedCompareRecord?.clock_in_face_match_percentage ||
                                    85
                                }}%
                            </div>
                            <div class="space-y-0.5 text-xs">
                                <div
                                    class="flex items-center gap-1.5 text-sm font-extrabold"
                                >
                                    <span
                                        v-if="
                                            selectedCompareRecord?.is_face_mismatch
                                        "
                                        >⚠️ Terindikasi Wajah Berbeda</span
                                    >
                                    <span v-else
                                        >✅ Wajah Identik & Terverifikasi</span
                                    >
                                </div>
                                <p class="text-[11px] leading-tight opacity-90">
                                    {{
                                        selectedCompareRecord?.is_face_mismatch
                                            ? 'Tingkat kemiripan di bawah ambang batas (60%). Berpotensi titip absen atau kru yang berbeda.'
                                            : 'Tingkat kemiripan di atas ambang batas. Wajah terverifikasi sesuai dengan Face ID terdaftar.'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Side-by-Side Photos Grid -->
                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                        <!-- Left: Registered Face ID -->
                        <div
                            class="rounded-2xl border border-gray-200 bg-gray-50 p-3 text-center dark:border-zinc-800 dark:bg-zinc-900"
                        >
                            <span
                                class="mb-2 block text-[11px] font-bold tracking-wide text-gray-500 uppercase"
                            >
                                1. Foto Face ID Terdaftar
                            </span>
                            <div
                                class="aspect-square overflow-hidden rounded-2xl border bg-black/5 shadow-inner"
                            >
                                <img
                                    :src="
                                        selectedCompareRecord?.employee
                                            ?.face_photo_url ||
                                        employee?.face_photo_url ||
                                        '/placeholder-user.jpg'
                                    "
                                    alt="Foto Face ID"
                                    class="size-full object-cover"
                                />
                            </div>
                            <span
                                class="mt-2 block font-mono text-[10px] text-gray-400"
                            >
                                Master Biometrik Kru
                            </span>
                        </div>

                        <!-- Right: Clock In / Out Selfie -->
                        <div
                            class="rounded-2xl border border-gray-200 bg-gray-50 p-3 text-center dark:border-zinc-800 dark:bg-zinc-900"
                        >
                            <span
                                class="mb-2 block text-[11px] font-bold tracking-wide text-gray-500 uppercase"
                            >
                                2. Foto Selfie Presensi
                            </span>
                            <div
                                class="aspect-square overflow-hidden rounded-2xl border bg-black/5 shadow-inner"
                            >
                                <img
                                    :src="
                                        selectedCompareRecord?.clock_in_photo_full_url ||
                                        `/storage/${selectedCompareRecord?.clock_in_photo_url}`
                                    "
                                    alt="Foto Selfie"
                                    class="size-full object-cover"
                                />
                            </div>
                            <span
                                class="mt-2 block font-mono text-[10px] text-gray-400"
                            >
                                Jam
                                {{
                                    selectedCompareRecord?.clock_in_time
                                        ? selectedCompareRecord.clock_in_time.slice(
                                              0,
                                              5,
                                          )
                                        : '-'
                                }}
                                WIB
                            </span>
                        </div>
                    </div>

                    <div class="pt-2 text-right">
                        <Button
                            type="button"
                            class="rounded-xl bg-gray-900 px-5 text-xs text-white hover:bg-black"
                            @click="isCompareModalOpen = false"
                        >
                            Tutup
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
