<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Edit3, Plus, Ruler, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface UnitRow {
    id: number;
    name: string;
    item_units_count: number;
}

defineProps<{ units: UnitRow[] }>();

const page = usePage();
const deleteError = computed(() => (page.props.errors as Record<string, string>).unit);

const isOpen = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({ name: '' });

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    isOpen.value = true;
};

const openEdit = (u: UnitRow) => {
    editingId.value = u.id;
    form.clearErrors();
    form.name = u.name;
    isOpen.value = true;
};

const submit = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            isOpen.value = false;
            form.reset();
        },
    };
    if (editingId.value) {
        form.put(`/units/${editingId.value}`, opts);
    } else {
        form.post('/units', opts);
    }
};

const remove = (u: UnitRow) => {
    if (confirm(`Hapus satuan ${u.name}?`)) {
        router.delete(`/units/${u.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Master Satuan - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Master Satuan</h1>
                <p class="text-sm text-gray-500">Daftar nama satuan. Isi per satuan (misal 1 karton = 20 box) diatur di tiap barang.</p>
            </div>
            <Button class="gap-1.5" @click="openCreate"><Plus class="size-4" /> Tambah Satuan</Button>
        </div>

        <p v-if="deleteError" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ deleteError }}</p>

        <Card>
            <CardHeader class="border-b pb-3">
                <CardTitle class="flex items-center gap-2 text-sm font-bold"><Ruler class="size-4 text-rose-500" /> {{ units.length }} Satuan</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="divide-y">
                    <div v-for="u in units" :key="u.id" class="flex items-center justify-between gap-3 p-4">
                        <div>
                            <div class="font-bold text-gray-900 dark:text-white">{{ u.name }}</div>
                            <div class="text-xs text-gray-500">Dipakai {{ u.item_units_count }} barang</div>
                        </div>
                        <div class="flex gap-1">
                            <Button size="icon" variant="ghost" @click="openEdit(u)"><Edit3 class="size-4" /></Button>
                            <Button size="icon" variant="ghost" class="text-rose-500" @click="remove(u)"><Trash2 class="size-4" /></Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="isOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader><DialogTitle>{{ editingId ? 'Edit Satuan' : 'Tambah Satuan' }}</DialogTitle></DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label for="unit_name">Nama Satuan</Label>
                        <Input id="unit_name" v-model="form.name" placeholder="karton" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <DialogFooter><Button type="submit" :disabled="form.processing">Simpan</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
