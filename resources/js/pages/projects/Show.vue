<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Ban, Check, Download, Lock, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProjectDocumentsTab from '@/components/projects/ProjectDocumentsTab.vue';
import ProjectEditDialog from '@/components/projects/ProjectEditDialog.vue';
import ProjectExpensesTab from '@/components/projects/ProjectExpensesTab.vue';
import type { ProfitSummary, ProjectExpense } from '@/components/projects/ProjectExpensesTab.vue';
import ProjectPlacementsTab from '@/components/projects/ProjectPlacementsTab.vue';
import ProjectProgressTab from '@/components/projects/ProjectProgressTab.vue';
import ProjectRabTab from '@/components/projects/ProjectRabTab.vue';
import ProjectTimelineTab from '@/components/projects/ProjectTimelineTab.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { fmtDate, rupiah } from '@/lib/format';
import type { EmployeeOption, Milestone, Placement, Project, ProgressLog } from '@/lib/project';
import { statusClass, statusLabels } from '@/lib/project';

const props = defineProps<{
    project: Project;
    summary: { total: number; selesai: number; belum_selesai: number; avg_progress: number };
    logs: ProgressLog[];
    pics: { id: number; full_name: string }[];
    placements: Placement[];
    recaps: Record<string, Record<string, { hadir: number; terlambat: number }>>;
    hasPlacement: boolean;
    milestones: Milestone[];
    employees: EmployeeOption[];
    signerDefaults: { nama: string | null; jabatan: string | null } | null;
    expenses: ProjectExpense[];
    profit: ProfitSummary | null;
    expenseCategories: string[];
    canManage: boolean;
    canVerify: boolean;
}>();

type Tab = 'ringkasan' | 'rab' | 'penempatan' | 'progress' | 'timeline' | 'pengeluaran' | 'dokumen';
// Progress baru terbuka setelah RAB dikirim dan surat penempatan terbit (proyek yang sudah berjalan tidak terkunci)
const progressLocked = computed(() => props.project.status === 'draft' || (props.project.status === 'rab_dikirim' && !props.hasPlacement));
const lockMessage = computed(() =>
    props.project.status === 'draft'
        ? 'Progress kerja baru bisa diisi setelah RAB dikirim ke klien. Lengkapi RAB di tab RAB, lalu klik "Tandai Terkirim".'
        : 'Progress kerja baru bisa diisi setelah surat penempatan kerja diterbitkan. Buat dan terbitkan surat di tab Penempatan.',
);
const tab = ref<Tab>(props.project.status === 'draft' ? 'rab' : props.project.status === 'rab_dikirim' && !props.hasPlacement ? 'penempatan' : 'progress');
// Tab Pengeluaran (dan keuntungan bersih) hanya untuk Admin Pusat
const tabs = computed<{ key: Tab; label: string }[]>(() => [
    { key: 'ringkasan', label: 'Ringkasan' },
    { key: 'rab', label: 'RAB' },
    { key: 'penempatan', label: 'Penempatan' },
    { key: 'progress', label: 'Progress' },
    { key: 'timeline', label: 'Timeline' },
    ...(props.canManage ? [{ key: 'pengeluaran' as Tab, label: 'Pengeluaran' }] : []),
    { key: 'dokumen', label: 'Dokumen' },
]);

// Alur proyek sesuai flowchart client; langkah bertanda soon belum dibangun.
const steps = computed(() => {
    const s = props.project.status;
    const sent = s !== 'draft';
    const billed = s === 'tagihan_dikirim' || s === 'selesai';
    const rows = [
        { label: 'Pembuatan Proyek', done: true, soon: false },
        { label: 'RAB & Invoice', done: sent, soon: false },
        { label: 'Kirim RAB / Invoice', done: sent, soon: false },
        { label: 'Penempatan Karyawan', done: props.hasPlacement, soon: false },
        { label: 'Progress Kerja', done: s === 'selesai' || billed, soon: false },
        { label: 'Kirim Tagihan Invoice', done: billed, soon: true },
        { label: 'Selesai', done: s === 'selesai', soon: false },
    ];
    const current = rows.findIndex((r) => !r.done && !r.soon);
    return rows.map((r, i) => ({ ...r, current: i === current && s !== 'dibatalkan' }));
});

const editOpen = ref(false);
const rab = computed(() => props.project.current_rab ?? null);
const sections = computed(() => rab.value?.sections ?? []);
const rabTotal = computed(() => sections.value.reduce((s, sec) => s + sec.items.reduce((t, i) => t + i.total, 0), 0));
const isLate = computed(
    () =>
        !!props.project.tgl_selesai_rencana &&
        !['selesai', 'dibatalkan'].includes(props.project.status) &&
        props.project.tgl_selesai_rencana < new Date().toISOString().slice(0, 10),
);

const cancel = () => {
    if (confirm(`Batalkan proyek ${props.project.nama}? Progress tidak bisa diisi lagi.`)) {
        router.post(`/projects/${props.project.id}/cancel`, {}, { preserveScroll: true });
    }
};

const remove = () => {
    if (confirm(`Hapus proyek ${props.project.nama}? Data RAB dan progress ikut tersembunyi.`)) {
        router.delete(`/projects/${props.project.id}`);
    }
};
</script>

<template>
    <Head :title="`${project.nama} - Momoyo Ops`" />

    <div class="flex-1 space-y-5 p-4 sm:p-6 lg:p-8">
        <div class="space-y-3">
            <Link href="/projects" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900"><ArrowLeft class="size-4" /> Semua proyek</Link>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">{{ project.nama }}</h1>
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold" :class="statusClass[project.status]">{{ statusLabels[project.status] }}</span>
                        <span v-if="isLate" class="rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-bold text-red-700">Terlambat</span>
                    </div>
                    <p class="text-sm text-gray-500">{{ project.kode }} · {{ project.branch?.branch_name }}<template v-if="project.client_name"> · {{ project.client_name }}</template></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as="a" :href="`/projects/${project.id}/export`" variant="outline" class="gap-1.5"><Download class="size-4" /> Export Excel</Button>
                    <template v-if="canManage">
                        <Button variant="outline" class="gap-1.5" @click="editOpen = true"><Pencil class="size-4" /> Edit</Button>
                        <Button v-if="!['selesai', 'dibatalkan'].includes(project.status)" variant="outline" class="gap-1.5" @click="cancel"><Ban class="size-4" /> Batalkan</Button>
                        <Button variant="outline" class="gap-1.5 text-rose-600" @click="remove"><Trash2 class="size-4" /> Hapus</Button>
                    </template>
                </div>
            </div>
        </div>

        <div v-if="project.status === 'dibatalkan'" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">Proyek ini dibatalkan.</div>
        <div v-else class="flex gap-1 overflow-x-auto pb-1">
            <div
                v-for="(st, i) in steps"
                :key="st.label"
                class="flex min-w-32 flex-1 shrink-0 flex-col gap-1 rounded-lg border px-3 py-2 text-xs"
                :class="st.current ? 'border-rose-300 bg-rose-50 dark:bg-rose-950/30' : st.done ? 'border-emerald-200 bg-emerald-50 dark:bg-emerald-950/20' : 'border-gray-200 bg-gray-50 dark:border-zinc-800 dark:bg-zinc-900'"
            >
                <span class="flex items-center gap-1 font-bold" :class="st.current ? 'text-rose-700' : st.done ? 'text-emerald-700' : 'text-gray-400'">
                    <Check v-if="st.done" class="size-3.5" />
                    <span v-else>{{ i + 1 }}.</span>
                    {{ st.label }}
                </span>
                <span v-if="st.soon" class="text-[10px] text-gray-400">Segera</span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Card><CardContent class="p-4"><div class="text-xs text-gray-500">Progress Proyek</div><div class="text-2xl font-black text-rose-600">{{ project.progress }}%</div></CardContent></Card>
            <Card><CardContent class="p-4"><div class="text-xs text-gray-500">Total Pekerjaan</div><div class="text-2xl font-black">{{ summary.total }}</div></CardContent></Card>
            <Card><CardContent class="p-4"><div class="text-xs text-gray-500">Selesai</div><div class="text-2xl font-black text-emerald-600">{{ summary.selesai }}</div></CardContent></Card>
            <Card><CardContent class="p-4"><div class="text-xs text-gray-500">Belum Selesai</div><div class="text-2xl font-black text-amber-600">{{ summary.belum_selesai }}</div></CardContent></Card>
        </div>

        <div class="flex gap-1 overflow-x-auto border-b">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                class="shrink-0 border-b-2 px-4 py-2 text-sm font-bold"
                :class="tab === t.key ? 'border-rose-600 text-rose-700' : 'border-transparent text-gray-500'"
                @click="tab = t.key"
            >
                <Lock v-if="t.key === 'progress' && progressLocked" class="mr-1 inline size-3.5" />{{ t.label }}
            </button>
        </div>

        <Card v-if="tab === 'ringkasan'">
            <CardContent class="grid gap-x-8 gap-y-3 p-5 text-sm sm:grid-cols-2">
                <div><div class="text-xs text-gray-500">Alamat</div>{{ project.alamat || '-' }}</div>
                <div><div class="text-xs text-gray-500">PIC Proyek</div>{{ project.pic?.full_name || '-' }}</div>
                <div><div class="text-xs text-gray-500">Luas Area</div>{{ project.luas_m2 ? `${project.luas_m2} m²` : '-' }}</div>
                <div><div class="text-xs text-gray-500">Nilai Kontrak</div>{{ rupiah(project.nilai_kontrak) }}</div>
                <div v-if="profit"><div class="text-xs text-gray-500">Keuntungan Bersih (setelah pengeluaran {{ rupiah(profit.total_expense) }})</div><b :class="profit.net_profit < 0 ? 'text-red-600' : 'text-emerald-600'">{{ rupiah(profit.net_profit) }}</b> <span class="text-xs text-gray-400">· margin {{ profit.margin }}%</span></div>
                <div><div class="text-xs text-gray-500">Jadwal Rencana</div>{{ project.tgl_mulai_rencana ? fmtDate(project.tgl_mulai_rencana) : '-' }} s/d {{ project.tgl_selesai_rencana ? fmtDate(project.tgl_selesai_rencana) : '-' }}</div>
                <div><div class="text-xs text-gray-500">Total RAB (v{{ rab?.versi ?? 1 }})</div>{{ rupiah(rabTotal) }}</div>
                <div><div class="text-xs text-gray-500">Cara Hitung Progress</div>{{ project.progress_mode === 'weighted' ? 'Berbobot nilai RAB' : 'Rata-rata sederhana' }}</div>
                <div><div class="text-xs text-gray-500">Catatan</div>{{ project.catatan || '-' }}</div>
            </CardContent>
        </Card>

        <ProjectRabTab v-else-if="tab === 'rab'" :project="project" :sections="sections" :rab="rab" :can-manage="canManage" />
        <Card v-else-if="tab === 'progress' && progressLocked">
            <CardContent class="p-8 text-center text-sm text-gray-500">
                <Lock class="mx-auto mb-2 size-6 text-gray-300" />
                {{ lockMessage }}
            </CardContent>
        </Card>
        <ProjectPlacementsTab v-else-if="tab === 'penempatan'" :project="project" :placements="placements" :recaps="recaps" :employees="employees" :signer-defaults="signerDefaults" :can-manage="canManage" />
        <ProjectTimelineTab v-else-if="tab === 'timeline'" :project="project" :sections="sections" :milestones="milestones" :can-manage="canManage" />
        <ProjectProgressTab v-else-if="tab === 'progress'" :project="project" :sections="sections" :logs="logs" :can-verify="canVerify" />
        <ProjectExpensesTab v-else-if="tab === 'pengeluaran' && profit" :project="project" :expenses="expenses" :profit="profit" :categories="expenseCategories" />
        <ProjectDocumentsTab v-else :project="project" :can-manage="canManage" />

        <ProjectEditDialog v-if="canManage" v-model:open="editOpen" :project="project" :pics="pics" />
    </div>
</template>
