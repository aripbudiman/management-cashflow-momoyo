<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { HardHat, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fmtDate } from '@/lib/format';
import type { Project } from '@/lib/project';
import { statusClass, statusLabels } from '@/lib/project';

interface Paginated<T> {
    data: T[];
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    projects: Paginated<Project>;
    stats: { total: number; by_status: Record<string, number>; avg_progress: number; terlambat: number; item_terlambat: number };
    branches: { id: number; branch_name: string }[];
    filters: { search?: string; status?: string; branch_id?: string };
}>();

const selectCls = 'h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const applyFilter = () => {
    router.get('/projects', { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true });
};

const canCreate = computed(() => props.branches.length > 0);
const isOpen = ref(false);
const form = useForm({
    kode: '',
    nama: '',
    client_name: '',
    branch_id: '' as number | '',
    alamat: '',
    luas_m2: '' as number | '',
});

const submit = () => {
    form.post('/projects', { onSuccess: () => form.reset() });
};
</script>

<template>
    <Head title="Proyek Lapangan - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Proyek Lapangan</h1>
                <p class="text-sm text-gray-500">Monitoring RAB dan progress pekerjaan renovasi/pembangunan outlet.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Input v-model="search" placeholder="Cari kode / nama / klien..." class="h-9 w-56" @keyup.enter="applyFilter" />
                <select v-model="status" :class="[selectCls, 'w-40']" @change="applyFilter">
                    <option value="">Semua status</option>
                    <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                </select>
                <Button v-if="canCreate" class="gap-1.5" @click="isOpen = true"><Plus class="size-4" /> Proyek Baru</Button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Card><CardContent class="p-4"><div class="text-xs text-gray-500">Total Proyek</div><div class="text-2xl font-black">{{ stats.total }}</div></CardContent></Card>
            <Card><CardContent class="p-4"><div class="text-xs text-gray-500">Berjalan</div><div class="text-2xl font-black text-amber-600">{{ stats.by_status.berjalan ?? 0 }}</div></CardContent></Card>
            <Card><CardContent class="p-4"><div class="text-xs text-gray-500">Rata-rata Progress</div><div class="text-2xl font-black">{{ stats.avg_progress }}%</div></CardContent></Card>
            <Card><CardContent class="p-4"><div class="text-xs text-gray-500">Terlambat</div><div class="text-2xl font-black text-red-600">{{ stats.terlambat }} <span class="text-xs font-semibold text-gray-500">proyek · {{ stats.item_terlambat }} item</span></div></CardContent></Card>
        </div>

        <Card>
            <CardHeader class="border-b pb-3">
                <CardTitle class="flex items-center gap-2 text-sm font-bold"><HardHat class="size-4 text-rose-500" /> {{ projects.total }} Proyek</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="projects.data.length === 0" class="p-8 text-center text-sm text-gray-400">Belum ada proyek.</div>
                <div v-else class="divide-y">
                    <Link v-for="p in projects.data" :key="p.id" :href="`/projects/${p.id}`" class="block space-y-2 p-4 hover:bg-gray-50 dark:hover:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-bold text-gray-900 dark:text-white">{{ p.nama }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ p.kode }} · {{ p.branch?.branch_name }}<template v-if="p.client_name"> · {{ p.client_name }}</template>
                                    <template v-if="p.tgl_selesai_rencana"> · target {{ fmtDate(p.tgl_selesai_rencana) }}</template>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold" :class="statusClass[p.status]">{{ statusLabels[p.status] }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-zinc-800">
                                <div class="h-full rounded-full bg-rose-500" :style="{ width: `${p.progress}%` }" />
                            </div>
                            <span class="w-12 text-right text-xs font-bold">{{ p.progress }}%</span>
                        </div>
                    </Link>
                </div>
            </CardContent>
        </Card>

        <div v-if="projects.links.length > 3" class="flex flex-wrap gap-1">
            <Button
                v-for="(l, idx) in projects.links"
                :key="idx"
                size="sm"
                :variant="l.active ? 'default' : 'outline'"
                :disabled="!l.url"
                @click="l.url && router.get(l.url, {}, { preserveState: true })"
                v-html="l.label"
            />
        </div>

        <Dialog v-model:open="isOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader><DialogTitle>Proyek Baru</DialogTitle></DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="kode">Kode Proyek</Label>
                            <Input id="kode" v-model="form.kode" placeholder="MM002535" />
                            <InputError :message="form.errors.kode" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="client_name">Klien / Brand</Label>
                            <Input id="client_name" v-model="form.client_name" />
                            <InputError :message="form.errors.client_name" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="nama">Nama Proyek</Label>
                        <Input id="nama" v-model="form.nama" placeholder="MMY-JTU-WINONG KIDUL" />
                        <InputError :message="form.errors.nama" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="branch_id">Cabang / Outlet</Label>
                            <select id="branch_id" v-model="form.branch_id" :class="selectCls">
                                <option value="">Pilih cabang...</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.branch_name }}</option>
                            </select>
                            <InputError :message="form.errors.branch_id" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="alamat">Alamat</Label>
                        <Input id="alamat" v-model="form.alamat" />
                        <InputError :message="form.errors.alamat" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="luas">Luas (m²)</Label>
                        <Input id="luas" v-model="form.luas_m2" type="number" step="0.01" min="0" />
                        <InputError :message="form.errors.luas_m2" />
                    </div>
                    <p class="text-xs text-gray-500">Jadwal, nilai kontrak, PIC, dan cara hitung progress diatur nanti di tab Ringkasan.</p>
                    <DialogFooter><Button type="submit" :disabled="form.processing">Simpan Proyek</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
