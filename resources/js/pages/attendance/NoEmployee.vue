<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, Building2, UserX } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
    };
}>();
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Operasional', href: '#' },
            { title: 'Absensi', href: '/attendance' },
        ]"
    >
        <Head title="Profil Karyawan Belum Terhubung - Momoyo" />

        <div class="mx-auto max-w-lg p-6 py-12">
            <Card
                class="border-rose-200/80 text-center shadow-lg dark:border-zinc-800"
            >
                <CardHeader class="flex flex-col items-center pb-2">
                    <div
                        class="mb-3 flex size-16 items-center justify-center rounded-3xl bg-rose-100 text-rose-600 dark:bg-rose-950/60"
                    >
                        <UserX class="size-8" />
                    </div>
                    <CardTitle class="text-xl font-bold"
                        >Profil Karyawan Belum Dihubungkan</CardTitle
                    >
                    <CardDescription class="mt-1 max-w-sm text-xs">
                        Akun login <strong>{{ user.name }}</strong> ({{
                            user.email
                        }}) belum terdaftar pada master karyawan cabang gerai
                        Momoyo.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4 pt-2">
                    <div
                        class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-left text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
                    >
                        <div class="mb-1 flex items-center gap-1.5 font-bold">
                            <AlertCircle class="size-4 text-amber-600" />
                            Petunjuk untuk Staf Cabang:
                        </div>
                        <p class="leading-relaxed">
                            Silakan hubungi
                            <strong>Admin Pusat Momoyo</strong> agar akun Anda
                            dikaitkan dengan NIK karyawan dan cabang penempatan
                            gerai Anda. Setelah dikaitkan, Anda dapat
                            mendaftarkan Face ID dan melakukan absensi.
                        </p>
                    </div>

                    <div class="pt-2">
                        <Button
                            as-child
                            class="w-full rounded-xl bg-rose-600 text-white hover:bg-rose-700"
                        >
                            <Link href="/dashboard">
                                <ArrowLeft class="mr-1.5 size-4" />
                                Kembali ke Beranda
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
