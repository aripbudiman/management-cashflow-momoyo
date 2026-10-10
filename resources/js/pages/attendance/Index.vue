<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowRight,
    Building2,
    Calendar,
    Check,
    CheckCircle,
    CheckCircle2,
    ChevronDown,
    Clock,
    Copy,
    ExternalLink,
    Eye,
    FileText,
    Filter,
    HelpCircle,
    MapPin,
    MoreHorizontal,
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useGeolocation } from '@/composables/useGeolocation';

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
    clock_out_long?: number | null;
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
    todaySchedule?: {
        is_off: boolean;
        shift_name: string | null;
        start_time: string | null;
        end_time: string | null;
    } | null;
    schedulePeriod?: Array<{
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
        attendance: {
            status: string;
            clock_in_time: string | null;
            clock_out_time: string | null;
            clock_in_photo_url: string | null;
            clock_out_photo_url: string | null;
            clock_in_distance_meters: number | null;
            clock_out_distance_meters: number | null;
            notes: string | null;
        } | null;
    }>;
    periodLabel?: string;
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
        date?: string;
    };
}>();

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const isAdminPusat = computed(
    () => Boolean(props.adminMonitoring) || currentUser.value?.role?.name === 'admin_pusat',
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

// Detail Modal State
const isDetailModalOpen = ref(false);
const selectedDetailRecord = ref<AttendanceRecord | null>(null);

const openAttendanceDetail = (rec: AttendanceRecord) => {
    selectedDetailRecord.value = rec;
    isDetailModalOpen.value = true;
};

const copiedCoordType = ref<string | null>(null);
const copyCoordinates = (
    lat: number | null,
    long: number | null,
    type: string,
) => {
    if (lat === null || long === null) return;
    navigator.clipboard.writeText(`${lat}, ${long}`);
    copiedCoordType.value = type;
    setTimeout(() => {
        copiedCoordType.value = null;
    }, 2000);
};

// Admin Search & Filter state
const search = ref(props.filters?.search || '');
const filterBranch = ref(props.filters?.branch_id || '');
const filterStatus = ref(props.filters?.status || '');
const filterDate = ref(props.filters?.date || '');

const handleFilter = () => {
    router.get(
        '/attendance',
        {
            search: search.value || undefined,
            branch_id: filterBranch.value || undefined,
            status: filterStatus.value || undefined,
            date: filterDate.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const resetFilter = () => {
    search.value = '';
    filterBranch.value = '';
    filterStatus.value = '';
    filterDate.value = '';
    router.get('/attendance', {}, { replace: true });
};

// Status Helpers
const expandedDate = ref<string | null>(null);
const toggleDay = (date: string, hasAttendance: boolean) => {
    if (!hasAttendance) return;
    expandedDate.value = expandedDate.value === date ? null : date;
};

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
    <Head title="Absensi Kru & Face ID - Momoyo" />

    <div class="w-full flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
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

            <!-- 2. KHUSUS ADMIN PUSAT: MONITORING REALTIME SELURUH CABANG -->
            <Card
                v-if="isAdminPusat && adminMonitoring"
                class="border-gray-200 shadow-md dark:border-zinc-800"
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
                    <div class="grid grid-cols-1 gap-2.5 pt-3 sm:grid-cols-12">
                        <div class="relative sm:col-span-4">
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
                        <div class="sm:col-span-3">
                            <select
                                v-model="filterBranch"
                                class="h-9 w-full rounded-xl border border-input bg-background px-3 text-xs text-foreground focus:ring-2 focus:ring-rose-500/20"
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
                        <div class="sm:col-span-2">
                            <select
                                v-model="filterDate"
                                class="h-9 w-full rounded-xl border border-input bg-background px-3 text-xs text-foreground focus:ring-2 focus:ring-rose-500/20"
                                @change="handleFilter"
                            >
                                <option value="">Hari Ini ({{ todayDate }})</option>
                                <option value="all">Semua Tanggal</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <select
                                v-model="filterStatus"
                                class="h-9 w-full rounded-xl border border-input bg-background px-3 text-xs text-foreground focus:ring-2 focus:ring-rose-500/20"
                                @change="handleFilter"
                            >
                                <option value="">Semua Status</option>
                                <option value="hadir_tepat_waktu">
                                    Tepat Waktu
                                </option>
                                <option value="terlambat">Terlambat</option>
                            </select>
                        </div>
                        <div class="sm:col-span-1 flex gap-1.5">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-9 w-full rounded-xl px-2.5 text-xs font-semibold hover:border-rose-300 hover:text-rose-600"
                                @click="resetFilter"
                                title="Reset Filter"
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
                                    <th class="px-5 py-3.5 whitespace-nowrap min-w-[200px]">Kru & Cabang</th>
                                    <th class="px-5 py-3.5 whitespace-nowrap min-w-[140px]">Tanggal & Shift</th>
                                    <th class="px-5 py-3.5 whitespace-nowrap text-center min-w-[110px]">Clock In</th>
                                    <th class="px-5 py-3.5 whitespace-nowrap text-center min-w-[110px]">Clock Out</th>
                                    <th class="px-5 py-3.5 whitespace-nowrap text-center min-w-[100px]">Jarak GPS</th>
                                    <th class="px-5 py-3.5 whitespace-nowrap min-w-[180px]">Verifikasi Wajah</th>
                                    <th class="px-5 py-3.5 whitespace-nowrap text-center min-w-[130px]">Status</th>
                                    <th class="px-5 py-3.5 whitespace-nowrap text-right min-w-[100px]">Aksi</th>
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
                                    <td class="px-5 py-3.5 whitespace-nowrap font-medium">
                                        <div
                                            class="text-sm font-bold text-gray-900 dark:text-white"
                                        >
                                            {{ row.employee?.full_name || '-' }}
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-1.5 text-xs font-normal text-gray-500">
                                            <span class="font-mono text-[11px] text-gray-400">{{ row.employee?.nik || '-' }}</span>
                                            <span>•</span>
                                            <span class="inline-flex items-center gap-1 font-medium text-rose-600 dark:text-rose-400">
                                                <Building2 class="size-3" />
                                                {{
                                                    row.employee?.current_branch
                                                        ?.branch_name ||
                                                    row.branch?.branch_name ||
                                                    'Gerai Pusat'
                                                }}
                                            </span>
                                        </div>
                                    </td>
                                    <td
                                        class="px-5 py-3.5 whitespace-nowrap font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        <div class="font-bold text-gray-800 dark:text-gray-200">
                                            {{ row.date }}
                                        </div>
                                        <div class="text-[11px] text-gray-400">
                                            {{
                                                row.shift?.shift_name ||
                                                'Shift Standar'
                                            }}
                                        </div>
                                    </td>
                                    <td
                                        class="px-5 py-3.5 whitespace-nowrap text-center font-mono text-sm font-bold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            row.clock_in_time
                                                ? row.clock_in_time.slice(0, 5)
                                                : '-'
                                        }}
                                        <span v-if="row.clock_in_time" class="ml-0.5 text-[10px] font-normal text-gray-400">WIB</span>
                                    </td>
                                    <td
                                        class="px-5 py-3.5 whitespace-nowrap text-center font-mono text-sm font-bold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            row.clock_out_time
                                                ? row.clock_out_time.slice(0, 5)
                                                : '-'
                                        }}
                                        <span v-if="row.clock_out_time" class="ml-0.5 text-[10px] font-normal text-gray-400">WIB</span>
                                    </td>
                                    <td
                                        class="px-5 py-3.5 whitespace-nowrap text-center font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        <span
                                            v-if="
                                                row.clock_in_distance_meters !==
                                                    null &&
                                                row.clock_in_distance_meters !==
                                                    undefined
                                            "
                                            class="inline-flex items-center gap-1 font-semibold"
                                        >
                                            {{ row.clock_in_distance_meters }}m
                                        </span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <Badge
                                                v-if="row.is_face_mismatch"
                                                class="animate-pulse border-rose-300 bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-800 dark:bg-rose-950 dark:text-rose-300"
                                            >
                                                ⚠️ Beda ({{
                                                    row.clock_in_face_match_percentage ||
                                                    40
                                                }}%)
                                            </Badge>
                                            <Badge @click="openFaceComparison(row)"
                                                v-else
                                                class="cursor-pointer border-emerald-300 bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-950 dark:text-emerald-300"
                                            >
                                                ✅ Cocok ({{
                                                    row.clock_in_face_match_percentage ||
                                                    88
                                                }}%)
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-center">
                                        <Badge
                                            :class="
                                                getStatusBadge(row.status).class
                                            "
                                            class="px-2.5 py-0.5 text-xs font-bold"
                                        >
                                            {{
                                                getStatusBadge(row.status).label
                                            }}
                                        </Badge>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-8 gap-1.5 rounded-xl border-gray-200 px-3 text-xs font-bold text-gray-700 shadow-sm transition-all hover:border-rose-400 hover:bg-rose-50 hover:text-rose-700 dark:border-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-800"
                                                >
                                                    <span>Aksi</span>
                                                    <ChevronDown class="size-3.5" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-48 rounded-2xl p-1.5 shadow-xl">
                                                <DropdownMenuItem
                                                    class="cursor-pointer gap-2 rounded-xl text-xs font-bold text-gray-800 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:text-gray-200 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"
                                                    @click="openAttendanceDetail(row)"
                                                >
                                                    <Eye class="size-4 text-rose-500" />
                                                    <span>Detail Absensi</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    class="cursor-pointer gap-2 rounded-xl text-xs font-medium text-gray-700 transition-colors hover:bg-emerald-50 hover:text-emerald-700 dark:text-gray-200 dark:hover:bg-emerald-950/40"
                                                    @click="openFaceComparison(row)"
                                                >
                                                    <ScanFace class="size-4 text-emerald-500" />
                                                    <span>Bandingkan Wajah</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator v-if="row.clock_in_photo_url || row.clock_out_photo_url" />
                                                <DropdownMenuItem
                                                    v-if="row.clock_in_photo_url"
                                                    class="cursor-pointer gap-2 rounded-xl text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300"
                                                    @click="openPhotoPreview(row.clock_in_photo_full_url || row.clock_in_photo_url, `Selfie In: ${row.employee?.full_name}`)"
                                                >
                                                    <Camera class="size-4 text-gray-400" />
                                                    <span>Lihat Foto In</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="row.clock_out_photo_url"
                                                    class="cursor-pointer gap-2 rounded-xl text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300"
                                                    @click="openPhotoPreview(row.clock_out_photo_full_url || row.clock_out_photo_url, `Selfie Out: ${row.employee?.full_name}`)"
                                                >
                                                    <Camera class="size-4 text-gray-400" />
                                                    <span>Lihat Foto Out</span>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        adminMonitoring.attendances.data
                                            .length === 0
                                    "
                                >
                                    <td
                                        colspan="8"
                                        class="px-5 py-10 text-center text-sm text-gray-400"
                                    >
                                        Belum ada data kehadiran pada filter
                                        ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Bar -->
                    <div
                        v-if="adminMonitoring.attendances.last_page > 1"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-4 text-xs dark:border-zinc-800"
                    >
                        <span class="text-gray-500">
                            Menampilkan halaman {{ adminMonitoring.attendances.current_page }} dari {{ adminMonitoring.attendances.last_page }} (Total {{ adminMonitoring.attendances.total }} data)
                        </span>
                        <div class="flex items-center gap-1">
                            <Link
                                v-for="(link, idx) in adminMonitoring.attendances.links"
                                :key="idx"
                                :href="link.url || '#'"
                                :class="[
                                    'rounded-lg px-2.5 py-1 text-xs font-medium transition-colors',
                                    link.active
                                        ? 'bg-rose-600 font-bold text-white shadow'
                                        : link.url
                                          ? 'border border-gray-200 bg-white text-gray-700 hover:bg-rose-50 hover:text-rose-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300'
                                          : 'cursor-not-allowed text-gray-300 dark:text-zinc-700',
                                ]"
                                v-html="link.label"
                                :preserve-scroll="true"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 3. FACE ID REGISTRATION STATUS BANNER -->
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

            <!-- 4b. PERIODE ABSENSI BULAN BERJALAN + JADWAL SHIFT -->
            <Card
                v-if="employee && schedulePeriod && schedulePeriod.length"
                class="border-gray-200 shadow-sm dark:border-zinc-800"
            >
                <CardHeader
                    class="border-b border-gray-100 pb-3.5 dark:border-zinc-800"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Calendar class="size-4.5 text-rose-500" />
                            <CardTitle class="text-sm font-bold"
                                >Periode Absensi & Jadwal Shift</CardTitle
                            >
                        </div>
                        <span class="text-xs text-gray-400">{{
                            periodLabel
                        }}</span>
                    </div>
                    <div
                        v-if="todaySchedule?.is_off"
                        class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs font-bold text-red-600 dark:bg-red-950/40"
                    >
                        Hari ini jadwal libur Anda — absensi dinonaktifkan.
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                        <div v-for="d in schedulePeriod" :key="d.date">
                        <div
                            class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs"
                            :class="[
                                d.is_today ? 'bg-rose-50/60 dark:bg-rose-950/20' : '',
                                d.is_off ? 'bg-red-50/60 dark:bg-red-950/20' : '',
                                d.attendance ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-900/50' : '',
                            ]"
                            @click="toggleDay(d.date, !!d.attendance)"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-9 text-center font-black"
                                    :class="d.is_off ? 'text-red-600' : 'text-gray-900 dark:text-white'"
                                >
                                    <div class="text-base leading-none">{{ d.day }}</div>
                                    <div class="text-[10px] font-medium uppercase">
                                        {{ d.weekday.slice(0, 3) }}
                                    </div>
                                </div>
                                <div v-if="d.leave_label" class="font-bold text-blue-600">
                                    {{ d.leave_label }}
                                </div>
                                <div v-else-if="d.is_off" class="font-bold text-red-600">
                                    Libur
                                </div>
                                <div v-else-if="d.is_scheduled" class="text-gray-600 dark:text-gray-300">
                                    <span class="font-bold">{{ d.shift_name }}</span>
                                    <span class="ml-1.5 font-mono text-gray-500">
                                        {{ d.start_time }} – {{ d.end_time }}
                                    </span>
                                </div>
                                <div v-else class="text-gray-400">Belum dijadwalkan</div>
                            </div>

                            <div class="flex items-center gap-2">
                                <template v-if="d.attendance">
                                    <span class="font-mono font-bold">
                                        {{ d.attendance.clock_in_time || '-' }} ➔
                                        {{ d.attendance.clock_out_time || '-' }}
                                    </span>
                                    <Badge
                                        :class="getStatusBadge(d.attendance.status).class"
                                        class="text-[10px] font-bold"
                                    >
                                        {{ getStatusBadge(d.attendance.status).label }}
                                    </Badge>
                                </template>
                                <Badge
                                    v-else-if="d.is_past && d.is_scheduled && !d.is_off"
                                    class="bg-red-100 text-[10px] font-bold text-red-700"
                                >
                                    Tidak Hadir
                                </Badge>
                                <span v-else-if="d.is_today && !d.is_off" class="font-bold text-rose-500">
                                    Hari ini
                                </span>
                                <ChevronDown
                                    v-if="d.attendance"
                                    class="size-4 text-gray-400 transition-transform"
                                    :class="expandedDate === d.date ? 'rotate-180' : ''"
                                />
                            </div>
                        </div>

                        <!-- Detail clock in / out + bukti foto -->
                        <div
                            v-if="d.attendance && expandedDate === d.date"
                            class="grid grid-cols-2 gap-3 border-t border-gray-100 bg-gray-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/40"
                        >
                            <div
                                v-for="(item, idx) in [
                                    {
                                        label: 'Clock In',
                                        time: d.attendance.clock_in_time,
                                        photo: d.attendance.clock_in_photo_url,
                                        dist: d.attendance.clock_in_distance_meters,
                                    },
                                    {
                                        label: 'Clock Out',
                                        time: d.attendance.clock_out_time,
                                        photo: d.attendance.clock_out_photo_url,
                                        dist: d.attendance.clock_out_distance_meters,
                                    },
                                ]"
                                :key="idx"
                                class="space-y-2"
                            >
                                <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500">
                                    {{ item.label }}
                                </div>
                                <div class="font-mono text-lg font-black text-gray-900 dark:text-white">
                                    {{ item.time || '-' }}
                                </div>
                                <button
                                    v-if="item.photo"
                                    type="button"
                                    class="block aspect-square w-full overflow-hidden rounded-xl border border-gray-200 shadow-sm hover:ring-2 hover:ring-rose-500"
                                    @click="openPhotoPreview(item.photo, `${item.label} ${d.date}`)"
                                >
                                    <img :src="item.photo" :alt="`Foto ${item.label}`" class="size-full object-cover" />
                                </button>
                                <div
                                    v-else
                                    class="flex aspect-square w-full items-center justify-center rounded-xl border border-dashed border-gray-300 text-[11px] text-gray-400"
                                >
                                    Tidak ada foto
                                </div>
                                <div v-if="item.dist !== null && item.photo" class="text-[11px] text-gray-500">
                                    Jarak ke gerai: {{ item.dist }} m
                                </div>
                            </div>
                            <div
                                v-if="d.attendance.notes"
                                class="col-span-2 rounded-lg bg-white p-2.5 text-[11px] text-gray-600 dark:bg-zinc-900"
                            >
                                Catatan: {{ d.attendance.notes }}
                            </div>
                        </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

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
            :employee-face-url="employee?.face_photo_url"
            :employee-name="employee?.full_name"
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

        <!-- MODAL 5: Comprehensive Attendance Detail Modal (Clock In & Clock Out with Lat/Long) -->
        <Dialog
            :open="isDetailModalOpen"
            @update:open="(val) => !val && (isDetailModalOpen = false)"
        >
            <DialogContent
                class="max-h-[92vh] w-full overflow-y-auto rounded-3xl border-rose-200/60 p-0 sm:max-w-2xl dark:border-zinc-800"
            >
                <!-- Header with Coral Gradient -->
                <div
                    class="relative bg-gradient-to-r from-red-600 via-rose-600 to-amber-600 p-5 text-white sm:p-6"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-md"
                            >
                                <Clock class="size-6 text-white" />
                            </div>
                            <div>
                                <DialogTitle class="text-base font-black text-white sm:text-lg">
                                    Detail Presensi Kehadiran Kru
                                </DialogTitle>
                                <DialogDescription class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-rose-100">
                                    <span class="font-bold text-white">{{ selectedDetailRecord?.employee?.full_name || 'Kru' }}</span>
                                    <span>•</span>
                                    <span>NIK: {{ selectedDetailRecord?.employee?.nik || '-' }}</span>
                                    <span>•</span>
                                    <span class="inline-flex items-center gap-1">
                                        <Building2 class="size-3" />
                                        {{ selectedDetailRecord?.employee?.current_branch?.branch_name || selectedDetailRecord?.branch?.branch_name || 'Gerai' }}
                                    </span>
                                </DialogDescription>
                            </div>
                        </div>

                        <Badge
                            v-if="selectedDetailRecord"
                            :class="getStatusBadge(selectedDetailRecord.status).class"
                            class="shrink-0 text-xs font-bold"
                        >
                            {{ getStatusBadge(selectedDetailRecord.status).label }}
                        </Badge>
                    </div>

                    <!-- Meta strip: Date & Shift -->
                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-rose-100/90 border-t border-white/15 pt-3">
                        <span class="inline-flex items-center gap-1">
                            <Calendar class="size-3.5" />
                            {{ selectedDetailRecord?.date }}
                        </span>
                        <span>•</span>
                        <span class="inline-flex items-center gap-1">
                            <Clock class="size-3.5" />
                            {{ selectedDetailRecord?.shift?.shift_name || 'Shift Standar' }}
                            <template v-if="selectedDetailRecord?.shift?.start_time">
                                ({{ selectedDetailRecord.shift.start_time.slice(0, 5) }} - {{ selectedDetailRecord.shift.end_time.slice(0, 5) }} WIB)
                            </template>
                        </span>
                    </div>
                </div>

                <!-- Modal Body: Side-by-side Clock In & Clock Out Cards -->
                <div class="space-y-4 p-5 sm:p-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- CARD 1: CLOCK IN -->
                        <div class="flex flex-col justify-between rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/20">
                            <div class="space-y-3.5">
                                <div class="flex items-center justify-between border-b border-emerald-200/70 pb-2.5 dark:border-emerald-800/40">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-black text-emerald-800 uppercase dark:text-emerald-300">
                                        <CheckCircle2 class="size-4 text-emerald-600" />
                                        Clock In (Masuk)
                                    </span>
                                    <span class="font-mono text-sm font-black text-emerald-900 dark:text-emerald-200">
                                        {{ selectedDetailRecord?.clock_in_time ? selectedDetailRecord.clock_in_time.slice(0, 5) + ' WIB' : '-' }}
                                    </span>
                                </div>

                                <!-- Selfie In Thumbnail -->
                                <div class="flex items-center gap-3">
                                    <div
                                        class="group relative size-20 shrink-0 cursor-pointer overflow-hidden rounded-2xl border-2 border-emerald-300 bg-zinc-950 shadow-sm"
                                        @click="openPhotoPreview(selectedDetailRecord?.clock_in_photo_full_url || selectedDetailRecord?.clock_in_photo_url, `Selfie Clock In: ${selectedDetailRecord?.employee?.full_name}`)"
                                    >
                                        <img
                                            v-if="selectedDetailRecord?.clock_in_photo_url"
                                            :src="selectedDetailRecord.clock_in_photo_full_url || `/storage/${selectedDetailRecord.clock_in_photo_url}`"
                                            alt="Selfie In"
                                            class="size-full object-cover transition-transform group-hover:scale-105"
                                        />
                                        <div v-else class="flex size-full items-center justify-center text-gray-500">
                                            <Camera class="size-6" />
                                        </div>
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition-opacity group-hover:opacity-100">
                                            <Eye class="size-5 text-white" />
                                        </div>
                                    </div>
                                    <div class="space-y-1 text-xs">
                                        <span class="block font-medium text-gray-500 dark:text-gray-400">Verifikasi Face ID:</span>
                                        <Badge
                                            v-if="selectedDetailRecord?.is_face_mismatch"
                                            class="border-rose-300 bg-rose-100 text-[11px] font-bold text-rose-800 dark:bg-rose-950 dark:text-rose-300"
                                        >
                                            ⚠️ Kemiripan {{ selectedDetailRecord?.clock_in_face_match_percentage || 0 }}%
                                        </Badge>
                                        <Badge
                                            v-else
                                            class="border-emerald-300 bg-emerald-100 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                        >
                                            ✅ Terverifikasi ({{ selectedDetailRecord?.clock_in_face_match_percentage || 100 }}%)
                                        </Badge>
                                    </div>
                                </div>

                                <!-- GPS Coordinates Highlight Box (User requested) -->
                                <div class="rounded-xl border border-emerald-300/60 bg-white/80 p-3 text-xs space-y-2 dark:border-emerald-800/40 dark:bg-zinc-900">
                                    <div class="flex items-center justify-between text-gray-500">
                                        <span class="font-bold flex items-center gap-1 text-emerald-900 dark:text-emerald-300">
                                            <MapPin class="size-3.5 text-emerald-600" />
                                            Titik GPS Clock In
                                        </span>
                                        <span class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">
                                            {{ selectedDetailRecord?.clock_in_distance_meters != null ? `${selectedDetailRecord.clock_in_distance_meters}m dari gerai` : '-' }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 font-mono text-[11px] bg-emerald-50/50 p-2 rounded-lg dark:bg-zinc-800">
                                        <div>
                                            <span class="block text-[10px] text-gray-400 font-sans uppercase">Latitude</span>
                                            <span class="font-bold text-gray-800 dark:text-gray-200 select-all">{{ selectedDetailRecord?.clock_in_lat ?? '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="block text-[10px] text-gray-400 font-sans uppercase">Longitude</span>
                                            <span class="font-bold text-gray-800 dark:text-gray-200 select-all">{{ selectedDetailRecord?.clock_in_long ?? '-' }}</span>
                                        </div>
                                    </div>

                                    <!-- Action buttons: Copy & Open Maps -->
                                    <div class="flex items-center gap-2 pt-1">
                                        <a
                                            v-if="selectedDetailRecord?.clock_in_lat && selectedDetailRecord?.clock_in_long"
                                            :href="`https://www.google.com/maps?q=${selectedDetailRecord.clock_in_lat},${selectedDetailRecord.clock_in_long}`"
                                            target="_blank"
                                            class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg bg-emerald-600 px-2 py-1 text-[11px] font-bold text-white transition-colors hover:bg-emerald-700 shadow-sm"
                                        >
                                            <Navigation class="size-3" />
                                            <span>Buka di Google Maps</span>
                                            <ExternalLink class="size-2.5 ml-0.5" />
                                        </a>
                                        <button
                                            v-if="selectedDetailRecord?.clock_in_lat && selectedDetailRecord?.clock_in_long"
                                            type="button"
                                            @click="copyCoordinates(selectedDetailRecord.clock_in_lat, selectedDetailRecord.clock_in_long, 'in')"
                                            class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                                            title="Salin Koordinat"
                                        >
                                            <Check v-if="copiedCoordType === 'in'" class="size-3 text-emerald-600" />
                                            <Copy v-else class="size-3" />
                                            <span>{{ copiedCoordType === 'in' ? 'Tersalin' : 'Salin' }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Notes if any -->
                                <div v-if="selectedDetailRecord?.notes" class="text-[11px] text-gray-600 dark:text-gray-400">
                                    <span class="font-bold">Catatan:</span> {{ selectedDetailRecord.notes }}
                                </div>
                            </div>
                        </div>

                        <!-- CARD 2: CLOCK OUT -->
                        <div
                            class="flex flex-col justify-between rounded-2xl border p-4 transition-all"
                            :class="[
                                selectedDetailRecord?.clock_out_time
                                    ? 'border-indigo-200 bg-indigo-50/40 dark:border-indigo-900/60 dark:bg-indigo-950/20'
                                    : 'border-dashed border-gray-300 bg-gray-50/60 dark:border-zinc-800 dark:bg-zinc-900/30'
                            ]"
                        >
                            <template v-if="selectedDetailRecord?.clock_out_time">
                                <div class="space-y-3.5">
                                    <div class="flex items-center justify-between border-b border-indigo-200/70 pb-2.5 dark:border-indigo-800/40">
                                        <span class="inline-flex items-center gap-1.5 text-xs font-black text-indigo-800 uppercase dark:text-indigo-300">
                                            <CheckCircle class="size-4 text-indigo-600" />
                                            Clock Out (Keluar)
                                        </span>
                                        <span class="font-mono text-sm font-black text-indigo-900 dark:text-indigo-200">
                                            {{ selectedDetailRecord.clock_out_time.slice(0, 5) }} WIB
                                        </span>
                                    </div>

                                    <!-- Selfie Out Thumbnail -->
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="group relative size-20 shrink-0 cursor-pointer overflow-hidden rounded-2xl border-2 border-indigo-300 bg-zinc-950 shadow-sm"
                                            @click="openPhotoPreview(selectedDetailRecord.clock_out_photo_full_url || selectedDetailRecord.clock_out_photo_url, `Selfie Clock Out: ${selectedDetailRecord.employee?.full_name}`)"
                                        >
                                            <img
                                                v-if="selectedDetailRecord.clock_out_photo_url"
                                                :src="selectedDetailRecord.clock_out_photo_full_url || `/storage/${selectedDetailRecord.clock_out_photo_url}`"
                                                alt="Selfie Out"
                                                class="size-full object-cover transition-transform group-hover:scale-105"
                                            />
                                            <div v-else class="flex size-full items-center justify-center text-gray-500">
                                                <Camera class="size-6" />
                                            </div>
                                            <div class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition-opacity group-hover:opacity-100">
                                                <Eye class="size-5 text-white" />
                                            </div>
                                        </div>
                                        <div class="space-y-1 text-xs">
                                            <span class="block font-medium text-gray-500 dark:text-gray-400">Verifikasi Face ID:</span>
                                            <Badge
                                                class="border-indigo-300 bg-indigo-100 text-[11px] font-bold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300"
                                            >
                                                ✅ Terverifikasi ({{ selectedDetailRecord.clock_out_face_match_percentage || 100 }}%)
                                            </Badge>
                                        </div>
                                    </div>

                                    <!-- GPS Coordinates Highlight Box (User requested) -->
                                    <div class="rounded-xl border border-indigo-300/60 bg-white/80 p-3 text-xs space-y-2 dark:border-indigo-800/40 dark:bg-zinc-900">
                                        <div class="flex items-center justify-between text-gray-500">
                                            <span class="font-bold flex items-center gap-1 text-indigo-900 dark:text-indigo-300">
                                                <MapPin class="size-3.5 text-indigo-600" />
                                                Titik GPS Clock Out
                                            </span>
                                            <span class="text-[11px] font-semibold text-indigo-700 dark:text-indigo-400">
                                                {{ selectedDetailRecord.clock_out_distance_meters != null ? `${selectedDetailRecord.clock_out_distance_meters}m dari gerai` : '-' }}
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2 font-mono text-[11px] bg-indigo-50/50 p-2 rounded-lg dark:bg-zinc-800">
                                            <div>
                                                <span class="block text-[10px] text-gray-400 font-sans uppercase">Latitude</span>
                                                <span class="font-bold text-gray-800 dark:text-gray-200 select-all">{{ selectedDetailRecord.clock_out_lat ?? '-' }}</span>
                                            </div>
                                            <div>
                                                <span class="block text-[10px] text-gray-400 font-sans uppercase">Longitude</span>
                                                <span class="font-bold text-gray-800 dark:text-gray-200 select-all">{{ selectedDetailRecord.clock_out_long ?? '-' }}</span>
                                            </div>
                                        </div>

                                        <!-- Action buttons: Copy & Open Maps -->
                                        <div class="flex items-center gap-2 pt-1">
                                            <a
                                                v-if="selectedDetailRecord.clock_out_lat && selectedDetailRecord.clock_out_long"
                                                :href="`https://www.google.com/maps?q=${selectedDetailRecord.clock_out_lat},${selectedDetailRecord.clock_out_long}`"
                                                target="_blank"
                                                class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg bg-indigo-600 px-2 py-1 text-[11px] font-bold text-white transition-colors hover:bg-indigo-700 shadow-sm"
                                            >
                                                <Navigation class="size-3" />
                                                <span>Buka di Google Maps</span>
                                                <ExternalLink class="size-2.5 ml-0.5" />
                                            </a>
                                            <button
                                                v-if="selectedDetailRecord.clock_out_lat && selectedDetailRecord.clock_out_long"
                                                type="button"
                                                @click="copyCoordinates(selectedDetailRecord.clock_out_lat, selectedDetailRecord.clock_out_long, 'out')"
                                                class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                                                title="Salin Koordinat"
                                            >
                                                <Check v-if="copiedCoordType === 'out'" class="size-3 text-indigo-600" />
                                                <Copy v-else class="size-3" />
                                                <span>{{ copiedCoordType === 'out' ? 'Tersalin' : 'Salin' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <template v-else>
                                <div class="flex h-full min-h-[220px] flex-col items-center justify-center p-6 text-center">
                                    <div class="mb-2 flex size-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-zinc-800">
                                        <Clock class="size-6" />
                                    </div>
                                    <div class="font-bold text-xs text-gray-700 dark:text-gray-300">
                                        Belum Clock Out
                                    </div>
                                    <p class="mt-1 text-[11px] leading-relaxed text-gray-400">
                                        Kru sedang bertugas dan belum melakukan Clock Out untuk shift hari ini.
                                    </p>
                                    <Badge class="mt-3 border-amber-300 bg-amber-50 text-[10px] font-bold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                        Shift Sedang Berlangsung
                                    </Badge>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Footer Close Button -->
                    <div class="flex justify-end pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-xl px-5 text-xs font-bold"
                            @click="isDetailModalOpen = false"
                        >
                            Tutup
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
</template>
