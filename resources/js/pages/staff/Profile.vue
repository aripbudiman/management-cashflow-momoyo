<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, Cake, CreditCard, LogOut, MapPin, Palette, Phone, Settings } from '@lucide/vue';
import { computed } from 'vue';
import { fmtDate } from '@/lib/format';

const props = defineProps<{
    employee: {
        full_name: string;
        nik: string;
        face_photo_url: string | null;
        birth_place: string | null;
        birth_date: string | null;
        address: string | null;
        phone_number: string | null;
        bank_name: string | null;
        bank_account_number: string | null;
        bank_account_name: string | null;
        branch: { branch_name: string; branch_code: string } | null;
    } | null;
    account: { name: string; email: string };
}>();

const initials = computed(() => (props.employee?.full_name ?? props.account.name).split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase());
const ttl = computed(() => {
    const e = props.employee;
    if (!e?.birth_place && !e?.birth_date) return '-';
    return [e?.birth_place, e?.birth_date ? fmtDate(e.birth_date) : null].filter(Boolean).join(', ');
});

const logout = () => {
    router.flushAll();
    router.post('/logout');
};
</script>

<template>
    <Head title="Profil Saya" />

    <div>
        <header class="rounded-b-[2.5rem] bg-gradient-to-b from-red-600 via-rose-600 to-amber-600 px-5 pt-8 pb-16 text-center text-white">
            <div class="mx-auto size-20 overflow-hidden rounded-full border-2 border-white/50 bg-white/20">
                <img v-if="employee?.face_photo_url" :src="employee.face_photo_url" alt="Foto" class="size-full object-cover" />
                <div v-else class="flex size-full items-center justify-center text-2xl font-black">{{ initials }}</div>
            </div>
            <h1 class="mt-3 text-xl font-black">{{ employee?.full_name ?? account.name }}</h1>
            <p class="font-mono text-sm text-rose-100">{{ employee?.nik }}</p>
        </header>

        <div class="-mt-10 space-y-4 px-4">
            <section v-if="employee" class="space-y-4 rounded-3xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                <h2 class="text-xs font-black tracking-wider text-gray-400 uppercase">Data Pribadi</h2>
                <div class="flex gap-3"><Building2 class="mt-0.5 size-4 shrink-0 text-rose-600" /><div><div class="text-xs text-gray-500">Penempatan</div><div class="text-sm font-semibold">{{ employee.branch?.branch_name ?? '-' }}</div></div></div>
                <div class="flex gap-3"><Cake class="mt-0.5 size-4 shrink-0 text-rose-600" /><div><div class="text-xs text-gray-500">Tempat, Tanggal Lahir</div><div class="text-sm font-semibold">{{ ttl }}</div></div></div>
                <div class="flex gap-3"><MapPin class="mt-0.5 size-4 shrink-0 text-rose-600" /><div><div class="text-xs text-gray-500">Alamat</div><div class="text-sm font-semibold">{{ employee.address || '-' }}</div></div></div>
                <div class="flex gap-3"><Phone class="mt-0.5 size-4 shrink-0 text-rose-600" /><div><div class="text-xs text-gray-500">No. Telepon / WA</div><div class="text-sm font-semibold">{{ employee.phone_number || '-' }}</div></div></div>
            </section>

            <section v-if="employee" class="space-y-3 rounded-3xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                <h2 class="text-xs font-black tracking-wider text-gray-400 uppercase">Rekening Gaji</h2>
                <div class="flex gap-3"><CreditCard class="mt-0.5 size-4 shrink-0 text-rose-600" />
                    <div v-if="employee.bank_name">
                        <div class="text-sm font-semibold">{{ employee.bank_name }}</div>
                        <div class="font-mono text-sm">{{ employee.bank_account_number }}</div>
                        <div class="text-xs text-gray-500 uppercase">a.n. {{ employee.bank_account_name }}</div>
                    </div>
                    <div v-else class="text-sm text-gray-400">Belum diisi. Hubungi Admin Pusat.</div>
                </div>
                <p class="text-[11px] text-gray-400">Data ini dikelola Admin Pusat. Hubungi admin jika ada yang perlu diperbarui.</p>
            </section>

            <section class="divide-y overflow-hidden rounded-3xl bg-white shadow-sm dark:divide-zinc-800 dark:bg-zinc-900">
                <Link href="/settings/profile" class="flex items-center gap-3 px-5 py-3.5 text-sm font-semibold"><Settings class="size-4 text-gray-500" /> Pengaturan Akun <span class="ml-auto text-xs text-gray-400">{{ account.email }}</span></Link>
                <Link href="/settings/appearance" class="flex items-center gap-3 px-5 py-3.5 text-sm font-semibold"><Palette class="size-4 text-gray-500" /> Tampilan</Link>
                <button type="button" class="flex w-full items-center gap-3 px-5 py-3.5 text-left text-sm font-semibold text-red-600" @click="logout"><LogOut class="size-4" /> Keluar</button>
            </section>
        </div>
    </div>
</template>
