<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import RupiahInput from '@/components/RupiahInput.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Project } from '@/lib/project';

const props = defineProps<{ project: Project; pics: { id: number; full_name: string }[] }>();
const open = defineModel<boolean>('open', { default: false });

const selectCls = 'h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';

const form = useForm({
    kode: '',
    nama: '',
    client_name: '',
    branch_id: 0,
    pic_id: null as number | null,
    alamat: '',
    luas_m2: '' as number | '',
    tgl_mulai_rencana: '',
    tgl_selesai_rencana: '',
    nilai_kontrak: 0 as number | '',
    progress_mode: 'weighted',
    catatan: '',
});

watch(open, (isOpen) => {
    if (!isOpen) return;
    const p = props.project;
    form.clearErrors();
    form.defaults({
        kode: p.kode,
        nama: p.nama,
        client_name: p.client_name ?? '',
        branch_id: p.branch_id,
        pic_id: p.pic_id,
        alamat: p.alamat ?? '',
        luas_m2: p.luas_m2 ?? '',
        tgl_mulai_rencana: p.tgl_mulai_rencana?.slice(0, 10) ?? '',
        tgl_selesai_rencana: p.tgl_selesai_rencana?.slice(0, 10) ?? '',
        nilai_kontrak: p.nilai_kontrak,
        progress_mode: p.progress_mode,
        catatan: p.catatan ?? '',
    }).reset();
});

const submit = () => {
    form.put(`/projects/${props.project.id}`, { preserveScroll: true, onSuccess: () => (open.value = false) });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader><DialogTitle>Edit Proyek</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label>Kode Proyek</Label>
                        <Input v-model="form.kode" />
                        <InputError :message="form.errors.kode" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Klien / Brand</Label>
                        <Input v-model="form.client_name" />
                        <InputError :message="form.errors.client_name" />
                    </div>
                </div>
                <div class="space-y-1.5">
                    <Label>Nama Proyek</Label>
                    <Input v-model="form.nama" />
                    <InputError :message="form.errors.nama" />
                </div>
                <div class="space-y-1.5">
                    <Label>Alamat</Label>
                    <Input v-model="form.alamat" />
                    <InputError :message="form.errors.alamat" />
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label>Luas (m²)</Label>
                        <Input v-model="form.luas_m2" type="number" step="0.01" min="0" />
                        <InputError :message="form.errors.luas_m2" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Mulai (rencana)</Label>
                        <Input v-model="form.tgl_mulai_rencana" type="date" />
                        <InputError :message="form.errors.tgl_mulai_rencana" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Selesai (rencana)</Label>
                        <Input v-model="form.tgl_selesai_rencana" type="date" />
                        <InputError :message="form.errors.tgl_selesai_rencana" />
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label>Nilai Kontrak</Label>
                        <RupiahInput v-model="form.nilai_kontrak" />
                        <InputError :message="form.errors.nilai_kontrak" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Hitung Progress</Label>
                        <select v-model="form.progress_mode" :class="selectCls">
                            <option value="weighted">Berbobot nilai RAB</option>
                            <option value="simple">Rata-rata sederhana</option>
                        </select>
                        <InputError :message="form.errors.progress_mode" />
                    </div>
                </div>
                <div class="space-y-1.5">
                    <Label>PIC Proyek</Label>
                    <select v-model="form.pic_id" :class="selectCls">
                        <option :value="null">Tanpa PIC</option>
                        <option v-for="e in pics" :key="e.id" :value="e.id">{{ e.full_name }}</option>
                    </select>
                    <InputError :message="form.errors.pic_id" />
                </div>
                <div class="space-y-1.5">
                    <Label>Catatan</Label>
                    <Input v-model="form.catatan" />
                    <InputError :message="form.errors.catatan" />
                </div>
                <DialogFooter><Button type="submit" :disabled="form.processing">Simpan</Button></DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
