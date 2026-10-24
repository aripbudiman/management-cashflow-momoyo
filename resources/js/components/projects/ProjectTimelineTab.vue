<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { CalendarClock, Flag, Wand2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fmtDate } from '@/lib/format';
import type { Milestone, Project, WorkItem, WorkSection } from '@/lib/project';
import { itemStatusLabels } from '@/lib/project';

const props = defineProps<{ project: Project; sections: WorkSection[]; milestones: Milestone[]; canManage: boolean }>();

const DAY = 86_400_000;
const toMs = (d: string) => new Date(`${d.slice(0, 10)}T00:00:00`).getTime();
const todayIso = new Date().toISOString().slice(0, 10);

const items = computed(() => props.sections.flatMap((s) => s.items));
const hasDates = computed(() => items.value.some((i) => i.tgl_rencana_mulai || i.tgl_aktual_mulai));

// Rentang sumbu waktu: tanggal terawal sampai terakhir dari rencana, aktual, dan hari ini
const range = computed(() => {
    const dates: number[] = [toMs(todayIso)];
    for (const i of items.value) {
        for (const d of [i.tgl_rencana_mulai, i.tgl_rencana_selesai, i.tgl_aktual_mulai, i.tgl_aktual_selesai]) if (d) dates.push(toMs(d));
    }
    if (props.project.tgl_mulai_rencana) dates.push(toMs(props.project.tgl_mulai_rencana));
    if (props.project.tgl_selesai_rencana) dates.push(toMs(props.project.tgl_selesai_rencana));
    const min = Math.min(...dates);
    const max = Math.max(...dates);
    return { min, days: Math.max(7, Math.round((max - min) / DAY) + 2) };
});

const pos = (d: string) => ((toMs(d) - range.value.min) / DAY / range.value.days) * 100;
const width = (a: string, b: string) => Math.max(((toMs(b) - toMs(a)) / DAY + 1) / range.value.days * 100, 1);

const ticks = computed(() => {
    const out: { left: number; label: string }[] = [];
    const step = range.value.days > 90 ? 14 : 7;
    for (let d = 0; d < range.value.days; d += step) {
        const date = new Date(range.value.min + d * DAY);
        out.push({ left: (d / range.value.days) * 100, label: `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}` });
    }
    return out;
});
const todayLeft = computed(() => pos(todayIso));

// Aktual: mulai saat progress pertama > 0, selesai saat 100% (berjalan = sampai hari ini)
const actual = (i: WorkItem) => (i.tgl_aktual_mulai ? { start: i.tgl_aktual_mulai, end: i.tgl_aktual_selesai ?? todayIso } : null);

// --- Atur jadwal ---
const page = usePage();
const alasanError = computed(() => (page.props.errors as Record<string, string>).alasan);

const genOpen = ref(false);
const genForm = useForm({ alasan: '' });
const submitGenerate = () => genForm.post(`/projects/${props.project.id}/timeline/generate`, { preserveScroll: true, onSuccess: () => (genOpen.value = false) });

const editOpen = ref(false);
const editing = ref<WorkItem | null>(null);
const editForm = useForm({ tgl_rencana_mulai: '', tgl_rencana_selesai: '', alasan: '' });
const openEdit = (i: WorkItem) => {
    if (!props.canManage) return;
    editing.value = i;
    editForm.clearErrors();
    editForm.tgl_rencana_mulai = i.tgl_rencana_mulai?.slice(0, 10) ?? '';
    editForm.tgl_rencana_selesai = i.tgl_rencana_selesai?.slice(0, 10) ?? '';
    editForm.alasan = '';
    editOpen.value = true;
};
const submitEdit = () => {
    if (!editing.value) return;
    editForm.put(`/work-items/${editing.value.id}/schedule`, { preserveScroll: true, onSuccess: () => (editOpen.value = false) });
};
const hadDates = computed(() => !!editing.value?.tgl_rencana_mulai || !!editing.value?.tgl_rencana_selesai);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap gap-2">
                <div v-for="m in milestones" :key="m.label" class="flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs" :class="m.date ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-gray-200 text-gray-400'">
                    <Flag class="size-3.5" /> {{ m.label }}<template v-if="m.date"> · {{ fmtDate(m.date) }}</template>
                </div>
            </div>
            <Button v-if="canManage" variant="outline" class="gap-1.5" @click="genOpen = true"><Wand2 class="size-4" /> Susun dari Durasi</Button>
        </div>

        <Card v-if="!hasDates">
            <CardContent class="p-8 text-center text-sm text-gray-500">
                <CalendarClock class="mx-auto mb-2 size-6 text-gray-300" />
                Jadwal rencana belum diatur.
                <template v-if="canManage">Isi tanggal mulai proyek (Edit), lalu klik "Susun dari Durasi", atau atur tanggal per item.</template>
            </CardContent>
        </Card>

        <template v-else>
            <!-- Gantt (layar lebar) -->
            <Card class="hidden md:block">
                <CardContent class="overflow-x-auto p-0">
                    <div class="min-w-[760px]">
                        <div class="flex border-b text-[10px] text-gray-500">
                            <div class="w-56 shrink-0 px-3 py-1.5 font-bold">Pekerjaan</div>
                            <div class="relative h-7 flex-1">
                                <span v-for="t in ticks" :key="t.left" class="absolute top-1.5 -translate-x-1/2" :style="{ left: `${t.left}%` }">{{ t.label }}</span>
                            </div>
                        </div>
                        <template v-for="sec in sections" :key="sec.id">
                            <div class="border-b bg-gray-50 px-3 py-1.5 text-xs font-bold dark:bg-zinc-900">{{ sec.kode_bidang }}. {{ sec.nama }}</div>
                            <div v-for="i in sec.items" :key="i.id" class="flex items-center border-b text-xs" :class="canManage && 'cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-900'" @click="openEdit(i)">
                                <div class="w-56 shrink-0 truncate px-3 py-2" :class="i.is_terlambat && 'font-bold text-red-600'" :title="i.uraian">{{ i.uraian }}</div>
                                <div class="relative h-9 flex-1">
                                    <div class="absolute inset-y-0 w-px bg-rose-400/70" :style="{ left: `${todayLeft}%` }" />
                                    <div v-if="i.tgl_rencana_mulai && i.tgl_rencana_selesai" class="absolute top-1.5 h-2.5 rounded bg-sky-300" :style="{ left: `${pos(i.tgl_rencana_mulai)}%`, width: `${width(i.tgl_rencana_mulai, i.tgl_rencana_selesai)}%` }" :title="`Rencana ${fmtDate(i.tgl_rencana_mulai)} - ${fmtDate(i.tgl_rencana_selesai)}`" />
                                    <div v-if="actual(i)" class="absolute bottom-1.5 h-2.5 rounded" :class="i.is_terlambat ? 'bg-red-500' : i.status === 'selesai' ? 'bg-emerald-500' : 'bg-amber-500'" :style="{ left: `${pos(actual(i)!.start)}%`, width: `${width(actual(i)!.start, actual(i)!.end)}%` }" :title="`Aktual ${fmtDate(actual(i)!.start)} - ${fmtDate(actual(i)!.end)}`" />
                                </div>
                            </div>
                        </template>
                    </div>
                </CardContent>
            </Card>
            <div class="hidden items-center gap-4 text-[11px] text-gray-500 md:flex">
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-4 rounded bg-sky-300" /> Rencana</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-4 rounded bg-amber-500" /> Aktual berjalan</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-4 rounded bg-emerald-500" /> Aktual selesai</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-4 rounded bg-red-500" /> Terlambat</span>
                <span class="flex items-center gap-1"><span class="inline-block h-3 w-px bg-rose-400" /> Hari ini</span>
            </div>

            <!-- Daftar (layar kecil) -->
            <div class="space-y-3 md:hidden">
                <Card v-for="sec in sections" :key="sec.id">
                    <CardHeader class="border-b pb-2"><CardTitle class="text-sm font-bold">{{ sec.kode_bidang }}. {{ sec.nama }}</CardTitle></CardHeader>
                    <CardContent class="divide-y p-0">
                        <button v-for="i in sec.items" :key="i.id" type="button" class="block w-full space-y-0.5 p-3 text-left text-sm" @click="openEdit(i)">
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-semibold" :class="i.is_terlambat && 'text-red-600'">{{ i.uraian }}</span>
                                <span v-if="i.is_terlambat" class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">Terlambat</span>
                            </div>
                            <div class="text-xs text-gray-500">Rencana: {{ i.tgl_rencana_mulai ? `${fmtDate(i.tgl_rencana_mulai)} - ${fmtDate(i.tgl_rencana_selesai ?? i.tgl_rencana_mulai)}` : '-' }}</div>
                            <div class="text-xs text-gray-500">Aktual: {{ i.tgl_aktual_mulai ? `${fmtDate(i.tgl_aktual_mulai)} - ${i.tgl_aktual_selesai ? fmtDate(i.tgl_aktual_selesai) : 'berjalan'}` : '-' }} · {{ itemStatusLabels[i.status] }} {{ i.progress }}%</div>
                        </button>
                    </CardContent>
                </Card>
            </div>
        </template>

        <Dialog v-model:open="genOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Susun Jadwal dari Durasi</DialogTitle>
                    <DialogDescription>Item dalam satu bidang berurutan mulai dari tanggal mulai proyek, antar bidang berjalan paralel. Item tanpa durasi dihitung 1 hari.</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitGenerate">
                    <div class="space-y-1.5">
                        <Label>Alasan <span class="font-normal text-gray-500">(wajib jika jadwal sudah pernah diatur)</span></Label>
                        <Input v-model="genForm.alasan" />
                        <InputError :message="genForm.errors.alasan ?? alasanError" />
                    </div>
                    <DialogFooter><Button type="submit" :disabled="genForm.processing">Susun Jadwal</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="editOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Jadwal Rencana</DialogTitle>
                    <DialogDescription>{{ editing?.uraian }}</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label>Mulai</Label>
                            <Input v-model="editForm.tgl_rencana_mulai" type="date" />
                            <InputError :message="editForm.errors.tgl_rencana_mulai" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Selesai</Label>
                            <Input v-model="editForm.tgl_rencana_selesai" type="date" />
                            <InputError :message="editForm.errors.tgl_rencana_selesai" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Alasan perubahan <span v-if="hadDates" class="text-red-500">(wajib)</span></Label>
                        <Input v-model="editForm.alasan" />
                        <InputError :message="editForm.errors.alasan" />
                    </div>
                    <DialogFooter><Button type="submit" :disabled="editForm.processing">Simpan</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
