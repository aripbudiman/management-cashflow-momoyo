<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Camera,
    CheckCircle2,
    FlipHorizontal,
    RefreshCw,
    ScanFace,
    Sparkles,
    UploadCloud,
    X,
} from '@lucide/vue';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
    open: boolean;
    employeeName?: string;
    isUpdating?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

// Video stream state
const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const mediaStream = ref<MediaStream | null>(null);
const isCameraActive = ref(false);
const cameraError = ref<string | null>(null);
const facingMode = ref<'user' | 'environment'>('user');
const capturedPhoto = ref<string | null>(null);
const isSubmitting = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

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
        }
    } catch (err: any) {
        console.error('Camera access error:', err);
        isCameraActive.value = false;
        if (err.name === 'NotAllowedError') {
            cameraError.value =
                'Izin kamera ditolak. Harap izinkan akses kamera di browser smartphone Anda.';
        } else if (err.name === 'NotFoundError') {
            cameraError.value = 'Kamera tidak ditemukan pada perangkat ini.';
        } else {
            cameraError.value =
                'Gagal mengaktifkan kamera. Anda dapat mengunggah foto selfie langsung dari galeri atau kamera bawaan.';
        }
    }
};

// Stop camera stream
const stopCamera = () => {
    if (mediaStream.value) {
        mediaStream.value.getTracks().forEach((track) => track.stop());
        mediaStream.value = null;
    }
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    isCameraActive.value = false;
};

// Toggle Front / Back camera
const toggleCameraFacing = async () => {
    facingMode.value = facingMode.value === 'user' ? 'environment' : 'user';
    await startCamera();
};

// Capture photo snapshot from video canvas
const capturePhoto = () => {
    if (!videoRef.value) return;

    if (navigator.vibrate) {
        navigator.vibrate(50);
    }

    const video = videoRef.value;
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 640;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Flip horizontally if front camera for mirror feel
    if (facingMode.value === 'user') {
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
    }

    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    capturedPhoto.value = canvas.toDataURL('image/jpeg', 0.88);

    stopCamera();
};

// Retake photo
const retakePhoto = () => {
    capturedPhoto.value = null;
    startCamera();
};

// Fallback file upload input
const handleFileUpload = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = (e) => {
            capturedPhoto.value = e.target?.result as string;
            stopCamera();
        };
        reader.readAsDataURL(file);
    }
};

// Submit face registration to server
const submitFaceRegistration = () => {
    if (!capturedPhoto.value) return;

    isSubmitting.value = true;
    router.post(
        '/attendance/register-face',
        {
            photo: capturedPhoto.value,
            face_data: {
                registered_at: new Date().toISOString(),
                device: navigator.userAgent,
            },
        },
        {
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
        },
    );
};

const closeModal = () => {
    stopCamera();
    capturedPhoto.value = null;
    cameraError.value = null;
    emit('update:open', false);
};

// Watch modal open/close
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            capturedPhoto.value = null;
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
            class="max-h-[92vh] w-full overflow-y-auto rounded-3xl border-rose-200/60 p-0 sm:max-w-md dark:border-zinc-800"
        >
            <!-- Header with Gradient -->
            <div
                class="relative bg-gradient-to-r from-red-600 via-rose-600 to-amber-600 p-5 text-white"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex size-10 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-md"
                        >
                            <ScanFace class="size-5 text-white" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold text-white">
                                {{
                                    isUpdating
                                        ? 'Perbarui Face ID'
                                        : 'Pendaftaran Face ID'
                                }}
                            </DialogTitle>
                            <DialogDescription class="text-xs text-rose-100">
                                {{
                                    employeeName
                                        ? `Untuk ${employeeName}`
                                        : 'Wajah referensi absensi kru'
                                }}
                            </DialogDescription>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Body / Camera Viewport -->
            <div class="space-y-4 p-5">
                <!-- Guidance Text -->
                <div
                    class="flex items-start gap-2.5 rounded-2xl border border-amber-200/70 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-200"
                >
                    <Sparkles class="mt-0.5 size-4 shrink-0 text-amber-600" />
                    <div>
                        <span class="font-bold"
                            >Panduan Pengambilan Wajah:</span
                        >
                        <ul
                            class="mt-1 list-inside list-disc space-y-0.5 text-amber-800/90 dark:text-amber-300"
                        >
                            <li>
                                Pastikan wajah Anda berada tepat di dalam
                                lingkaran oval.
                            </li>
                            <li>
                                Lepaskan masker dan kacamata hitam agar
                                terverifikasi akurat.
                            </li>
                            <li>Gunakan pencahayaan yang terang dan merata.</li>
                        </ul>
                    </div>
                </div>

                <!-- Camera / Preview Frame -->
                <div
                    class="relative mx-auto flex aspect-square w-full max-w-[320px] items-center justify-center overflow-hidden rounded-3xl border-2 border-rose-100 bg-zinc-950 shadow-inner dark:border-zinc-800"
                >
                    <!-- Mode 1: Live Camera Stream -->
                    <template v-if="!capturedPhoto">
                        <video
                            ref="videoRef"
                            class="absolute inset-0 size-full object-cover"
                            :class="{ '-scale-x-100': facingMode === 'user' }"
                            autoplay
                            playsinline
                            muted
                        ></video>

                        <!-- Oval Guide Overlay for Face Framing -->
                        <div
                            class="pointer-events-none absolute inset-0 flex items-center justify-center"
                        >
                            <!-- Oval cutout guide with animated glow border -->
                            <div
                                class="relative flex h-[82%] w-[70%] items-center justify-center rounded-[50%] border-2 border-dashed border-rose-400/90 shadow-[0_0_20px_rgba(244,63,94,0.35)]"
                            >
                                <!-- Scanner line micro-animation -->
                                <div
                                    class="absolute inset-x-4 h-0.5 animate-pulse bg-gradient-to-r from-transparent via-rose-400 to-transparent"
                                ></div>
                            </div>
                        </div>

                        <!-- Live Status Pill -->
                        <div
                            class="pointer-events-none absolute inset-x-0 top-3 flex justify-center"
                        >
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-1 text-[11px] font-semibold text-white backdrop-blur-md"
                            >
                                <span
                                    class="size-2 animate-ping rounded-full bg-emerald-500"
                                ></span>
                                Posisikan wajah di dalam oval
                            </span>
                        </div>

                        <!-- Switch Camera Button -->
                        <button
                            type="button"
                            @click="toggleCameraFacing"
                            class="absolute top-3 right-3 flex size-9 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur-md transition-colors hover:bg-white/30"
                            title="Balik Kamera Depan/Belakang"
                        >
                            <FlipHorizontal class="size-4" />
                        </button>

                        <!-- Camera Error State Overlay -->
                        <div
                            v-if="cameraError"
                            class="absolute inset-0 flex flex-col items-center justify-center bg-zinc-950/90 p-4 text-center text-white"
                        >
                            <AlertCircle class="mb-2 size-8 text-rose-500" />
                            <p
                                class="mb-4 max-w-xs text-xs leading-relaxed text-rose-200"
                            >
                                {{ cameraError }}
                            </p>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="rounded-xl border-white/20 text-xs text-white hover:bg-white/10"
                                @click="fileInputRef?.click()"
                            >
                                <UploadCloud class="mr-1.5 size-3.5" />
                                Pilih Foto / Buka Kamera HP
                            </Button>
                        </div>
                    </template>

                    <!-- Mode 2: Captured Photo Preview -->
                    <template v-else>
                        <img
                            :src="capturedPhoto"
                            alt="Hasil Foto Face ID"
                            class="size-full object-cover"
                        />
                        <div
                            class="absolute inset-x-0 top-3 flex justify-center"
                        >
                            <Badge
                                class="bg-emerald-600 px-3 py-1 text-xs font-semibold text-white shadow-md"
                            >
                                <CheckCircle2 class="mr-1 size-3.5" />
                                Foto Wajah Terambil
                            </Badge>
                        </div>
                    </template>
                </div>

                <!-- Hidden file input fallback -->
                <input
                    ref="fileInputRef"
                    type="file"
                    accept="image/*"
                    capture="user"
                    class="hidden"
                    @change="handleFileUpload"
                />

                <!-- Controls & Action Buttons -->
                <div class="space-y-2 pt-1">
                    <!-- Action State 1: Ready to Snap -->
                    <div
                        v-if="!capturedPhoto"
                        class="flex flex-col items-center gap-2"
                    >
                        <button
                            type="button"
                            @click="capturePhoto"
                            class="group relative flex size-18 items-center justify-center rounded-full bg-gradient-to-tr from-rose-600 to-red-500 p-1 shadow-lg shadow-rose-600/30 transition-transform hover:scale-105 active:scale-95"
                            :disabled="!isCameraActive"
                        >
                            <div
                                class="flex size-full items-center justify-center rounded-full border-2 border-white/80 bg-rose-600 transition-colors group-hover:bg-rose-500"
                            >
                                <Camera class="size-7 text-white" />
                            </div>
                        </button>
                        <span
                            class="text-[11px] font-bold tracking-wide text-gray-500"
                        >
                            Ketuk untuk Mengambil Foto Wajah
                        </span>
                    </div>

                    <!-- Action State 2: Preview & Confirm -->
                    <div v-else class="grid grid-cols-2 gap-2.5">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-11 rounded-xl border-gray-300 text-xs font-bold hover:bg-rose-50 hover:text-rose-600 dark:border-zinc-700"
                            :disabled="isSubmitting"
                            @click="retakePhoto"
                        >
                            <RefreshCw class="mr-1.5 size-3.5" />
                            Foto Ulang
                        </Button>
                        <Button
                            type="button"
                            class="h-11 rounded-xl bg-rose-600 text-xs font-bold text-white shadow-md shadow-rose-600/30 hover:bg-rose-700"
                            :disabled="isSubmitting"
                            @click="submitFaceRegistration"
                        >
                            <Spinner
                                v-if="isSubmitting"
                                class="mr-1.5 size-4"
                            />
                            <CheckCircle2 v-else class="mr-1.5 size-4" />
                            <span>{{
                                isSubmitting
                                    ? 'Mendaftarkan...'
                                    : 'Daftarkan Face ID'
                            }}</span>
                        </Button>
                    </div>

                    <!-- Cancel / Close Button -->
                    <div class="pt-1 text-center">
                        <button
                            type="button"
                            class="py-1 text-xs font-medium text-gray-400 hover:text-gray-600"
                            @click="closeModal"
                            :disabled="isSubmitting"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
