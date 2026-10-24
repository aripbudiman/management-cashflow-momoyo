<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Download, Edit3, FileUp, Lock, Plus, Send, Trash2, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import RupiahInput from '@/components/RupiahInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rupiah } from '@/lib/format';
import type { Project, RabVersion, WorkItem, WorkSection } from '@/lib/project';

const props = defineProps<{ project: Project; sections: WorkSection[]; rab: RabVersion | null; canManage: boolean }>();

const page = usePage();
const rabError = computed(() => (page.props.errors as Record<string, string>).rab);
const locked = computed(() => props.rab?.status === 'sent');
const editable = computed(() => props.canManage && !locked.value);
const grandTotal = computed(() => props.sections.reduce((s, sec) => s + sec.items.reduce((t, i) => t + i.total, 0), 0));
const sectionTotal = (sec: WorkSection) => sec.items.reduce((t, i) => t + i.total, 0);
const num = (n: number) => new Intl.NumberFormat('id-ID').format(n);

// --- Bidang ---
const sectionOpen = ref(false);
const editingSection = ref<WorkSection | null>(null);
const sectionForm = useForm({ kode_bidang: '', nama: '' });
const openSection = (sec: WorkSection | null) => {
    editingSection.value = sec;
    sectionForm.clearErrors();
    sectionForm.kode_bidang = sec?.kode_bidang ?? '';
    sectionForm.nama = sec?.nama ?? '';
    sectionOpen.value = true;
};
const submitSection = () => {
    const opts = { preserveScroll: true, onSuccess: () => (sectionOpen.value = false) };
    if (editingSection.value) sectionForm.put(`/sections/${editingSection.value.id}`, opts);
    else sectionForm.post(`/projects/${props.project.id}/sections`, opts);
};
const removeSection = (sec: WorkSection) => {
    if (confirm(`Hapus bidang ${sec.kode_bidang}. ${sec.nama} beserta ${sec.items.length} item di dalamnya?`)) {
        router.delete(`/sections/${sec.id}`, { preserveScroll: true });
    }
};

// --- Item ---
const itemOpen = ref(false);
const editingItem = ref<WorkItem | null>(null);
const itemForm = useForm({
    section_id: 0,
    uraian: '',
    deskripsi: '',
    qty: 1 as number | '',
    satuan: 'unit',
    harga_satuan: 0 as number | '',
    durasi_hari: '' as number | '',
});
const openItem = (sectionId: number, item: WorkItem | null) => {
    editingItem.value = item;
    itemForm.clearErrors();
    itemForm.section_id = sectionId;
    itemForm.uraian = item?.uraian ?? '';
    itemForm.deskripsi = item?.deskripsi ?? '';
    itemForm.qty = item?.qty ?? 1;
    itemForm.satuan = item?.satuan ?? 'unit';
    itemForm.harga_satuan = item?.harga_satuan ?? 0;
    itemForm.durasi_hari = item?.durasi_hari ?? '';
    itemOpen.value = true;
};
const submitItem = () => {
    const opts = { preserveScroll: true, onSuccess: () => (itemOpen.value = false) };
    if (editingItem.value) itemForm.put(`/work-items/${editingItem.value.id}`, opts);
    else itemForm.post(`/projects/${props.project.id}/items`, opts);
};
const removeItem = (item: WorkItem) => {
    if (confirm(`Hapus item "${item.uraian}"?`)) router.delete(`/work-items/${item.id}`, { preserveScroll: true });
};

// --- Impor Excel ---
const importOpen = ref(false);
const importForm = useForm<{ file: File | null }>({ file: null });
const submitImport = () => {
    importForm.post(`/projects/${props.project.id}/rab/import`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importOpen.value = false;
            importForm.reset();
        },
    });
};

// --- Kirim & revisi ---
const sendRab = () => {
    if (confirm('Tandai RAB ini sudah dikirim ke klien? RAB akan terkunci, perubahan berikutnya lewat revisi.')) {
        router.post(`/projects/${props.project.id}/rab/send`, {}, { preserveScroll: true });
    }
};
const reviseRab = () => {
    if (confirm('Buat revisi RAB baru (draft) dari RAB yang terkirim?')) {
        router.post(`/projects/${props.project.id}/rab/revise`, {}, { preserveScroll: true });
    }
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2 text-sm">
                <span class="font-bold">RAB v{{ rab?.versi ?? 1 }}</span>
                <Badge :variant="locked ? 'secondary' : 'outline'" class="gap-1"><Lock v-if="locked" class="size-3" /> {{ locked ? 'Terkirim' : 'Draft' }}</Badge>
                <span class="text-gray-500">Total {{ rupiah(grandTotal) }}</span>
            </div>
            <div v-if="canManage" class="flex flex-wrap gap-2">
                <template v-if="editable">
                    <Button variant="outline" class="gap-1.5" @click="openSection(null)"><Plus class="size-4" /> Bidang</Button>
                    <Button variant="outline" class="gap-1.5" @click="importOpen = true"><Upload class="size-4" /> Impor Excel</Button>
                    <Button class="gap-1.5" @click="sendRab"><Send class="size-4" /> Tandai Terkirim</Button>
                </template>
                <Button v-else variant="outline" class="gap-1.5" @click="reviseRab"><FileUp class="size-4" /> Buat Revisi</Button>
            </div>
        </div>

        <p v-if="rabError" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ rabError }}</p>

        <Card v-if="sections.length === 0">
            <CardContent class="p-8 text-center text-sm text-gray-400">
                RAB masih kosong.<template v-if="editable"> Tambah bidang lalu item pekerjaan, atau impor dari Excel.</template>
            </CardContent>
        </Card>

        <Card v-for="sec in sections" :key="sec.id">
            <CardHeader class="border-b pb-3">
                <div class="flex items-center justify-between gap-3">
                    <CardTitle class="text-sm font-bold">{{ sec.kode_bidang }}. {{ sec.nama }}</CardTitle>
                    <div class="flex items-center gap-1">
                        <span class="mr-2 text-xs font-bold text-gray-600">{{ rupiah(sectionTotal(sec)) }}</span>
                        <template v-if="editable">
                            <Button size="icon" variant="ghost" class="size-8" @click="openItem(sec.id, null)"><Plus class="size-4" /></Button>
                            <Button size="icon" variant="ghost" class="size-8" @click="openSection(sec)"><Edit3 class="size-4" /></Button>
                            <Button size="icon" variant="ghost" class="size-8 text-rose-500" @click="removeSection(sec)"><Trash2 class="size-4" /></Button>
                        </template>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="sec.items.length === 0" class="p-4 text-center text-xs text-gray-400">Belum ada item.</div>
                <div v-else class="divide-y">
                    <div v-for="(item, idx) in sec.items" :key="item.id" class="flex items-start justify-between gap-3 p-3 text-sm">
                        <div class="min-w-0">
                            <div class="font-semibold">{{ idx + 1 }}. {{ item.uraian }}</div>
                            <div v-if="item.deskripsi" class="text-xs text-gray-500">{{ item.deskripsi }}</div>
                            <div class="text-xs text-gray-500">
                                {{ num(item.qty) }} {{ item.satuan }} × {{ rupiah(item.harga_satuan) }}<template v-if="item.durasi_hari"> · {{ item.durasi_hari }} hari</template>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="font-bold">{{ rupiah(item.total) }}</span>
                            <template v-if="editable">
                                <Button size="icon" variant="ghost" class="size-8" @click="openItem(sec.id, item)"><Edit3 class="size-4" /></Button>
                                <Button size="icon" variant="ghost" class="size-8 text-rose-500" @click="removeItem(item)"><Trash2 class="size-4" /></Button>
                            </template>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="sectionOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader><DialogTitle>{{ editingSection ? 'Edit Bidang' : 'Tambah Bidang' }}</DialogTitle></DialogHeader>
                <form class="space-y-4" @submit.prevent="submitSection">
                    <div class="space-y-1.5">
                        <Label>Kode Bidang</Label>
                        <Input v-model="sectionForm.kode_bidang" placeholder="II" />
                        <InputError :message="sectionForm.errors.kode_bidang" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Nama Bidang</Label>
                        <Input v-model="sectionForm.nama" placeholder="Plumbing" />
                        <InputError :message="sectionForm.errors.nama" />
                    </div>
                    <InputError :message="rabError" />
                    <DialogFooter><Button type="submit" :disabled="sectionForm.processing">Simpan</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="itemOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader><DialogTitle>{{ editingItem ? 'Edit Item Pekerjaan' : 'Tambah Item Pekerjaan' }}</DialogTitle></DialogHeader>
                <form class="space-y-4" @submit.prevent="submitItem">
                    <div class="space-y-1.5">
                        <Label>Uraian</Label>
                        <Input v-model="itemForm.uraian" />
                        <InputError :message="itemForm.errors.uraian" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Deskripsi</Label>
                        <Input v-model="itemForm.deskripsi" />
                        <InputError :message="itemForm.errors.deskripsi" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Qty</Label>
                            <Input v-model="itemForm.qty" type="number" step="0.01" min="0" />
                            <InputError :message="itemForm.errors.qty" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Satuan</Label>
                            <Input v-model="itemForm.satuan" placeholder="unit, pcs, m2, m1, set" />
                            <InputError :message="itemForm.errors.satuan" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Harga Satuan</Label>
                            <RupiahInput v-model="itemForm.harga_satuan" />
                            <InputError :message="itemForm.errors.harga_satuan" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Durasi (hari)</Label>
                            <Input v-model="itemForm.durasi_hari" type="number" min="0" />
                            <InputError :message="itemForm.errors.durasi_hari" />
                        </div>
                    </div>
                    <InputError :message="itemForm.errors.section_id" />
                    <InputError :message="rabError" />
                    <DialogFooter><Button type="submit" :disabled="itemForm.processing">Simpan</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="importOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Impor RAB dari Excel</DialogTitle>
                    <DialogDescription>Item ditambahkan ke RAB draft. Kalau ada baris yang error, tidak ada data yang disimpan.</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 text-sm">
                    <div class="rounded-xl border border-gray-200 p-3 dark:border-zinc-700">
                        <div class="mb-1 font-bold">Format file (.xlsx, baris 1 = header)</div>
                        <div class="font-mono text-xs break-words text-gray-600">kode_bidang | nama_bidang | uraian | deskripsi | qty | satuan | harga_satuan | hari</div>
                        <ul class="mt-2 list-disc space-y-0.5 pl-5 text-xs text-gray-500">
                            <li>Wajib: kode_bidang, nama_bidang, uraian, qty, satuan, harga_satuan.</li>
                            <li>Baris dengan kode_bidang sama masuk ke bidang yang sama.</li>
                            <li>qty dan harga_satuan berupa angka tanpa "Rp". Total dihitung sistem.</li>
                            <li>Jangan pakai sel gabungan atau baris subtotal.</li>
                        </ul>
                    </div>
                    <Button as="a" href="/rab/template" variant="outline" class="w-full gap-1.5"><Download class="size-4" /> Unduh Template</Button>
                    <div class="space-y-1.5">
                        <Label for="rab_file">File Excel</Label>
                        <Input id="rab_file" type="file" accept=".xlsx" @change="(e: Event) => (importForm.file = (e.target as HTMLInputElement).files?.[0] ?? null)" />
                        <InputError :message="importForm.errors.file" />
                    </div>
                </div>
                <DialogFooter>
                    <Button :disabled="importForm.processing || !importForm.file" @click="submitImport">Impor</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
