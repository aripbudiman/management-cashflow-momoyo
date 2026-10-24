<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { BadgeCheck, Ban, CheckCheck, Edit3, FileDown, FilePlus2, FileText, Plus, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fmtDate } from '@/lib/format';
import type { EmployeeOption, Placement, Project } from '@/lib/project';
import { placementRoles, placementStatusClass, placementStatusLabels } from '@/lib/project';

const props = defineProps<{
    project: Project;
    placements: Placement[];
    recaps: Record<string, Record<string, { hadir: number; terlambat: number }>>;
    employees: EmployeeOption[];
    signerDefaults: { nama: string | null; jabatan: string | null } | null;
    canManage: boolean;
}>();

const selectCls = 'h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';

const page = usePage();
const placementError = computed(() => (page.props.errors as Record<string, string>).placement);

// --- Form draft / revisi ---
interface Row {
    employee_id: number | '';
    peran: string;
}
const formOpen = ref(false);
const editing = ref<Placement | null>(null);
const form = useForm<{
    tgl_mulai: string;
    tgl_selesai: string;
    lokasi: string;
    catatan: string;
    override_reason: string;
    revision_of: number | null;
    employees: Row[];
}>({ tgl_mulai: '', tgl_selesai: '', lokasi: '', catatan: '', override_reason: '', revision_of: null, employees: [] });

const fill = (p: Placement | null, revision: boolean) => {
    editing.value = revision ? null : p;
    form.clearErrors();
    form.tgl_mulai = p?.tgl_mulai.slice(0, 10) ?? props.project.tgl_mulai_rencana?.slice(0, 10) ?? '';
    form.tgl_selesai = p?.tgl_selesai.slice(0, 10) ?? props.project.tgl_selesai_rencana?.slice(0, 10) ?? '';
    form.lokasi = p?.lokasi ?? props.project.alamat ?? '';
    form.catatan = p?.catatan ?? '';
    form.override_reason = p?.override_reason ?? '';
    form.revision_of = revision && p ? p.id : null;
    form.employees = p ? p.employees.map((e) => ({ employee_id: e.employee_id, peran: e.peran })) : [{ employee_id: '', peran: 'Tukang' }];
    formOpen.value = true;
};
const err = (key: string): string | undefined => (form.errors as Record<string, string>)[key];
const addRow = () => form.employees.push({ employee_id: '', peran: 'Tukang' });
const removeRow = (i: number) => form.employees.splice(i, 1);

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => (formOpen.value = false) };
    if (editing.value) form.put(`/projects/${props.project.id}/placements/${editing.value.id}`, opts);
    else form.post(`/projects/${props.project.id}/placements`, opts);
};

// --- Terbitkan ---
const issueOpen = ref(false);
const issuing = ref<Placement | null>(null);
const issueForm = useForm({ penandatangan_nama: '', penandatangan_jabatan: '' });
const openIssue = (p: Placement) => {
    issuing.value = p;
    issueForm.clearErrors();
    issueForm.penandatangan_nama = props.signerDefaults?.nama ?? '';
    issueForm.penandatangan_jabatan = props.signerDefaults?.jabatan ?? '';
    issueOpen.value = true;
};
const submitIssue = () => {
    if (!issuing.value) return;
    issueForm.post(`/placements/${issuing.value.id}/issue`, { preserveScroll: true, onSuccess: () => (issueOpen.value = false) });
};

const act = (url: string, message: string, method: 'post' | 'delete' = 'post') => {
    if (confirm(message)) router[method](url, {}, { preserveScroll: true });
};
const isSuperseded = (p: Placement) => p.status === 'dibatalkan' && p.revisions.length > 0;
</script>

<template>
    <div class="space-y-4">
        <div v-if="canManage" class="flex flex-wrap items-center justify-between gap-2">
            <Link href="/settings/placement-template" class="inline-flex items-center gap-1.5 text-sm text-rose-700 hover:underline"><FileText class="size-4" /> Atur template surat</Link>
            <Button class="gap-1.5" @click="fill(null, false)"><Plus class="size-4" /> Penempatan Baru</Button>
        </div>

        <p v-if="placementError" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ placementError }}</p>

        <Card v-if="placements.length === 0">
            <CardContent class="p-8 text-center text-sm text-gray-400">
                Belum ada surat penempatan.<template v-if="canManage"> Progress kerja baru bisa diisi setelah surat penempatan diterbitkan.</template>
            </CardContent>
        </Card>

        <Card v-for="p in placements" :key="p.id">
            <CardContent class="space-y-3 p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold">{{ p.nomor_surat ?? 'Draft (belum bernomor)' }}</span>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold" :class="placementStatusClass[p.status]">{{ placementStatusLabels[p.status] }}</span>
                            <span v-if="isSuperseded(p)" class="text-[11px] text-gray-500">digantikan revisi</span>
                            <span v-if="p.revision_of" class="text-[11px] text-gray-500">revisi</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ fmtDate(p.tgl_mulai) }} s/d {{ fmtDate(p.tgl_selesai) }}<template v-if="p.lokasi"> · {{ p.lokasi }}</template>
                            <template v-if="p.penandatangan_nama"> · ttd {{ p.penandatangan_nama }}</template>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <Button v-if="p.status !== 'draft' || canManage" as="a" :href="`/placements/${p.id}/pdf`" target="_blank" size="sm" variant="outline" class="h-8 gap-1.5"><FileDown class="size-4" /> PDF</Button>
                        <template v-if="canManage">
                            <template v-if="p.status === 'draft'">
                                <Button size="sm" class="h-8 gap-1.5" @click="openIssue(p)"><BadgeCheck class="size-4" /> Terbitkan</Button>
                                <Button size="icon" variant="ghost" class="size-8" @click="fill(p, false)"><Edit3 class="size-4" /></Button>
                                <Button size="icon" variant="ghost" class="size-8 text-rose-500" @click="act(`/placements/${p.id}`, 'Hapus draft penempatan ini?', 'delete')"><Trash2 class="size-4" /></Button>
                            </template>
                            <template v-else-if="p.status === 'diterbitkan'">
                                <Button size="sm" variant="outline" class="h-8 gap-1.5" @click="fill(p, true)"><FilePlus2 class="size-4" /> Revisi</Button>
                                <Button size="sm" variant="outline" class="h-8 gap-1.5" @click="act(`/placements/${p.id}/complete`, 'Tandai penempatan ini selesai?')"><CheckCheck class="size-4" /> Selesai</Button>
                                <Button size="sm" variant="outline" class="h-8 gap-1.5 text-rose-600" @click="act(`/placements/${p.id}/cancel`, 'Batalkan surat penempatan ini?')"><Ban class="size-4" /> Batalkan</Button>
                            </template>
                        </template>
                    </div>
                </div>

                <div class="divide-y rounded-lg border text-sm">
                    <div v-for="e in p.employees" :key="e.id" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                        <div>
                            <span class="font-semibold">{{ e.employee.full_name }}</span>
                            <span class="text-xs text-gray-500"> · {{ e.employee.nik }}</span>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 font-bold text-gray-700 dark:bg-zinc-800 dark:text-gray-300">{{ e.peran }}</span>
                            <span v-if="recaps[p.id]?.[e.employee_id]" class="text-gray-500">
                                Hadir {{ recaps[p.id][e.employee_id].hadir }} hari<template v-if="recaps[p.id][e.employee_id].terlambat"> · terlambat {{ recaps[p.id][e.employee_id].terlambat }}</template>
                            </span>
                        </div>
                    </div>
                </div>
                <p v-if="p.override_reason" class="text-xs text-amber-700">Override konflik: {{ p.override_reason }}</p>
                <p v-if="p.catatan" class="text-xs text-gray-600">{{ p.catatan }}</p>
            </CardContent>
        </Card>

        <Dialog v-model:open="formOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Edit Draft Penempatan' : form.revision_of ? 'Revisi Penempatan' : 'Penempatan Baru' }}</DialogTitle>
                    <DialogDescription>Karyawan yang ditempatkan otomatis bisa mengisi progress proyek ini setelah surat terbit.</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label>Tanggal mulai</Label>
                            <Input v-model="form.tgl_mulai" type="date" />
                            <InputError :message="form.errors.tgl_mulai" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Tanggal selesai</Label>
                            <Input v-model="form.tgl_selesai" type="date" />
                            <InputError :message="form.errors.tgl_selesai" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Lokasi</Label>
                        <Input v-model="form.lokasi" />
                        <InputError :message="form.errors.lokasi" />
                    </div>

                    <div class="space-y-2">
                        <Label>Karyawan & peran di lapangan</Label>
                        <datalist id="peran-list"><option v-for="r in placementRoles" :key="r" :value="r" /></datalist>
                        <div v-for="(row, i) in form.employees" :key="i" class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <select v-model="row.employee_id" :class="selectCls">
                                    <option value="">Pilih karyawan...</option>
                                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}<template v-if="e.current_branch"> ({{ e.current_branch.branch_name }})</template></option>
                                </select>
                                <InputError :message="err(`employees.${i}.employee_id`)" />
                            </div>
                            <div class="w-36 shrink-0">
                                <Input v-model="row.peran" list="peran-list" placeholder="Peran" />
                                <InputError :message="err(`employees.${i}.peran`)" />
                            </div>
                            <Button type="button" size="icon" variant="ghost" class="size-9 shrink-0" :disabled="form.employees.length === 1" @click="removeRow(i)"><X class="size-4" /></Button>
                        </div>
                        <InputError :message="form.errors.employees" />
                        <Button type="button" variant="outline" size="sm" class="gap-1.5" @click="addRow"><Plus class="size-4" /> Tambah karyawan</Button>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Catatan</Label>
                        <Input v-model="form.catatan" />
                        <InputError :message="form.errors.catatan" />
                    </div>
                    <div class="space-y-1.5 rounded-lg border border-dashed border-amber-300 p-3">
                        <Label>Alasan override konflik jadwal <span class="font-normal text-gray-500">(isi hanya jika ada konflik)</span></Label>
                        <Input v-model="form.override_reason" placeholder="Contoh: persetujuan manajemen, proyek mendesak" />
                        <InputError :message="form.errors.override_reason" />
                    </div>
                    <DialogFooter><Button type="submit" :disabled="form.processing">Simpan Draft</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="issueOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Terbitkan Surat</DialogTitle>
                    <DialogDescription>Nomor surat dibuat otomatis. Setelah terbit, isi surat tidak bisa diubah (gunakan revisi).</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitIssue">
                    <div class="space-y-1.5">
                        <Label>Nama penandatangan</Label>
                        <Input v-model="issueForm.penandatangan_nama" />
                        <InputError :message="issueForm.errors.penandatangan_nama" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Jabatan penandatangan</Label>
                        <Input v-model="issueForm.penandatangan_jabatan" />
                        <InputError :message="issueForm.errors.penandatangan_jabatan" />
                    </div>
                    <DialogFooter><Button type="submit" :disabled="issueForm.processing">Terbitkan</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
