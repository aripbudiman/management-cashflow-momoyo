<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, Copy, Lock, Save } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

interface Grid {
    week_start: string;
    week_end: string;
    days: Array<{ date: string; day: number; weekday: string; is_sunday: boolean; locked: boolean; is_today: boolean }>;
    employees: Array<{ id: number; full_name: string; nik: string; editable: boolean }>;
    shifts: Array<{ id: number; shift_name: string; start_time: string; end_time: string }>;
    schedules: Array<{ employee_id: number; date: string; is_off: boolean; shift_id: number | null }>;
    leaves: Record<string, string>;
}

const props = defineProps<{
    branches: Array<{ id: number; branch_name: string }>;
    branchId: number;
    canEdit: boolean;
    weekStart: string;
    weekLabel: string;
    prevWeek: string;
    nextWeek: string;
    currentWeek: string;
    grid: Grid | null;
}>();

// Nilai sel: '' (kosong) | 'off' | 'shift:<id>'
const cells = reactive<Record<string, string>>({});
const original = ref<Record<string, string>>({});
const saving = ref(false);
const key = (empId: number, date: string) => `${empId}|${date}`;

const load = () => {
    const map: Record<string, string> = {};
    props.grid?.schedules.forEach((s) => {
        map[key(s.employee_id, s.date)] = s.is_off ? 'off' : `shift:${s.shift_id ?? ''}`;
    });
    Object.keys(cells).forEach((k) => delete cells[k]);
    Object.assign(cells, map);
    original.value = { ...map };
};
watch(() => props.grid, load, { immediate: true });

const dirtyEntries = computed(() => {
    const out: Array<{ employee_id: number; date: string; type: string | null; shift_id: number | null }> = [];
    if (!props.canEdit) return out;
    props.grid?.employees.forEach((e) => {
        if (!e.editable) return;
        props.grid!.days.forEach((d) => {
            if (d.locked || props.grid!.leaves[key(e.id, d.date)]) return;
            const k = key(e.id, d.date);
            const now = cells[k] ?? '';
            if (now === (original.value[k] ?? '')) return;
            out.push({
                employee_id: e.id,
                date: d.date,
                type: now === '' ? null : now === 'off' ? 'off' : 'shift',
                shift_id: now.startsWith('shift:') ? Number(now.slice(6)) : null,
            });
        });
    });
    return out;
});

const go = (params: Record<string, string | number>) => {
    if (dirtyEntries.value.length && !confirm('Perubahan belum disimpan. Lanjut dan buang perubahan?')) return;
    router.get('/schedules', { branch_id: props.branchId, week: props.weekStart, ...params }, { preserveState: true, preserveScroll: true });
};

const fillRow = (empId: number, value: string) => {
    if (!value) return;
    props.grid!.days.forEach((d) => {
        if (d.locked || props.grid!.leaves[key(empId, d.date)]) return;
        cells[key(empId, d.date)] = value === '__clear' ? '' : value;
    });
};
const fillDay = (date: string, value: string) => {
    if (!value) return;
    props.grid!.employees
        .filter((e) => e.editable && !props.grid!.leaves[key(e.id, date)])
        .forEach((e) => (cells[key(e.id, date)] = value === '__clear' ? '' : value));
};

const save = () => {
    saving.value = true;
    router.post(
        '/schedules',
        { branch_id: props.branchId, week: props.weekStart, entries: dirtyEntries.value },
        { preserveScroll: true, onFinish: () => (saving.value = false) },
    );
};

const copyPrev = () => {
    if (dirtyEntries.value.length && !confirm('Ada perubahan belum disimpan yang akan hilang. Lanjut menyalin?')) return;
    if (!confirm('Salin jadwal dari minggu sebelumnya? Hanya sel kosong yang diisi.')) return;
    router.post('/schedules/copy-week', { branch_id: props.branchId, week: props.weekStart }, { preserveScroll: true });
};

const cellClass = (v: string, sunday: boolean) =>
    v === 'off'
        ? 'bg-red-100 text-red-700 font-bold dark:bg-red-950 dark:text-red-300'
        : v.startsWith('shift:')
          ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
          : sunday
            ? 'bg-red-50/50 dark:bg-red-950/20'
            : '';

const shortName = (name: string) => name.replace(/\(.*\)/, '').replace(/^Shift\s+/i, '').trim().slice(0, 4);
const isCurrentWeek = computed(() => props.weekStart === props.currentWeek);
const selectCls = 'h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';
</script>

<template>
    <Head title="Jadwal Mingguan - Momoyo Ops" />

    <div class="flex-1 space-y-5 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-black tracking-tight text-gray-900 dark:text-white">
                    <CalendarDays class="size-6 text-rose-500" /> Jadwal Shift Mingguan
                </h1>
                <p class="text-sm text-gray-500">
                    {{ canEdit ? 'Susun shift & hari libur tim Anda per minggu (Senin–Minggu). Tanggal yang sudah lewat terkunci.' : 'Mode pantau: jadwal disusun Kepala Toko di tiap cabang.' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select v-if="branches.length > 1" :value="branchId" :class="selectCls" @change="go({ branch_id: ($event.target as HTMLSelectElement).value })">
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.branch_name }}</option>
                </select>
                <div class="flex items-center overflow-hidden rounded-md border border-gray-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <button type="button" class="px-2 py-2 hover:bg-gray-50 dark:hover:bg-zinc-800" aria-label="Minggu sebelumnya" @click="go({ week: prevWeek })"><ChevronLeft class="size-4" /></button>
                    <span class="px-3 text-sm font-bold whitespace-nowrap">{{ weekLabel }}</span>
                    <button type="button" class="px-2 py-2 hover:bg-gray-50 dark:hover:bg-zinc-800" aria-label="Minggu berikutnya" @click="go({ week: nextWeek })"><ChevronRight class="size-4" /></button>
                </div>
                <Button v-if="!isCurrentWeek" variant="outline" size="sm" @click="go({ week: currentWeek })">Minggu ini</Button>
                <template v-if="canEdit">
                    <Button variant="outline" class="gap-1.5" @click="copyPrev"><Copy class="size-4" /> Salin minggu lalu</Button>
                    <Button class="gap-1.5" :disabled="!dirtyEntries.length || saving" @click="save">
                        <Save class="size-4" /> Simpan
                        <span v-if="dirtyEntries.length">({{ dirtyEntries.length }})</span>
                    </Button>
                </template>
            </div>
        </div>

        <div v-if="grid && grid.shifts.length" class="flex flex-wrap gap-2 text-xs text-gray-500">
            <span v-for="s in grid.shifts" :key="s.id" class="rounded-full bg-gray-100 px-2.5 py-1 dark:bg-zinc-800">
                <b>{{ shortName(s.shift_name) }}</b> = {{ s.shift_name }} ({{ s.start_time.slice(0, 5) }}–{{ s.end_time.slice(0, 5) }})
            </span>
            <span class="rounded-full bg-red-100 px-2.5 py-1 text-red-700"><b>Libur</b></span>
            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-blue-700"><b>Izin/Sakit/Cuti</b> (otomatis dari pengajuan)</span>
        </div>
        <div v-else-if="grid && canEdit" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
            Cabang ini belum punya shift. Tambahkan dulu di menu Master Shift.
        </div>

        <Card v-if="grid">
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full border-collapse text-xs">
                    <thead>
                        <tr class="border-b bg-gray-50 dark:bg-zinc-900">
                            <th class="sticky left-0 z-10 min-w-40 bg-gray-50 p-2 text-left dark:bg-zinc-900">Karyawan</th>
                            <th v-if="canEdit" class="min-w-20 p-1">Isi baris</th>
                            <th
                                v-for="d in grid.days"
                                :key="d.date"
                                class="min-w-24 p-1 text-center"
                                :class="[d.is_sunday ? 'text-red-600' : '', d.is_today ? 'bg-rose-50 dark:bg-rose-950/30' : '']"
                            >
                                <div>{{ d.weekday }}</div>
                                <div class="flex items-center justify-center gap-0.5 text-sm font-black">
                                    {{ d.day }} <Lock v-if="d.locked" class="size-3 text-gray-400" />
                                </div>
                                <select
                                    v-if="canEdit && !d.locked"
                                    class="mt-0.5 w-full rounded border border-gray-200 bg-transparent text-[10px]"
                                    @change="fillDay(d.date, ($event.target as HTMLSelectElement).value); ($event.target as HTMLSelectElement).value = ''"
                                >
                                    <option value="">⋯</option>
                                    <option v-for="s in grid.shifts" :key="s.id" :value="`shift:${s.id}`">{{ shortName(s.shift_name) }}</option>
                                    <option value="off">Libur</option>
                                    <option value="__clear">Kosong</option>
                                </select>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in grid.employees" :key="e.id" class="border-b">
                            <td class="sticky left-0 z-10 bg-white p-2 dark:bg-zinc-950">
                                <div class="font-bold">{{ e.full_name }}</div>
                                <div class="text-[10px] text-gray-400">{{ e.nik }}<span v-if="!e.editable"> · bukan karyawan aktif cabang ini</span></div>
                            </td>
                            <td v-if="canEdit" class="p-1">
                                <select
                                    v-if="e.editable"
                                    class="w-full rounded border border-gray-200 bg-transparent text-[10px]"
                                    @change="fillRow(e.id, ($event.target as HTMLSelectElement).value); ($event.target as HTMLSelectElement).value = ''"
                                >
                                    <option value="">⋯</option>
                                    <option v-for="s in grid.shifts" :key="s.id" :value="`shift:${s.id}`">{{ shortName(s.shift_name) }}</option>
                                    <option value="off">Libur</option>
                                    <option value="__clear">Kosong</option>
                                </select>
                            </td>
                            <td v-for="d in grid.days" :key="d.date" class="p-0.5" :class="d.is_today ? 'bg-rose-50/60 dark:bg-rose-950/20' : ''">
                                <div v-if="grid.leaves[key(e.id, d.date)]" class="rounded bg-blue-100 px-1 py-1.5 text-center text-[11px] font-bold text-blue-700">
                                    {{ grid.leaves[key(e.id, d.date)] }}
                                </div>
                                <select
                                    v-else
                                    v-model="cells[key(e.id, d.date)]"
                                    :disabled="!canEdit || !e.editable || d.locked"
                                    class="w-full rounded border border-transparent px-0.5 py-1 text-center text-[11px] disabled:opacity-70"
                                    :class="cellClass(cells[key(e.id, d.date)] ?? '', d.is_sunday)"
                                >
                                    <option value="">–</option>
                                    <option v-for="s in grid.shifts" :key="s.id" :value="`shift:${s.id}`">{{ shortName(s.shift_name) }}</option>
                                    <option value="off">Libur</option>
                                </select>
                            </td>
                        </tr>
                        <tr v-if="!grid.employees.length">
                            <td :colspan="grid.days.length + 2" class="p-8 text-center text-gray-400">Belum ada karyawan aktif di cabang ini.</td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
