<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Camera,
    CheckCircle2,
    Clock,
    FlipHorizontal,
    Info,
    MapPin,
    Navigation,
    RefreshCw,
    ShieldAlert,
    ShieldCheck,
    UploadCloud,
    X,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import LocationMap from '@/components/LocationMap.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useFaceDetector } from '@/composables/useFaceDetector';
import { useGeolocation } from '@/composables/useGeolocation';

const props = defineProps<{
    open: boolean;
    mode: 'clock_in' | 'clock_out';
    branch?: {
        id: number;
        branch_name: string;
        branch_code: string;
        latitude: number;
        longitude: number;
        radius_meters: number;
    } | null;
    shifts?: Array<{
        id: number;
        shift_name: string;
        start_time: string;
        end_time: string;
        late_tolerance_minutes: number;
    }>;
    employeeFaceUrl?: string | null;
    employeeName?: string;
    attendanceType?: 'radius' | 'flexible';
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

// Geolocation composable
const {
    coords,
    isLoading: isGpsLoading,
    error: gpsError,
    getLocation,
    calculateDistance,
} = useGeolocation();

// Geofence states
const calculatedDistance = ref<number | null>(null);
// Tipe fleksibel: bebas lokasi, cukup koordinat GPS terdeteksi (lokasi tetap dicatat)
const isFlexible = computed(() => props.attendanceType === 'flexible');
const isInsideRadius = computed(() => {
    if (isFlexible.value) return !!coords.value?.latitude && !!coords.value?.longitude;
    if (!props.branch || calculatedDistance.value === null) return false;
    const maxRadius = props.branch.radius_meters || 50;
    return calculatedDistance.value <= maxRadius;
});

// Camera & Face Detection state
const {
    isFaceDetected,
    isFaceMatched,
    matchSimilarity,
    detectionConfidence,
    detectionMessage,
    setReferenceFace,
    startLiveDetection,
    stopLiveDetection,
    validateBase64Image,
} = useFaceDetector();

const videoRef = ref<HTMLVideoElement | null>(null);
const mediaStream = ref<MediaStream | null>(null);
const isCameraActive = ref(false);
const cameraError = ref<string | null>(null);
const facingMode = ref<'user' | 'environment'>('user');
const capturedPhoto = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

// Form state
const selectedShiftId = ref<number | null>(null);
const notes = ref('');
const isSubmitting = ref(false);

// Format time
const currentTimeStr = ref('');
const updateCurrentTime = () => {
    const d = new Date();
    currentTimeStr.value = d.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

// Check GPS location against branch
const checkGps = async () => {
    try {
        const position = await getLocation();
        if (props.branch && position.latitude && position.longitude) {
            calculatedDistance.value = calculateDistance(
                position.latitude,
                position.longitude,
                props.branch.latitude,
                props.branch.longitude,
            );
        }
    } catch (err: any) {
        console.warn('GPS check warning:', err);
    }
};

// Start camera stream
const startCamera = async () => {
    stopCamera();
    cameraError.value = null;

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: facingMode.value,
                width: { ideal: 720 },
                height: { ideal: 720 },
            },
            audio: false,
        });

        mediaStream.value = stream;
        await nextTick();

        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            videoRef.value.play();
            isCameraActive.value = true;
            startLiveDetection(videoRef.value);
        }
    } catch (err: any) {
        console.error('Camera access error:', err);
        isCameraActive.value = false;
        if (err.name === 'NotAllowedError') {
            cameraError.value =
                'Izin kamera ditolak. Harap izinkan akses kamera di browser smartphone Anda.';
        } else {
            cameraError.value =
                'Gagal menyalakan kamera. Anda dapat mengunggah selfie langsung.';
        }
    }
};

// Stop camera stream
const stopCamera = () => {
    stopLiveDetection();
    if (mediaStream.value) {
        mediaStream.value.getTracks().forEach((track) => track.stop());
        mediaStream.value = null;
    }
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    isCameraActive.value = false;
};

// Toggle camera facing mode
const toggleCameraFacing = async () => {
    facingMode.value = facingMode.value === 'user' ? 'environment' : 'user';
    await startCamera();
};

// Snap selfie dengan proteksi anti-objek / wajib wajah manusia
const capturePhoto = async () => {
    if (!videoRef.value) return;

    if (!isFaceDetected.value) {
        alert(
            'Wajah belum terdeteksi! Pastikan kamera mengarah tepat ke wajah Anda di dalam lingkaran oval (jangan halangi kamera dengan HP atau objek lain).',
        );
        return;
    }

    if (navigator.vibrate) {
        navigator.vibrate(50);
    }

    const video = videoRef.value;
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 640;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    if (facingMode.value === 'user') {
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
    }

    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const photoData = canvas.toDataURL('image/jpeg', 0.88);

    // Validasi keberadaan wajah pada snapshot kanvas
    const validation = await validateBase64Image(photoData);
    if (!validation.hasFace) {
        alert(
            'Wajah tidak terdeteksi pada hasil foto! Posisikan wajah Anda tepat di dalam lingkaran panduan oval.',
        );
        return;
    }

    capturedPhoto.value = photoData;
    stopCamera();
};

const retakePhoto = () => {
    capturedPhoto.value = null;
    startCamera();
};

// File upload fallback dengan validasi wajah
const handleFileUpload = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = async (e) => {
            const dataUrl = e.target?.result as string;
            const validation = await validateBase64Image(dataUrl);
            if (!validation.hasFace) {
                alert(
                    'Foto yang diunggah bukan wajah manusia! Pastikan Anda mengunggah foto wajah kru yang bersangkutan, bukan barang atau objek lain.',
                );
                return;
            }
            capturedPhoto.value = dataUrl;
            stopCamera();
        };
        reader.readAsDataURL(file);
    }
};

// Shift status calculation for clock in
const selectedShift = computed(() => {
    if (!props.shifts || !selectedShiftId.value) return null;
    return props.shifts.find((s) => s.id === selectedShiftId.value) || null;
});

const isClockInLate = computed(() => {
    if (!selectedShift.value) return false;
    const now = new Date();
    const [h, m] = selectedShift.value.start_time.split(':').map(Number);
    const shiftStart = new Date();
    shiftStart.setHours(
        h,
        m + (selectedShift.value.late_tolerance_minutes || 15),
        0,
    );
    return now > shiftStart;
});

// Submit clock in / out
const submitAttendance = () => {
    if (!coords.value?.latitude || !coords.value?.longitude) {
        alert(
            'Koordinat GPS belum terdeteksi. Silakan ketuk tombol Deteksi Ulang GPS.',
        );
        return;
    }

    if (!isFlexible.value && !isInsideRadius.value) {
        alert(
            `Lokasi Anda berada di luar radius gerai (${calculatedDistance.value}m dari gerai, batas max ${props.branch?.radius_meters || 50}m). Anda harus berada di area gerai untuk melakukan absen.`,
        );
        return;
    }

    if (!capturedPhoto.value) {
        alert('Foto selfie kehadiran wajib diambil terlebih dahulu.');
        return;
    }

    isSubmitting.value = true;
    const url =
        props.mode === 'clock_in'
            ? '/attendance/clock-in'
            : '/attendance/clock-out';

    const payload: any = {
        latitude: coords.value.latitude,
        longitude: coords.value.longitude,
        photo: capturedPhoto.value,
        notes: notes.value || null,
    };

    if (props.mode === 'clock_in' && selectedShiftId.value) {
        payload.shift_id = selectedShiftId.value;
    }

    router.post(url, payload, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            closeModal();
            emit('success');
        },
        onError: () => {
            isSubmitting.value = false;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const closeModal = () => {
    stopCamera();
    capturedPhoto.value = null;
    cameraError.value = null;
    notes.value = '';
    emit('update:open', false);
};

// Lifecycle watcher
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            updateCurrentTime();
            capturedPhoto.value = null;
            notes.value = '';
            if (props.employeeFaceUrl) {
                setReferenceFace(props.employeeFaceUrl);
            }
            if (
                props.shifts &&
                props.shifts.length > 0 &&
                !selectedShiftId.value
            ) {
                selectedShiftId.value = props.shifts[0].id;
            }
            checkGps();
            startCamera();
        } else {
            stopCamera();
        }
    },
);

onBeforeUnmount(() => {
    stopCamera();
});
</script>

<template>
    <Dialog :open="open" @update:open="(val) => !val && closeModal()">
        <DialogContent
            class="max-h-[95vh] w-full overflow-y-auto rounded-3xl border-rose-200/60 p-0 sm:max-w-md dark:border-zinc-800"
        >
            <!-- Header with Momoyo Color Palette -->
            <div
                class="relative p-5 text-white"
                :class="
                    mode === 'clock_in'
                        ? 'bg-gradient-to-r from-red-600 via-rose-600 to-amber-600'
                        : 'bg-gradient-to-r from-zinc-800 via-zinc-900 to-rose-950'
                "
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex size-10 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-md"
                        >
                            <Clock class="size-5 text-white" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold text-white">
                                {{
                                    mode === 'clock_in'
                                        ? 'Clock In (Absen Masuk)'
                                        : 'Clock Out (Absen Keluar)'
                                }}
                            </DialogTitle>
                            <DialogDescription
                                class="mt-0.5 flex items-center gap-1.5 text-xs text-rose-100"
                            >
                                <MapPin class="size-3" />
                                <span>{{
                                    branch?.branch_name || 'Gerai Momoyo'
                                }}</span>
                            </DialogDescription>
                        </div>
                    </div>
                    <Badge
                        class="border-none bg-white/25 text-[11px] font-bold text-white backdrop-blur-sm"
                    >
                        {{ currentTimeStr }} WIB
                    </Badge>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="space-y-4 p-5">
                <!-- 1. GEOFENCING RADAR STATUS CARD -->
                <div
                    class="rounded-2xl border p-3.5 transition-all"
                    :class="[
                        isGpsLoading
                            ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/30'
                            : isInsideRadius
                              ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800/60 dark:bg-emerald-950/30'
                              : 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-800/60 dark:bg-rose-950/30',
                    ]"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-start gap-2.5">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-xl text-white"
                                :class="[
                                    isGpsLoading
                                        ? 'animate-spin bg-amber-500'
                                        : isInsideRadius
                                          ? 'bg-emerald-600'
                                          : 'bg-rose-600',
                                ]"
                            >
                                <Navigation
                                    v-if="!isGpsLoading"
                                    class="size-4"
                                />
                                <RefreshCw v-else class="size-4" />
                            </div>
                            <div class="space-y-0.5 text-xs">
                                <div
                                    class="flex items-center gap-1.5 font-bold"
                                >
                                    <span v-if="isGpsLoading"
                                        >Mencari Posisi GPS Smartphone...</span
                                    >
                                    <span
                                        v-else-if="isInsideRadius"
                                        class="text-emerald-800 dark:text-emerald-300"
                                    >
                                        {{
                                            isFlexible
                                                ? 'Mode Fleksibel: Lokasi Dicatat'
                                                : 'Lokasi Valid: Dalam Radius Gerai'
                                        }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-rose-800 dark:text-rose-300"
                                    >
                                        Di Luar Radius Gerai
                                    </span>
                                </div>
                                <p class="text-[11px] leading-tight opacity-90">
                                    <template v-if="isGpsLoading">
                                        Menyambungkan ke satelit GPS
                                        perangkat...
                                    </template>
                                    <template v-else-if="isFlexible && coords">
                                        Anda bisa absen di mana saja. Koordinat
                                        <strong
                                            >{{ coords.latitude.toFixed(5) }},
                                            {{
                                                coords.longitude.toFixed(5)
                                            }}</strong
                                        >
                                        akan tercatat<template
                                            v-if="calculatedDistance !== null"
                                        >
                                            ({{ calculatedDistance }}m dari
                                            gerai)</template
                                        >.
                                    </template>
                                    <template
                                        v-else-if="calculatedDistance !== null"
                                    >
                                        Terdeteksi
                                        <strong
                                            >{{ calculatedDistance }}m</strong
                                        >
                                        dari titik gerai (Maks. toleransi
                                        <strong
                                            >{{
                                                branch?.radius_meters || 50
                                            }}m</strong
                                        >).
                                    </template>
                                    <template v-else>
                                        {{ gpsError || 'GPS belum aktif.' }}
                                    </template>
                                </p>
                            </div>
                        </div>

                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            class="h-7 shrink-0 px-2 text-[11px] font-bold hover:bg-black/5"
                            :disabled="isGpsLoading"
                            @click="checkGps"
                        >
                            <RefreshCw
                                class="mr-1 size-3"
                                :class="{ 'animate-spin': isGpsLoading }"
                            />
                            GPS Ulang
                        </Button>
                    </div>

                    <div
                        v-if="
                            !isGpsLoading &&
                            !isFlexible &&
                            !isInsideRadius &&
                            calculatedDistance !== null
                        "
                        class="mt-2 flex items-center gap-1 border-t border-rose-200/80 pt-2 text-[11px] font-medium text-rose-800 dark:border-rose-800/40 dark:text-rose-300"
                    >
                        <AlertCircle class="size-3.5 shrink-0 text-rose-600" />
                        <span
                            >Absensi hanya dapat dilakukan jika Anda sudah
                            berada di lokasi gerai.</span
                        >
                    </div>
                </div>

                <!-- PETA POSISI SAAT INI -->
                <LocationMap
                    v-if="coords"
                    :points="[
                        {
                            lat: coords.latitude,
                            lng: coords.longitude,
                            label: 'Posisi Anda',
                            color: '#2563eb',
                        },
                    ]"
                    :branch="
                        branch && branch.latitude != null
                            ? {
                                  lat: Number(branch.latitude),
                                  lng: Number(branch.longitude),
                                  radius: branch.radius_meters || 50,
                                  name: branch.branch_name,
                              }
                            : null
                    "
                    height-class="h-44"
                />

                <!-- 2. PILIH SHIFT (KHUSUS CLOCK IN) -->
                <div
                    v-if="mode === 'clock_in' && shifts && shifts.length > 0"
                    class="space-y-1.5"
                >
                    <Label
                        class="text-xs font-bold text-gray-700 dark:text-gray-300"
                    >
                        Pilih Shift Hari Ini:
                    </Label>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <button
                            v-for="s in shifts"
                            :key="s.id"
                            type="button"
                            @click="selectedShiftId = s.id"
                            class="relative overflow-hidden rounded-xl border p-2.5 text-left transition-all"
                            :class="[
                                selectedShiftId === s.id
                                    ? 'border-rose-600 bg-rose-50/70 font-bold text-rose-950 ring-2 ring-rose-500/20 dark:border-rose-500 dark:bg-rose-950/40 dark:text-rose-100'
                                    : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300',
                            ]"
                        >
                            <div class="truncate text-xs font-bold">
                                {{ s.shift_name }}
                            </div>
                            <div
                                class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400"
                            >
                                {{ s.start_time.slice(0, 5) }} -
                                {{ s.end_time.slice(0, 5) }}
                            </div>
                        </button>
                    </div>
                    <!-- Late / On time note -->
                    <div
                        v-if="selectedShift"
                        class="mt-1 flex items-center gap-1.5 text-[11px]"
                    >
                        <span
                            v-if="isClockInLate"
                            class="flex items-center gap-1 font-semibold text-amber-600"
                        >
                            ⚠️ Melewati jam mulai toleransi ({{
                                selectedShift.late_tolerance_minutes
                            }}
                            menit). Tercatat Terlambat.
                        </span>
                        <span
                            v-else
                            class="flex items-center gap-1 font-semibold text-emerald-600"
                        >
                            ✅ Masuk dalam jadwal shift tepat waktu.
                        </span>
                    </div>
                </div>

                <!-- 3. CAMERA SELFIE VIEWPORT -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <Label
                            class="font-bold text-gray-700 dark:text-gray-300"
                        >
                            Foto Selfie Kehadiran:
                        </Label>
                        <span class="text-[11px] text-gray-500"
                            >Kamera Depan Aktif</span
                        >
                    </div>

                    <div
                        class="relative mx-auto flex aspect-square w-full max-w-[280px] items-center justify-center overflow-hidden rounded-3xl border-2 border-rose-100 bg-zinc-950 shadow-inner dark:border-zinc-800"
                    >
                        <!-- Live stream -->
                        <template v-if="!capturedPhoto">
                            <video
                                ref="videoRef"
                                class="absolute inset-0 size-full object-cover"
                                :class="{
                                    '-scale-x-100': facingMode === 'user',
                                }"
                                autoplay
                                playsinline
                                muted
                            ></video>

                            <!-- Oval Guide with Dynamic Biometric Indicator -->
                            <div
                                class="pointer-events-none absolute inset-0 flex items-center justify-center"
                            >
                                <div
                                    class="relative h-[80%] w-[68%] rounded-[50%] border-2 border-dashed transition-all duration-300"
                                    :class="[
                                        isFaceDetected
                                            ? 'border-emerald-400 shadow-[0_0_25px_rgba(52,211,153,0.6)] ring-2 ring-emerald-400/50'
                                            : 'border-rose-400/90 shadow-[0_0_20px_rgba(244,63,94,0.35)]',
                                    ]"
                                >
                                    <!-- Scanning beam micro-animation -->
                                    <div
                                        class="absolute inset-x-4 top-1/2 -translate-y-1/2 h-0.5 animate-pulse"
                                        :class="
                                            isFaceDetected
                                                ? 'bg-gradient-to-r from-transparent via-emerald-400 to-transparent'
                                                : 'bg-gradient-to-r from-transparent via-rose-400 to-transparent'
                                        "
                                    ></div>
                                </div>
                            </div>

                            <!-- Real-time Face Status Pill Overlay -->
                            <div
                                class="pointer-events-none absolute inset-x-0 top-2.5 flex justify-center px-3"
                            >
                                <span
                                    class="inline-flex max-w-[92%] items-center gap-1.5 truncate rounded-full px-3 py-1 text-[11px] font-semibold backdrop-blur-md transition-all text-center"
                                    :class="[
                                        isFaceDetected
                                            ? 'border border-emerald-500/50 bg-emerald-950/85 text-emerald-200 shadow-sm'
                                            : 'border border-rose-500/30 bg-black/75 text-rose-200',
                                    ]"
                                >
                                    <span
                                        class="size-2 shrink-0 rounded-full"
                                        :class="[
                                            isFaceDetected
                                                ? 'animate-ping bg-emerald-400'
                                                : 'animate-pulse bg-rose-500',
                                        ]"
                                    ></span>
                                    <span class="truncate">
                                        {{
                                            isFaceDetected
                                                ? 'Wajah Terdeteksi & Fokus'
                                                : 'Posisikan Wajah di Oval'
                                        }}
                                    </span>
                                </span>
                            </div>

                            <button
                                type="button"
                                @click="toggleCameraFacing"
                                class="absolute top-2.5 right-2.5 flex size-8 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur-md hover:bg-white/30"
                                title="Balik Kamera"
                            >
                                <FlipHorizontal class="size-3.5" />
                            </button>

                            <!-- Camera Error State -->
                            <div
                                v-if="cameraError"
                                class="absolute inset-0 flex flex-col items-center justify-center bg-zinc-950/90 p-3 text-center text-white"
                            >
                                <AlertCircle
                                    class="mb-1 size-6 text-rose-500"
                                />
                                <p class="mb-3 text-[11px] text-rose-200">
                                    {{ cameraError }}
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="rounded-xl border-white/20 text-xs text-white"
                                    @click="fileInputRef?.click()"
                                >
                                    <UploadCloud class="mr-1 size-3" />
                                    Unggah Selfie
                                </Button>
                            </div>
                        </template>

                        <!-- Preview -->
                        <template v-else>
                            <img
                                :src="capturedPhoto"
                                alt="Selfie Absensi"
                                class="size-full object-cover"
                            />
                            <div
                                class="absolute inset-x-0 top-2 flex justify-center"
                            >
                                <Badge
                                    class="bg-emerald-600 text-[11px] font-semibold text-white shadow"
                                >
                                    <CheckCircle2 class="mr-1 size-3" />
                                    Selfie Siap Diverifikasi
                                </Badge>
                            </div>
                        </template>
                    </div>

                    <!-- Hidden file upload -->
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept="image/*"
                        capture="user"
                        class="hidden"
                        @change="handleFileUpload"
                    />

                    <!-- Capture / Retake Controls -->
                    <div class="pt-2 text-center">
                        <div
                            v-if="!capturedPhoto"
                            class="flex flex-col items-center"
                        >
                            <button
                                type="button"
                                @click="capturePhoto"
                                class="group flex size-14 items-center justify-center rounded-full p-0.5 shadow-md transition-all active:scale-95"
                                :class="[
                                    isFaceDetected
                                        ? 'bg-gradient-to-tr from-emerald-600 to-teal-500 shadow-emerald-600/40 ring-4 ring-emerald-500/20'
                                        : 'bg-gradient-to-tr from-rose-600 to-red-500 shadow-rose-600/30 opacity-70',
                                ]"
                                :disabled="!isCameraActive"
                            >
                                <div
                                    class="flex size-full items-center justify-center rounded-full border-2 border-white/80 transition-colors"
                                    :class="[
                                        isFaceDetected
                                            ? 'bg-emerald-600 group-hover:bg-emerald-500'
                                            : 'bg-rose-600 group-hover:bg-rose-500',
                                    ]"
                                >
                                    <Camera class="size-5 text-white" />
                                </div>
                            </button>
                            <span
                                class="mt-1 text-[11px] font-semibold transition-colors"
                                :class="
                                    isFaceDetected
                                        ? 'font-bold text-emerald-700 dark:text-emerald-400'
                                        : 'text-gray-500'
                                "
                            >
                                {{
                                    isFaceDetected
                                        ? 'Ambil Foto Selfie'
                                        : 'Posisikan Wajah di Oval'
                                }}
                            </span>
                        </div>
                        <div v-else class="flex justify-center">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="rounded-xl border-gray-300 text-xs font-bold hover:bg-rose-50 hover:text-rose-600"
                                @click="retakePhoto"
                            >
                                <RefreshCw class="mr-1.5 size-3" />
                                Foto Ulang
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- 4. CATATAN / KETERANGAN OPSIONAL -->
                <div class="space-y-1">
                    <Label
                        for="notes"
                        class="text-xs font-semibold text-gray-600 dark:text-gray-400"
                    >
                        Catatan Kehadiran (Opsional):
                    </Label>
                    <Input
                        id="notes"
                        v-model="notes"
                        placeholder="Contoh: Bertugas di kasir meja 1 / shift ganti..."
                        class="h-9 rounded-xl text-xs"
                    />
                </div>

                <!-- SUBMIT ACTIONS -->
                <div class="space-y-2 pt-2">
                    <Button
                        type="button"
                        class="h-12 w-full rounded-2xl text-xs font-bold text-white shadow-lg shadow-rose-600/25 transition-all"
                        :class="[
                            mode === 'clock_in'
                                ? 'bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700'
                                : 'bg-gradient-to-r from-zinc-900 to-rose-900 hover:from-zinc-950 hover:to-rose-950',
                        ]"
                        :disabled="
                            isSubmitting || !isInsideRadius || !capturedPhoto
                        "
                        @click="submitAttendance"
                    >
                        <Spinner v-if="isSubmitting" class="mr-2 size-4" />
                        <CheckCircle2 v-else class="mr-2 size-4" />
                        <span>
                            {{
                                isSubmitting
                                    ? 'Memproses Absensi...'
                                    : mode === 'clock_in'
                                      ? 'Konfirmasi Clock In Sekarang'
                                      : 'Konfirmasi Clock Out Sekarang'
                            }}
                        </span>
                    </Button>

                    <div class="text-center">
                        <button
                            type="button"
                            class="py-1 text-xs font-medium text-gray-400 hover:text-gray-600"
                            @click="closeModal"
                            :disabled="isSubmitting"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
