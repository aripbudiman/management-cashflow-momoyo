<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarCheck, Check, Paperclip, RefreshCw, Search, Send, X } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Leave {
    id: number;
    type: string;
    start_date: string;
    end_date: string;
    total_days: number;
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
    approval_notes: string | null;
    attachment_full_url: string | null;
    employee?: { full_name: string; nik: string };
    branch?: { branch_name: string } | null;
    approver?: { name: string } | null;
}

const props = defineProps<{
    isAdmin: boolean;
    hasEmployee: boolean;
    typeLabels: Record<string, string>;
    myLeaves: Leave[];
    quota: { quota: number; used: number; pending: number; remaining: number; year: number } | null;
    adminLeaves: { data: Leave[]; links: Array<{ url: string | null; label: string; active: boolean }>; current_page: number; last_page: number; total: number } | null;
    branches: Array<{ id: number; branch_name: string }>;
    filters: { search?: string; branch_id?: string; status?: string; type?: string };
}>();

const today = new Date().toLocaleDateString('sv-SE');
const form = useForm<{
    type: string;
    start_date: string;
    end_date: string;
    reason: string;
    attachment: File | null;
}>({ type: 'izin', start_date: today, end_date: today, reason: '', attachment: null });

const submit = () =>
    form.post('/leaves', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('reason', 'attachment'),
    });

const cancel = (l: Leave) => {
    if (confirm('Batalkan pengajuan ini?')) router.delete(`/leaves/${l.id}`, { preserveScroll: true });
};

// Satu dialog untuk setujui / tolak
const deciding = ref<{ leave: Leave; mode: 'approve' | 'reject' } | null>(null);
const notes = ref('');
const decide = () => {
    if (!deciding.value) return;
    const { leave, mode } = deciding.value;
    router.post(
        `/leaves/${leave.id}/${mode}`,
        { approval_notes: notes.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                deciding.value = null;
                notes.value = '';
            },
        },
    );
};
const openDecision = (leave: Leave, mode: 'approve' | 'reject') => {
    notes.value = '';
    deciding.value = { leave, mode };
};

const search = ref(props.filters.search ?? '');
const applyFilters = (extra: Record<string, string> = {}) =>
    router.get(
        '/leaves',
        { ...props.filters, search: search.value || undefined, ...extra },
        { preserveState: true, preserveScroll: true },
    );
const filter = (k: string, v: string) => applyFilters({ [k]: v });
const resetFilters = () => {
    search.value = '';
    router.get('/leaves', {}, { preserveState: true });
};
const initials = (n?: string) => (n ?? '?').split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const selectCls =
    'h-10 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-rose-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300';

const statusMap = {
    pending: { label: 'Menunggu', cls: 'bg-amber-100 text-amber-700' },
    approved: { label: 'Disetujui', cls: 'bg-emerald-100 text-emerald-700' },
    rejected: { label: 'Ditolak', cls: 'bg-red-100 text-red-700' },
};
const fmt = (d: string) => d.slice(0, 10).split('-').reverse().join('/');
const range = (l: Leave) => (l.start_date.slice(0, 10) === l.end_date.slice(0, 10) ? fmt(l.start_date) : `${fmt(l.start_date)} – ${fmt(l.end_date)}`);
</script>

<template>
    <Head title="Izin, Sakit & Cuti - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-black tracking-tight text-gray-900 dark:text-white">
                <CalendarCheck class="size-6 text-rose-500" /> Pengajuan Izin, Sakit & Cuti
            </h1>
            <p class="text-sm text-gray-500">
                {{ isAdmin ? 'Tinjau dan putuskan pengajuan karyawan seluruh cabang.' : 'Ajukan ke Admin Pusat. Status bisa dipantau di bawah.' }}
            </p>
        </div>

        <!-- FORM PENGAJUAN (karyawan) -->
        <Card v-if="hasEmployee">
            <CardHeader class="border-b pb-3">
                <div class="flex items-center justify-between">
                    <CardTitle class="text-sm font-bold">Buat Pengajuan</CardTitle>
                    <span v-if="quota" class="text-xs text-gray-500">
                        Jatah cuti {{ quota.year }}: <b>{{ quota.remaining }}</b> dari {{ quota.quota }} hari tersisa
                        <span v-if="quota.pending">({{ quota.pending }} hari menunggu)</span>
                    </span>
                </div>
            </CardHeader>
            <CardContent class="p-4">
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label for="type">Jenis</Label>
                        <select
                            id="type"
                            v-model="form.type"
                            class="h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <option v-for="(label, k) in typeLabels" :key="k" :value="k">{{ label }}</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="attachment">Lampiran (surat dokter, dll — opsional)</Label>
                        <Input id="attachment" type="file" accept=".jpg,.jpeg,.png,.pdf" @change="form.attachment = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                        <InputError :message="form.errors.attachment" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="start_date">Tanggal Mulai</Label>
                        <Input id="start_date" v-model="form.start_date" type="date" />
                        <InputError :message="form.errors.start_date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="end_date">Tanggal Selesai</Label>
                        <Input id="end_date" v-model="form.end_date" type="date" :min="form.start_date" />
                        <InputError :message="form.errors.end_date" />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="reason">Alasan</Label>
                        <textarea
                            id="reason"
                            v-model="form.reason"
                            rows="3"
                            class="w-full rounded-md border border-gray-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                            placeholder="Jelaskan alasan pengajuan"
                        />
                        <InputError :message="form.errors.reason" />
                    </div>
                    <div class="sm:col-span-2">
                        <Button type="submit" class="gap-1.5" :disabled="form.processing"><Send class="size-4" /> Kirim Pengajuan</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- RIWAYAT SAYA -->
        <Card v-if="hasEmployee">
            <CardHeader class="border-b pb-3"><CardTitle class="text-sm font-bold">Riwayat Pengajuan Saya</CardTitle></CardHeader>
            <CardContent class="p-0">
                <div v-if="!myLeaves.length" class="p-8 text-center text-sm text-gray-400">Belum ada pengajuan.</div>
                <div v-else class="divide-y">
                    <div v-for="l in myLeaves" :key="l.id" class="flex flex-col gap-2 p-4 text-sm sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-0.5">
                            <div class="font-bold">{{ typeLabels[l.type] }} · {{ range(l) }} <span class="font-normal text-gray-500">({{ l.total_days }} hari)</span></div>
                            <div class="text-xs text-gray-500">{{ l.reason }}</div>
                            <div v-if="l.approval_notes" class="text-xs text-gray-600">
                                Catatan {{ l.approver?.name || 'admin' }}: {{ l.approval_notes }}
                            </div>
                            <a v-if="l.attachment_full_url" :href="l.attachment_full_url" target="_blank" class="inline-flex items-center gap-1 text-xs text-rose-600">
                                <Paperclip class="size-3" /> Lampiran
                            </a>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold" :class="statusMap[l.status].cls">{{ statusMap[l.status].label }}</span>
                            <Button v-if="l.status === 'pending'" size="sm" variant="outline" @click="cancel(l)">Batalkan</Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- PERSETUJUAN (admin pusat) -->
        <template v-if="isAdmin && adminLeaves">
            <Card class="border-gray-200 shadow-sm dark:border-zinc-800">
                <CardContent class="p-4">
                    <div class="grid grid-cols-1 items-center gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div class="relative lg:col-span-2">
                            <Search class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
                            <Input
                                v-model="search"
                                placeholder="Cari Nama atau NIK karyawan..."
                                class="h-10 rounded-xl pl-10 text-xs"
                                @keydown.enter="applyFilters()"
                            />
                        </div>
                        <select :value="filters.branch_id ?? ''" :class="selectCls" @change="filter('branch_id', ($event.target as HTMLSelectElement).value)">
                            <option value="">Semua Cabang Gerai</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.branch_name }}</option>
                        </select>
                        <select :value="filters.status ?? ''" :class="selectCls" @change="filter('status', ($event.target as HTMLSelectElement).value)">
                            <option value="">Semua Status</option>
                            <option value="pending">Menunggu</option>
                            <option value="approved">Disetujui</option>
                            <option value="rejected">Ditolak</option>
                        </select>
                        <div class="flex items-center gap-2">
                            <select :value="filters.type ?? ''" :class="selectCls" @change="filter('type', ($event.target as HTMLSelectElement).value)">
                                <option value="">Semua Jenis</option>
                                <option v-for="(label, k) in typeLabels" :key="k" :value="k">{{ label }}</option>
                            </select>
                            <Button variant="outline" size="icon" title="Reset Filter" class="size-10 shrink-0 rounded-xl text-gray-500" @click="resetFilters">
                                <RefreshCw class="size-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="overflow-hidden border-gray-200 shadow-md dark:border-zinc-800">
                <CardHeader class="flex flex-row items-center justify-between border-b border-gray-100 p-4 dark:border-zinc-800">
                    <div>
                        <CardTitle class="text-base font-bold">Daftar Pengajuan Karyawan</CardTitle>
                        <CardDescription class="text-xs">Total {{ adminLeaves.total }} pengajuan</CardDescription>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-gray-200 bg-gray-50 text-gray-500 dark:border-zinc-800 dark:bg-zinc-900/60">
                                <tr>
                                    <th class="px-4 py-3 font-bold">Karyawan</th>
                                    <th class="px-4 py-3 font-bold">Cabang</th>
                                    <th class="px-4 py-3 font-bold">Jenis</th>
                                    <th class="px-4 py-3 font-bold">Periode</th>
                                    <th class="px-4 py-3 font-bold">Alasan</th>
                                    <th class="px-4 py-3 font-bold">Status</th>
                                    <th class="px-4 py-3 text-center font-bold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="l in adminLeaves.data" :key="l.id" class="transition-colors hover:bg-gray-50/50 dark:hover:bg-zinc-800/30">
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-rose-500 to-amber-400 text-xs font-bold text-white shadow-sm">
                                                {{ initials(l.employee?.full_name) }}
                                            </div>
                                            <div>
                                                <span class="block text-sm font-bold text-gray-900 dark:text-white">{{ l.employee?.full_name }}</span>
                                                <span class="font-mono text-[11px] font-semibold text-rose-600 dark:text-rose-400">{{ l.employee?.nik }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 font-medium text-gray-800 dark:text-gray-200">{{ l.branch?.branch_name ?? '-' }}</td>
                                    <td class="px-4 py-3.5">
                                        <Badge variant="outline" class="border px-2 py-0.5 text-[10px] font-bold">{{ typeLabels[l.type] }}</Badge>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">{{ range(l) }}</div>
                                        <div class="text-[11px] text-gray-400">{{ l.total_days }} hari</div>
                                    </td>
                                    <td class="max-w-64 px-4 py-3.5 text-gray-600 dark:text-gray-400">
                                        <div class="line-clamp-2">{{ l.reason }}</div>
                                        <div v-if="l.approval_notes" class="mt-0.5 text-[11px] text-gray-400">Catatan: {{ l.approval_notes }}</div>
                                        <a v-if="l.attachment_full_url" :href="l.attachment_full_url" target="_blank" class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold text-rose-600">
                                            <Paperclip class="size-3" /> Lampiran
                                        </a>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold" :class="statusMap[l.status].cls">{{ statusMap[l.status].label }}</span>
                                        <div v-if="l.approver" class="mt-0.5 text-[10px] text-gray-400">oleh {{ l.approver.name }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <div v-if="l.status === 'pending'" class="inline-flex items-center gap-1">
                                            <Button size="sm" class="h-8 gap-1 rounded-xl bg-emerald-600 text-xs font-semibold hover:bg-emerald-700" @click="openDecision(l, 'approve')">
                                                <Check class="size-3.5" /> Setujui
                                            </Button>
                                            <Button size="sm" variant="outline" class="h-8 gap-1 rounded-xl text-xs font-semibold text-red-600" @click="openDecision(l, 'reject')">
                                                <X class="size-3.5" /> Tolak
                                            </Button>
                                        </div>
                                        <span v-else class="text-gray-300">-</span>
                                    </td>
                                </tr>
                                <tr v-if="!adminLeaves.data.length">
                                    <td colspan="7" class="py-12 text-center text-gray-400">
                                        <CalendarCheck class="mx-auto mb-2 size-10 text-gray-300" />
                                        <p class="text-sm font-bold">Tidak ada pengajuan</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="adminLeaves.last_page > 1" class="flex items-center justify-between border-t border-gray-100 p-4 text-xs dark:border-zinc-800">
                        <span class="text-gray-500">
                            Menampilkan halaman <strong>{{ adminLeaves.current_page }}</strong> dari <strong>{{ adminLeaves.last_page }}</strong>
                        </span>
                        <div class="flex items-center gap-1">
                            <template v-for="(link, i) in adminLeaves.links" :key="i">
                                <Button
                                    v-if="link.url"
                                    variant="outline"
                                    size="sm"
                                    :class="['h-8 rounded-lg px-3 text-xs', link.active ? 'border-rose-600 bg-rose-600 font-bold text-white' : '']"
                                    @click="router.get(link.url, {}, { preserveScroll: true, preserveState: true })"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- Dialog keputusan -->
        <Dialog :open="!!deciding" @update:open="(v: boolean) => !v && (deciding = null)">
            <DialogContent class="max-w-md rounded-3xl p-6">
                <DialogHeader>
                    <DialogTitle class="text-lg font-black">
                        {{ deciding?.mode === 'approve' ? 'Setujui' : 'Tolak' }} Pengajuan
                    </DialogTitle>
                    <DialogDescription class="text-xs text-gray-500">
                        {{ deciding?.leave.employee?.full_name }} · {{ deciding && typeLabels[deciding.leave.type] }} ·
                        {{ deciding && range(deciding.leave) }} ({{ deciding?.leave.total_days }} hari)
                        <template v-if="deciding?.mode === 'approve'"><br />Jadwal shift/libur karyawan pada tanggal tersebut akan dihapus.</template>
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold">
                        Catatan <span v-if="deciding?.mode === 'reject'" class="text-rose-500">*</span>
                        <span v-else class="font-normal text-gray-400">(opsional)</span>
                    </Label>
                    <textarea v-model="notes" rows="3" class="w-full rounded-xl border border-gray-300 p-3 text-xs dark:border-zinc-800 dark:bg-zinc-950" />
                    <InputError :message="($page.props.errors as Record<string, string>)?.approval_notes" />
                </div>
                <DialogFooter>
                    <Button variant="outline" class="rounded-xl" @click="deciding = null">Batal</Button>
                    <Button
                        class="rounded-xl"
                        :class="deciding?.mode === 'approve' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'"
                        :disabled="deciding?.mode === 'reject' && !notes.trim()"
                        @click="decide"
                    >
                        {{ deciding?.mode === 'approve' ? 'Setujui' : 'Tolak' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
