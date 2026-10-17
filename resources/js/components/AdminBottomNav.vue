<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, LayoutGrid, ScanFace, UserRound, Grid2x2 } from '@lucide/vue';
import { ref } from 'vue';
import AdminMenuSheet from '@/components/AdminMenuSheet.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const { isCurrentUrl } = useCurrentUrl();
const menuOpen = ref(false);

const left = [
    { href: '/dashboard', label: 'Beranda', icon: LayoutGrid },
    { href: '/attendance', label: 'Absensi', icon: ScanFace },
];
const right = [
    { href: '/cashflow-verifications', label: 'Setoran', icon: CheckCircle2 },
    { href: '/my-profile', label: 'Profil', icon: UserRound },
];
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95" style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="mx-auto grid max-w-lg grid-cols-5 items-end px-2">
            <Link
                v-for="item in left"
                :key="item.href"
                :href="item.href"
                class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-semibold"
                :class="isCurrentUrl(item.href) ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400'"
            >
                <component :is="item.icon" class="size-5" />
                {{ item.label }}
            </Link>

            <!-- Tombol tengah: semua menu -->
            <div class="flex justify-center">
                <button
                    type="button"
                    class="-mt-6 flex size-14 items-center justify-center rounded-full bg-rose-600 text-white shadow-lg ring-4 ring-white transition-transform active:scale-95 dark:ring-zinc-950"
                    aria-label="Semua Menu"
                    @click="menuOpen = true"
                >
                    <Grid2x2 class="size-6" />
                </button>
            </div>

            <Link
                v-for="item in right"
                :key="item.href"
                :href="item.href"
                class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-semibold"
                :class="isCurrentUrl(item.href) ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400'"
            >
                <component :is="item.icon" class="size-5" />
                {{ item.label }}
            </Link>
        </div>
    </nav>

    <AdminMenuSheet v-model:open="menuOpen" />
</template>
