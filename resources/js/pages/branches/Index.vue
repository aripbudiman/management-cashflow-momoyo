<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    CheckCircle2,
    Clock,
    Crosshair,
    Edit3,
    ExternalLink,
    MapPin,
    Navigation,
    Plus,
    Power,
    RefreshCw,
    Search,
    Shield,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

// Props dari BranchController
const props = defineProps<{
    branches: {
        data: Array<{
            id: number;
            branch_code: string;
            branch_name: string;
            address: string | null;
            latitude: number;
            longitude: number;
            radius_meters: number;
            is_active: boolean;
            active_employees_count: number;
            created_at: string;
        }>;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search?: string;
        is_active?: string;
    };
    stats: {
        total: number;
        active: number;
        inactive: number;
        total_coverage_employees: number;
    };
}>();

// Search & Filter State
const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.is_active ?? '');

const applyFilters = () => {
    router.get(
        '/branches',
        {
            search: search.value || undefined,
            is_active:
                statusFilter.value !== '' ? statusFilter.value : undefined,
        },
        { preserveState: true, replace: true },
    );
};

const resetFilters = () => {
    search.value = '';
    statusFilter.value = '';
    router.get('/branches');
};

// Modal State: Tambah / Edit Cabang
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingBranchId = ref<number | null>(null);
const isDetectingGps = ref(false);
const gpsDetectionMessage = ref<string | null>(null);

const form = useForm({
    branch_code: '',
    branch_name: '',
    address: '',
    latitude: '' as number | string,
    longitude: '' as number | string,
    radius_meters: 50,
    is_active: true,
});

const openCreateModal = () => {
    isEditing.value = false;
    editingBranchId.value = null;
    gpsDetectionMessage.value = null;
    form.reset();
    form.clearErrors();
    form.radius_meters = 50;
    form.is_active = true;
    isModalOpen.value = true;
};

const openEditModal = (b: (typeof props.branches.data)[number]) => {
    isEditing.value = true;
    editingBranchId.value = b.id;
    gpsDetectionMessage.value = null;
    form.clearErrors();

    form.branch_code = b.branch_code;
    form.branch_name = b.branch_name;
    form.address = b.address || '';
    form.latitude = b.latitude;
    form.longitude = b.longitude;
    form.radius_meters = b.radius_meters;
    form.is_active = b.is_active;

    isModalOpen.value = true;
};

// Ambil lokasi GPS browser saat admin berada di outlet
const detectCurrentLocation = () => {
    if (!navigator.geolocation) {
        gpsDetectionMessage.value =
            'Browser Anda tidak mendukung Geolocation API.';
        return;
    }

    isDetectingGps.value = true;
    gpsDetectionMessage.value = 'Mendeteksi titik koordinat perangkat...';

    navigator.geolocation.getCurrentPosition(
        (position) => {
            form.latitude = Number(position.coords.latitude.toFixed(7));
            form.longitude = Number(position.coords.longitude.toFixed(7));
            isDetectingGps.value = false;
            gpsDetectionMessage.value = `Koordinat berhasil dideteksi! (Akurasi ~${Math.round(position.coords.accuracy)} meter)`;
        },
        (error) => {
            isDetectingGps.value = false;
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    gpsDetectionMessage.value =
                        'Izin akses lokasi ditolak oleh browser.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    gpsDetectionMessage.value =
                        'Informasi lokasi tidak tersedia saat ini.';
                    break;
                case error.TIMEOUT:
                    gpsDetectionMessage.value =
                        'Waktu permintaan lokasi habis.';
                    break;
                default:
                    gpsDetectionMessage.value = 'Gagal mendeteksi lokasi.';
            }
        },
        { enableHighAccuracy: true, timeout: 10000 },
    );
};

const submitForm = () => {
    if (isEditing.value && editingBranchId.value) {
        form.put(`/branches/${editingBranchId.value}`, {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            },
        });
    } else {
        form.post('/branches', {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            },
        });
    }
};

const toggleBranchStatus = (b: (typeof props.branches.data)[number]) => {
    const actionText = b.is_active ? 'menonaktifkan' : 'mengaktifkan kembali';
    if (
        confirm(`Apakah Anda yakin ingin ${actionText} gerai ${b.branch_name}?`)
    ) {
        router.delete(`/branches/${b.id}`);
    }
};
</script>

<template>
    <Head title="Master Cabang & Geofencing GPS - Momoyo Ops" />

    <div class="mx-auto max-w-7xl flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- HEADER BANNER -->
        <div
            class="flex flex-col gap-4 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="space-y-1">
                <div
                    class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-bold text-rose-600 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-400"
                >
                    <Shield class="size-3.5" />
                    <span>Akses Khusus Admin Pusat</span>
                </div>
                <h1
                    class="text-2xl font-black tracking-tight text-gray-900 sm:text-3xl dark:text-white"
                >
                    Master Cabang
                </h1>
                <p
                    class="max-w-2xl text-xs text-gray-500 sm:text-sm dark:text-gray-400"
                >
                    Kelola data master seluruh gerai Momoyo, tentukan titik
                    koordinat lintang/bujur (Latitude & Longitude), serta
                    tetapkan radius geofencing untuk validasi absensi selfie
                    kru.
                </p>
            </div>

            <!-- ACTION BUTTON: TAMBAH CABANG -->
            <Button
                @click="openCreateModal"
                class="flex h-11 shrink-0 items-center gap-2 self-start rounded-2xl bg-gradient-to-r from-red-600 via-rose-600 to-rose-700 px-5 text-sm font-bold text-white shadow-md shadow-rose-600/25 hover:from-red-700 hover:to-rose-800 md:self-auto"
            >
                <Plus class="size-4" />
                <span>Tambah Gerai Cabang</span>
            </Button>
        </div>

        <!-- 4 STATS CARDS -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <Card class="border-gray-200 shadow-sm dark:border-zinc-800">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <span
                            class="block text-xs font-bold tracking-wider text-gray-400 uppercase"
                            >Total Gerai</span
                        >
                        <span
                            class="text-2xl font-black text-gray-900 dark:text-white"
                            >{{ stats.total }}</span
                        >
                    </div>
                    <div
                        class="flex size-10 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950/50"
                    >
                        <Building2 class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-gray-200 shadow-sm dark:border-zinc-800">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <span
                            class="block text-xs font-bold tracking-wider text-gray-400 uppercase"
                            >Cabang Aktif</span
                        >
                        <span
                            class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                            >{{ stats.active }}</span
                        >
                    </div>
                    <div
                        class="flex size-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50"
                    >
                        <CheckCircle2 class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-gray-200 shadow-sm dark:border-zinc-800">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <span
                            class="block text-xs font-bold tracking-wider text-gray-400 uppercase"
                            >Tutup / Nonaktif</span
                        >
                        <span
                            class="text-2xl font-black text-amber-600 dark:text-amber-400"
                            >{{ stats.inactive }}</span
                        >
                    </div>
                    <div
                        class="flex size-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-950/50"
                    >
                        <Power class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-gray-200 shadow-sm dark:border-zinc-800">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <span
                            class="block text-xs font-bold tracking-wider text-gray-400 uppercase"
                            >Total Kru Aktif</span
                        >
                        <span
                            class="text-2xl font-black text-blue-600 dark:text-blue-400"
                            >{{ stats.total_coverage_employees }}</span
                        >
                    </div>
                    <div
                        class="flex size-10 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-950/50"
                    >
                        <Users class="size-5" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- FILTER & SEARCH BAR -->
        <Card class="border-gray-200 shadow-sm dark:border-zinc-800">
            <CardContent class="p-4">
                <div class="grid grid-cols-1 items-center gap-3 sm:grid-cols-3">
                    <!-- Search Input -->
                    <div class="relative sm:col-span-2">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400"
                        />
                        <Input
                            v-model="search"
                            @keydown.enter="applyFilters"
                            placeholder="Cari Nama Gerai, Kode Cabang (misal: MMY-JKT-01), atau Alamat..."
                            class="h-10 rounded-xl pl-10 text-xs"
                        />
                    </div>

                    <!-- Status Filter & Reset -->
                    <div class="flex items-center gap-2">
                        <select
                            v-model="statusFilter"
                            @change="applyFilters"
                            class="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-rose-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300"
                        >
                            <option value="">Semua Status Gerai</option>
                            <option value="true">Aktif Beroperasi</option>
                            <option value="false">Nonaktif / Tutup</option>
                        </select>

                        <Button
                            variant="outline"
                            size="icon"
                            @click="resetFilters"
                            title="Reset Filter"
                            class="size-10 shrink-0 rounded-xl text-gray-500"
                        >
                            <RefreshCw class="size-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- BRANCHES GRID CARDS -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="b in branches.data"
                :key="b.id"
                :class="[
                    'flex flex-col justify-between overflow-hidden rounded-3xl border shadow-md transition-all',
                    b.is_active
                        ? 'border-gray-200 bg-white hover:border-rose-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-rose-900'
                        : 'border-gray-200 bg-gray-50/60 opacity-75 dark:border-zinc-800 dark:bg-zinc-900/40',
                ]"
            >
                <CardHeader
                    class="border-b border-gray-100 p-5 pb-3 dark:border-zinc-800"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-0.5 font-mono text-xs font-black text-rose-600 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-400"
                                >
                                    {{ b.branch_code }}
                                </span>
                                <Badge
                                    variant="outline"
                                    :class="
                                        b.is_active
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40'
                                            : 'border-gray-300 text-gray-500'
                                    "
                                >
                                    {{ b.is_active ? 'Aktif' : 'Nonaktif' }}
                                </Badge>
                            </div>
                            <CardTitle
                                class="mt-2 text-base font-black text-gray-900 dark:text-white"
                            >
                                {{ b.branch_name }}
                            </CardTitle>
                        </div>
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-tr from-rose-500 to-amber-400 text-white shadow-sm"
                        >
                            <Building2 class="size-4" />
                        </div>
                    </div>
                </CardHeader>

                <CardContent
                    class="flex flex-1 flex-col justify-between space-y-4 p-5"
                >
                    <!-- Address -->
                    <div class="space-y-1">
                        <span
                            class="block text-[11px] font-bold tracking-wider text-gray-400 uppercase"
                            >Alamat Outlet</span
                        >
                        <p
                            class="line-clamp-2 text-xs leading-relaxed text-gray-600 dark:text-gray-300"
                        >
                            {{ b.address || 'Alamat belum dilengkapi.' }}
                        </p>
                    </div>

                    <!-- Geofencing & Coordinates Card -->
                    <div
                        class="space-y-2 rounded-2xl border border-gray-200 bg-gray-50 p-3.5 dark:border-zinc-800 dark:bg-zinc-800/50"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <span
                                class="flex items-center gap-1 font-medium text-gray-500"
                            >
                                <MapPin class="size-3.5 text-rose-500" />
                                Koordinat GPS:
                            </span>
                            <a
                                :href="`https://www.google.com/maps?q=${b.latitude},${b.longitude}`"
                                target="_blank"
                                class="flex items-center gap-1 text-[11px] font-bold text-rose-600 hover:text-rose-700"
                                title="Buka di Google Maps"
                            >
                                <span>Peta</span>
                                <ExternalLink class="size-3" />
                            </a>
                        </div>
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-2 text-center font-mono text-xs font-semibold text-gray-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-gray-200"
                        >
                            {{ b.latitude }}, {{ b.longitude }}
                        </div>
                        <div
                            class="flex items-center justify-between pt-1 text-xs"
                        >
                            <span
                                class="flex items-center gap-1 font-medium text-gray-500"
                            >
                                <Navigation class="size-3.5 text-emerald-500" />
                                Radius Absensi:
                            </span>
                            <Badge
                                class="bg-emerald-100 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                            >
                                {{ b.radius_meters }} meter
                            </Badge>
                        </div>
                    </div>

                    <!-- Active Employees Count -->
                    <div
                        class="flex items-center justify-between border-t border-gray-100 pt-1 text-xs dark:border-zinc-800"
                    >
                        <span class="font-medium text-gray-400"
                            >Kru Aktif Bertugas:</span
                        >
                        <span
                            class="flex items-center gap-1 font-bold text-gray-800 dark:text-gray-200"
                        >
                            <Users class="size-3.5 text-rose-500" />
                            {{ b.active_employees_count }} Karyawan
                        </span>
                    </div>

                    <!-- Action Buttons -->
                    <div
                        class="grid grid-cols-2 gap-2 border-t border-gray-100 pt-2 dark:border-zinc-800"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openEditModal(b)"
                            class="h-9 rounded-xl border-gray-300 text-xs font-bold hover:border-rose-300 hover:text-rose-600 dark:border-zinc-700"
                        >
                            <Edit3 class="mr-1 size-3.5" /> Edit Gerai
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="toggleBranchStatus(b)"
                            :class="[
                                'h-9 rounded-xl text-xs font-bold',
                                b.is_active
                                    ? 'text-gray-500 hover:bg-rose-50 hover:text-rose-600'
                                    : 'text-emerald-600 hover:bg-emerald-50',
                            ]"
                        >
                            <Power class="mr-1 size-3.5" />
                            {{ b.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="!branches.data.length"
            class="rounded-3xl border border-gray-200 bg-white p-6 py-16 text-center dark:border-zinc-800 dark:bg-zinc-900"
        >
            <Building2 class="mx-auto mb-2 size-12 text-gray-300" />
            <h3 class="text-base font-bold text-gray-800 dark:text-gray-200">
                Belum ada gerai cabang terdaftar
            </h3>
            <p class="mt-1 text-xs text-gray-400">
                Silakan klik tombol "Tambah Cabang Baru" untuk mendaftarkan
                gerai perdana Anda.
            </p>
            <Button
                @click="openCreateModal"
                class="mt-4 rounded-xl bg-rose-600 px-4 text-xs font-bold text-white shadow-md shadow-rose-500/20 hover:bg-rose-700"
            >
                <Plus class="mr-1.5 size-4" /> Tambah Cabang Perdana
            </Button>
        </div>

        <!-- PAGINATION -->
        <div
            v-if="branches.last_page > 1"
            class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4 text-xs dark:border-zinc-800 dark:bg-zinc-900"
        >
            <span class="text-gray-500">
                Menampilkan halaman
                <strong>{{ branches.current_page }}</strong> dari
                <strong>{{ branches.last_page }}</strong>
            </span>
            <div class="flex items-center gap-1">
                <template v-for="(link, i) in branches.links" :key="i">
                    <Button
                        v-if="link.url"
                        variant="outline"
                        size="sm"
                        :class="[
                            'h-8 rounded-lg px-3 text-xs',
                            link.active
                                ? 'border-rose-600 bg-rose-600 font-bold text-white'
                                : '',
                        ]"
                        @click="router.get(link.url)"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>

    <!-- MODAL DIALOG: TAMBAH / EDIT GERAI CABANG -->
    <Dialog :open="isModalOpen" @update:open="isModalOpen = $event">
        <DialogContent
            class="max-h-[90vh] max-w-xl overflow-y-auto rounded-3xl p-6"
        >
            <DialogHeader
                class="border-b border-gray-100 pb-3 dark:border-zinc-800"
            >
                <DialogTitle
                    class="flex items-center gap-2 text-lg font-black text-gray-900 dark:text-white"
                >
                    <Building2 class="size-5 text-rose-600" />
                    <span>{{
                        isEditing
                            ? 'Edit Informasi Gerai Cabang'
                            : 'Tambah Gerai Cabang Baru Momoyo'
                    }}</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-gray-500">
                    Admin pusat menentukan kode cabang, alamat, serta koordinat
                    geofencing untuk batas absensi.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitForm" class="space-y-4 pt-2">
                <!-- Kode Cabang & Nama Cabang -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="space-y-1.5 sm:col-span-1">
                        <Label for="branch_code" class="text-xs font-semibold">
                            Kode Gerai <span class="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="branch_code"
                            v-model="form.branch_code"
                            placeholder="MMY-BDG-01"
                            required
                            class="h-10 rounded-xl font-mono text-xs uppercase"
                        />
                        <InputError :message="form.errors.branch_code" />
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="branch_name" class="text-xs font-semibold">
                            Nama Gerai Cabang
                            <span class="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="branch_name"
                            v-model="form.branch_name"
                            placeholder="Contoh: Momoyo Dago Bandung"
                            required
                            class="h-10 rounded-xl text-xs"
                        />
                        <InputError :message="form.errors.branch_name" />
                    </div>
                </div>

                <!-- Alamat Lengkap -->
                <div class="space-y-1.5">
                    <Label for="address" class="text-xs font-semibold">
                        Alamat Lengkap Outlet
                    </Label>
                    <textarea
                        id="address"
                        v-model="form.address"
                        rows="2"
                        placeholder="Jl. Ir. H. Juanda No. 120, Dago, Coblong, Kota Bandung..."
                        class="w-full rounded-xl border border-gray-300 bg-white p-3 text-xs text-gray-700 focus:ring-2 focus:ring-rose-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300"
                    ></textarea>
                    <InputError :message="form.errors.address" />
                </div>

                <!-- GEOFENCING GPS COORDINATES -->
                <div
                    class="space-y-3 rounded-2xl border border-rose-200 bg-rose-50/50 p-4 dark:border-rose-900/60 dark:bg-rose-950/20"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-rose-900 uppercase dark:text-rose-200"
                        >
                            <Navigation class="size-3.5 text-rose-500" />
                            <span>Titik Koordinat GPS & Geofencing</span>
                        </span>

                        <!-- BUTTON DETEKSI LOKASI GPS BROWSER -->
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="detectCurrentLocation"
                            :disabled="isDetectingGps"
                            class="flex h-8 items-center gap-1 rounded-xl border-rose-300 bg-white text-xs font-bold text-rose-700 hover:bg-rose-100 dark:bg-zinc-900 dark:text-rose-300"
                        >
                            <Spinner
                                v-if="isDetectingGps"
                                class="size-3 text-rose-600"
                            />
                            <Crosshair v-else class="size-3 text-rose-600" />
                            <span>Deteksi GPS Saya</span>
                        </Button>
                    </div>

                    <div
                        v-if="gpsDetectionMessage"
                        class="rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                    >
                        {{ gpsDetectionMessage }}
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="latitude" class="text-xs font-semibold">
                                Latitude (Lintang)
                                <span class="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="latitude"
                                v-model="form.latitude"
                                placeholder="-6.229728"
                                required
                                class="h-10 rounded-xl bg-white font-mono text-xs dark:bg-zinc-900"
                            />
                            <InputError :message="form.errors.latitude" />
                        </div>

                        <div class="space-y-1">
                            <Label
                                for="longitude"
                                class="text-xs font-semibold"
                            >
                                Longitude (Bujur)
                                <span class="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="longitude"
                                v-model="form.longitude"
                                placeholder="106.855364"
                                required
                                class="h-10 rounded-xl bg-white font-mono text-xs dark:bg-zinc-900"
                            />
                            <InputError :message="form.errors.longitude" />
                        </div>
                    </div>

                    <!-- Radius Geofencing -->
                    <div class="space-y-1.5 pt-1">
                        <div class="flex items-center justify-between">
                            <Label
                                for="radius_meters"
                                class="text-xs font-semibold"
                            >
                                Radius Toleransi Geofencing
                            </Label>
                            <span
                                class="font-mono text-xs font-bold text-rose-600"
                                >{{ form.radius_meters }} meter</span
                            >
                        </div>
                        <Input
                            id="radius_meters"
                            type="number"
                            min="10"
                            max="500"
                            step="5"
                            v-model="form.radius_meters"
                            required
                            class="h-10 rounded-xl bg-white font-mono text-xs dark:bg-zinc-900"
                        />
                        <p class="text-[11px] text-gray-500">
                            Kru hanya dapat Clock-In jika berada dalam radius
                            ini dari titik toko. Standar toko: 50 meter.
                        </p>
                        <InputError :message="form.errors.radius_meters" />
                    </div>
                </div>

                <!-- Status Operasional -->
                <div class="flex items-center gap-2 pt-1">
                    <input
                        id="is_active"
                        type="checkbox"
                        v-model="form.is_active"
                        class="size-4 rounded border-gray-300 text-rose-600 focus:ring-rose-500"
                    />
                    <label
                        for="is_active"
                        class="cursor-pointer text-xs font-semibold text-gray-700 dark:text-gray-300"
                    >
                        Gerai cabang aktif beroperasi (Siap menerima absensi &
                        transaksi)
                    </label>
                </div>

                <!-- DIALOG FOOTER -->
                <DialogFooter
                    class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4 dark:border-zinc-800"
                >
                    <Button
                        type="button"
                        variant="outline"
                        @click="isModalOpen = false"
                        class="h-10 rounded-xl text-xs"
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="h-10 rounded-xl bg-gradient-to-r from-red-600 via-rose-600 to-rose-700 px-5 text-xs font-bold text-white shadow-sm hover:from-red-700 hover:to-rose-800"
                    >
                        <Spinner v-if="form.processing" class="mr-1 size-3.5" />
                        <span>{{
                            isEditing
                                ? 'Simpan Perubahan Gerai'
                                : 'Tambahkan Gerai Cabang'
                        }}</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
