<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Clock, Edit3, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ShiftRow {
    id: number;
    branch_id: number;
    shift_name: string;
    start_time: string;
    end_time: string;
    late_tolerance_minutes: number;
}

const props = defineProps<{
    branches: Array<{ id: number; branch_code: string; branch_name: string }>;
    branchId: number;
    shifts: ShiftRow[];
}>();

const isOpen = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    branch_id: props.branchId,
    shift_name: '',
    start_time: '09:00',
    end_time: '17:00',
    late_tolerance_minutes: 15,
});

const changeBranch = (e: Event) => {
    router.get('/shifts', { branch_id: (e.target as HTMLSelectElement).value }, { preserveState: true });
};

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.branch_id = props.branchId;
    isOpen.value = true;
};

const openEdit = (s: ShiftRow) => {
    editingId.value = s.id;
    form.clearErrors();
    form.branch_id = s.branch_id;
    form.shift_name = s.shift_name;
    form.start_time = s.start_time.slice(0, 5);
    form.end_time = s.end_time.slice(0, 5);
    form.late_tolerance_minutes = s.late_tolerance_minutes;
    isOpen.value = true;
};

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => (isOpen.value = false) };
    if (editingId.value) {
        form.put(`/shifts/${editingId.value}`, opts);
    } else {
        form.post('/shifts', opts);
    }
};

const remove = (s: ShiftRow) => {
    if (confirm(`Hapus shift ${s.shift_name}? Riwayat jadwal tetap tersimpan.`)) {
        router.delete(`/shifts/${s.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Master Shift - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Master Shift</h1>
                <p class="text-sm text-gray-500">Jam kerja shift per cabang. Dipakai saat menyusun jadwal karyawan.</p>
            </div>
            <div class="flex items-center gap-2">
                <select
                    :value="branchId"
                    class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                    @change="changeBranch"
                >
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.branch_name }}</option>
                </select>
                <Button class="gap-1.5" @click="openCreate"><Plus class="size-4" /> Tambah Shift</Button>
            </div>
        </div>

        <Card>
            <CardHeader class="border-b pb-3">
                <CardTitle class="flex items-center gap-2 text-sm font-bold">
                    <Clock class="size-4 text-rose-500" /> Daftar Shift
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="shifts.length === 0" class="p-8 text-center text-sm text-gray-400">
                    Belum ada shift di cabang ini.
                </div>
                <div v-else class="divide-y">
                    <div v-for="s in shifts" :key="s.id" class="flex items-center justify-between gap-3 p-4">
                        <div>
                            <div class="font-bold text-gray-900 dark:text-white">{{ s.shift_name }}</div>
                            <div class="font-mono text-xs text-gray-500">
                                {{ s.start_time.slice(0, 5) }} – {{ s.end_time.slice(0, 5) }} · toleransi
                                {{ s.late_tolerance_minutes }} menit
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <Button size="icon" variant="ghost" @click="openEdit(s)"><Edit3 class="size-4" /></Button>
                            <Button size="icon" variant="ghost" class="text-rose-500" @click="remove(s)">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="isOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Edit Shift' : 'Tambah Shift' }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label for="shift_name">Nama Shift</Label>
                        <Input id="shift_name" v-model="form.shift_name" placeholder="Shift Pagi" />
                        <InputError :message="form.errors.shift_name" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="start_time">Jam Mulai</Label>
                            <Input id="start_time" v-model="form.start_time" type="time" />
                            <InputError :message="form.errors.start_time" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="end_time">Jam Selesai</Label>
                            <Input id="end_time" v-model="form.end_time" type="time" />
                            <InputError :message="form.errors.end_time" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="tol">Toleransi Terlambat (menit)</Label>
                        <Input id="tol" v-model="form.late_tolerance_minutes" type="number" min="0" max="240" />
                        <InputError :message="form.errors.late_tolerance_minutes" />
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
