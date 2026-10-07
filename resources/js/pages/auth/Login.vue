<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    DollarSign,
    KeyRound,
    Lock,
    LogIn,
    Mail,
    MapPin,
    Shield,
    Sparkles,
    UserCheck,
    Users,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: false, // Custom full-bleed split cover layout
});

const props = defineProps<{
    status?: string;
    canResetPassword?: boolean;
}>();

const form = useForm({
    email: 'owner@momoyo.id',
    password: 'password',
    remember: true,
});

const isQuickLoggingIn = ref(false);

const demoAccounts = [
    {
        role: 'owner',
        label: 'Owner',
        name: 'Budi Hartono',
        email: 'owner@momoyo.id',
        desc: 'Eksekutif se-jaringan',
        badge: 'Supervision',
        badgeColor:
            'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
        icon: Building2,
    },
    {
        role: 'superadmin',
        label: 'Superadmin',
        name: 'Rian Hidayat',
        email: 'superadmin@momoyo.id',
        desc: 'Master Cabang & Rolling',
        badge: 'Full Access',
        badgeColor:
            'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300',
        icon: Shield,
    },
    {
        role: 'finance_pusat',
        label: 'Finance Pusat',
        name: 'Dewi Lestari',
        email: 'finance@momoyo.id',
        desc: 'Verifikasi Setoran Harian',
        badge: 'Reconciliation',
        badgeColor:
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
        icon: DollarSign,
    },
    {
        role: 'store_manager',
        label: 'Store Manager',
        name: 'Agus Pratama',
        email: 'manager.tebet@momoyo.id',
        desc: 'Closing & Approval Toko',
        badge: 'Tebet Raya',
        badgeColor:
            'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
        icon: UserCheck,
    },
    {
        role: 'crew_kasir',
        label: 'Crew Kasir',
        name: 'Siti Rahma',
        email: 'kasir.tebet@momoyo.id',
        desc: 'Clock-in GPS & Input Kas',
        badge: 'Outlet Frontliner',
        badgeColor:
            'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
        icon: Users,
    },
];

const selectDemoAccount = (
    demo: (typeof demoAccounts)[number],
    autoSubmit = false,
) => {
    form.email = demo.email;
    form.password = 'password';

    if (autoSubmit) {
        submitLogin();
    }
};

const submitLogin = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Masuk - Momoyo Ops & Cashflow" />

    <div
        class="flex min-h-screen w-full flex-col bg-slate-50 font-sans lg:flex-row dark:bg-zinc-950"
    >
        <!-- LEFT PANEL: Hero Brand & Feature Showcase (Desktop) -->
        <div
            class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-red-600 via-rose-700 to-amber-700 p-12 text-white lg:flex lg:w-1/2 xl:w-5/12"
        >
            <!-- Background Decorative Blobs -->
            <div
                class="pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full bg-white/10 blur-3xl"
            ></div>
            <div
                class="pointer-events-none absolute -right-24 -bottom-24 h-96 w-96 rounded-full bg-amber-400/20 blur-3xl"
            ></div>

            <!-- Brand Header -->
            <div class="relative z-10">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-11 items-center justify-center rounded-2xl bg-white text-2xl font-black text-rose-600 shadow-xl shadow-black/20"
                    >
                        M
                    </div>
                    <div>
                        <h1
                            class="text-2xl leading-none font-black tracking-tight text-white"
                        >
                            MOMOYO
                        </h1>
                        <p
                            class="mt-1 text-xs font-semibold tracking-wider text-rose-200 uppercase"
                        >
                            Multi-Branch Operations & Cashflow
                        </p>
                    </div>
                </div>
            </div>

            <!-- Value Props Carousel/Grid -->
            <div class="relative z-10 my-auto space-y-6 py-8">
                <div>
                    <span
                        class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/15 px-3 py-1 text-xs font-semibold text-rose-100 backdrop-blur-md"
                    >
                        <Sparkles class="size-3.5 text-amber-300" /> Standar
                        Operasional Digital Gerai
                    </span>
                    <h2 class="text-3xl leading-tight font-black text-white">
                        Kendali Penuh Seluruh Cabang dalam Satu Layar.
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed text-rose-100">
                        Gantikan rekap WhatsApp manual dengan validasi
                        Geofencing GPS real-time, pencatatan multi-metode bayar,
                        dan rekonsiliasi setoran pusat terpusat.
                    </p>
                </div>

                <!-- 3 Key Features -->
                <div class="space-y-3.5 pt-2">
                    <div
                        class="flex items-start gap-3 rounded-xl border border-white/15 bg-white/10 p-3.5 backdrop-blur-md transition-colors hover:bg-white/15"
                    >
                        <div
                            class="mt-0.5 shrink-0 rounded-lg bg-white/20 p-2 text-white"
                        >
                            <MapPin class="size-4" />
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-white">
                                Absensi Geofencing & Selfie Kamera Langsung
                            </h4>
                            <p class="mt-0.5 text-xs text-rose-100">
                                Validasi radius toko otomatis menggunakan
                                formula Haversine untuk mencegah manipulasi
                                kehadiran.
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-start gap-3 rounded-xl border border-white/15 bg-white/10 p-3.5 backdrop-blur-md transition-colors hover:bg-white/15"
                    >
                        <div
                            class="mt-0.5 shrink-0 rounded-lg bg-white/20 p-2 text-white"
                        >
                            <DollarSign class="size-4" />
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-white">
                                Rekap Closing Terstruktur & Kas Kecil
                            </h4>
                            <p class="mt-0.5 text-xs text-rose-100">
                                Rincian otomatis omzet Tunai, QRIS, EDC,
                                GrabFood, GoFood, ShopeeFood beserta bukti bon
                                nota.
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-start gap-3 rounded-xl border border-white/15 bg-white/10 p-3.5 backdrop-blur-md transition-colors hover:bg-white/15"
                    >
                        <div
                            class="mt-0.5 shrink-0 rounded-lg bg-white/20 p-2 text-white"
                        >
                            <CheckCircle2 class="size-4" />
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-white">
                                Verifikasi Mutasi Finance Pusat
                            </h4>
                            <p class="mt-0.5 text-xs text-rose-100">
                                Inspeksi transfer bank kasir dengan lightbox
                                thumbnail, peringatan selisih kas, dan approval
                                1-klik.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Left -->
            <div
                class="relative z-10 flex items-center justify-between border-t border-white/20 pt-4 text-xs text-rose-200"
            >
                <span>© 2026 PT Momoyo Kuliner Indonesia</span>
                <span class="flex items-center gap-1.5 font-medium">
                    <span
                        class="size-2 animate-pulse rounded-full bg-emerald-400"
                    ></span>
                    Sistem Operasional Aktif
                </span>
            </div>
        </div>

        <!-- RIGHT PANEL: Login Form & Role Quick-Selector -->
        <div
            class="flex flex-1 flex-col justify-between overflow-y-auto p-6 sm:p-10 lg:p-12"
        >
            <!-- Mobile Brand Header -->
            <div
                class="flex items-center justify-between border-b border-gray-200 pb-6 lg:hidden dark:border-zinc-800"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-rose-600 text-lg font-black text-white"
                    >
                        M
                    </div>
                    <div>
                        <span
                            class="font-extrabold text-gray-900 dark:text-white"
                            >MOMOYO</span
                        >
                        <p
                            class="text-[10px] font-semibold text-rose-600 uppercase"
                        >
                            Ops & Cashflow
                        </p>
                    </div>
                </div>
                <span
                    class="rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-300"
                >
                    Karyawan & Manajemen
                </span>
            </div>

            <!-- Center Form Container -->
            <div class="mx-auto my-auto w-full max-w-md py-6">
                <!-- Header -->
                <div class="mb-6">
                    <h2
                        class="text-2xl font-black tracking-tight text-gray-900 sm:text-3xl dark:text-white"
                    >
                        Masuk ke Akun Anda
                    </h2>
                    <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">
                        Silakan masuk untuk mengelola operasional gerai,
                        absensi, atau laporan kas harian.
                    </p>
                </div>

                <!-- Flash Status Alert -->
                <div
                    v-if="status"
                    class="mb-5 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                >
                    <CheckCircle2 class="size-4 shrink-0 text-emerald-500" />
                    {{ status }}
                </div>

                <!-- DEMO ACCOUNT QUICK-PICKER -->
                <div
                    class="mb-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="mb-2.5 flex items-center justify-between">
                        <span
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-gray-700 uppercase dark:text-gray-300"
                        >
                            <Sparkles class="size-3.5 text-rose-500" /> Akses
                            Cepat Demo Role (1-Klik):
                        </span>
                        <span class="text-[10px] text-gray-400"
                            >Pilih untuk auto-fill</span
                        >
                    </div>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <button
                            v-for="demo in demoAccounts"
                            :key="demo.email"
                            type="button"
                            @click="selectDemoAccount(demo)"
                            :class="[
                                'flex items-center gap-2.5 rounded-xl border p-2 text-left text-xs transition-all',
                                form.email === demo.email
                                    ? 'border-rose-500 bg-rose-50/70 font-semibold text-rose-900 ring-1 ring-rose-500 dark:bg-rose-950/30 dark:text-rose-200'
                                    : 'border-gray-200 bg-gray-50/50 text-gray-700 hover:border-gray-300 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-gray-300 dark:hover:border-zinc-700',
                            ]"
                        >
                            <component
                                :is="demo.icon"
                                class="size-4 shrink-0 text-rose-500"
                            />
                            <div class="flex-1 truncate">
                                <div class="flex items-center justify-between">
                                    <span class="truncate font-bold">{{
                                        demo.label
                                    }}</span>
                                    <span
                                        :class="[
                                            'py-0.2 rounded px-1.5 text-[9px] font-semibold',
                                            demo.badgeColor,
                                        ]"
                                        >{{ demo.badge }}</span
                                    >
                                </div>
                                <p
                                    class="truncate text-[10px] text-gray-500 dark:text-gray-400"
                                >
                                    {{ demo.email }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Main Form -->
                <form @submit.prevent="submitLogin" class="space-y-4">
                    <!-- Email Input -->
                    <div class="space-y-1.5">
                        <Label
                            for="email"
                            class="text-xs font-semibold text-gray-700 dark:text-gray-300"
                        >
                            Email Momoyo
                        </Label>
                        <div class="relative">
                            <div
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"
                            >
                                <Mail class="size-4" />
                            </div>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="nama@momoyo.id"
                                class="h-11 rounded-xl border-gray-300 bg-white pl-10 text-sm focus:border-rose-500 focus:ring-rose-500 dark:border-zinc-800 dark:bg-zinc-900"
                            />
                        </div>
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Password Input -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label
                                for="password"
                                class="text-xs font-semibold text-gray-700 dark:text-gray-300"
                            >
                                Password
                            </Label>
                            <span class="text-[11px] text-gray-400"
                                >Default: password</span
                            >
                        </div>
                        <div class="relative">
                            <div
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"
                            >
                                <Lock class="size-4" />
                            </div>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="h-11 rounded-xl border-gray-300 bg-white pl-10 text-sm focus:border-rose-500 focus:ring-rose-500 dark:border-zinc-800 dark:bg-zinc-900"
                            />
                        </div>
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between pt-1">
                        <label
                            class="flex cursor-pointer items-center gap-2 text-xs text-gray-600 select-none dark:text-gray-400"
                        >
                            <Checkbox
                                id="remember"
                                v-model:checked="form.remember"
                            />
                            <span>Ingat saya di perangkat ini</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-2 flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-red-600 via-rose-600 to-rose-700 text-sm font-bold text-white shadow-md shadow-rose-600/25 transition-all hover:from-red-700 hover:to-rose-800"
                    >
                        <Spinner
                            v-if="form.processing"
                            class="size-4 text-white"
                        />
                        <LogIn v-else class="size-4" />
                        <span>Masuk ke Dashboard Momoyo</span>
                    </Button>
                </form>

                <!-- Security & Privacy Note -->
                <div
                    class="mt-6 flex items-center justify-center gap-2 border-t border-gray-200 pt-5 text-center text-xs text-gray-400 dark:border-zinc-800/80 dark:text-gray-500"
                >
                    <KeyRound class="size-3.5 text-rose-500" />
                    <span
                        >Akses terproteksi Role-Based Access Control (RBAC) &
                        Geofencing GPS</span
                    >
                </div>
            </div>

            <!-- Footer Right -->
            <div
                class="py-2 text-center text-xs text-gray-400 dark:text-gray-600"
            >
                Momoyo Operations & Cashflow Platform • Versi 2.4.0
            </div>
        </div>
    </div>
</template>
