<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Edit3, Package, Plus, Trash2, Upload } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import RupiahInput from '@/components/RupiahInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rupiah } from '@/lib/format';

interface ItemRow {
    id: number;
    name: string;
    unit: string;
    price: string;
    min_stock: string;
    is_active: boolean;
}

const props = defineProps<{
    items: { data: ItemRow[]; links: Array<{ url: string | null; label: string; active: boolean }>; total: number };
    filters: { search?: string };
}>();

const isOpen = ref(false);
const editingId = ref<number | null>(null);
const search = ref(props.filters.search ?? '');

const form = useForm({ name: '', unit: '', price: '' as number | '', min_stock: 0, is_active: true });

const doSearch = () => router.get('/items', { search: search.value || undefined }, { preserveState: true });

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    isOpen.value = true;
};

const openEdit = (i: ItemRow) => {
    editingId.value = i.id;
    form.clearErrors();
    form.name = i.name;
    form.unit = i.unit;
    form.price = Number(i.price);
    form.min_stock = Number(i.min_stock);
    form.is_active = i.is_active;
    isOpen.value = true;
};

const submit = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            isOpen.value = false;
            form.reset();
            form.clearErrors();
        },
    };
    if (editingId.value) {
        form.put(`/items/${editingId.value}`, opts);
    } else {
        form.post('/items', opts);
    }
};

const remove = (i: ItemRow) => {
    if (confirm(`Hapus ${i.name}? Jika sudah punya riwayat stok, barang hanya dinonaktifkan.`)) {
        router.delete(`/items/${i.id}`, { preserveScroll: true });
    }
};

// Impor: tempel daftar dari client, satu baris satu barang → nama;satuan;harga
const importOpen = ref(false);
const importText = ref('');
const importError = ref('');
const importing = ref(false);

const submitImport = () => {
    const rows = importText.value
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean)
        .map((l) => {
            const [name, unit, price] = l.split(/[;\t]/).map((s) => s.trim());
            return { name, unit, price: Number((price ?? '').replace(/[^\d.]/g, '')) };
        });

    if (rows.some((r) => !r.name || !r.unit || Number.isNaN(r.price))) {
        importError.value = 'Format salah. Gunakan: nama;satuan;harga (satu barang per baris).';
        return;
    }
    importError.value = '';
    importing.value = true;
    router.post('/items/import', { rows }, {
        preserveScroll: true,
        onSuccess: () => {
            importOpen.value = false;
            importText.value = '';
        },
        onError: (e) => (importError.value = Object.values(e)[0] as string),
        onFinish: () => (importing.value = false),
    });
};
</script>

<template>
    <Head title="Master Barang - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Master Barang</h1>
                <p class="text-sm text-gray-500">Satu daftar barang dan harga untuk semua cabang.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Input v-model="search" placeholder="Cari barang..." class="h-9 w-44" @keyup.enter="doSearch" />
                <Button variant="outline" class="gap-1.5" @click="importOpen = true"><Upload class="size-4" /> Impor</Button>
                <Button class="gap-1.5" @click="openCreate"><Plus class="size-4" /> Tambah Barang</Button>
            </div>
        </div>

        <Card>
            <CardHeader class="border-b pb-3">
                <CardTitle class="flex items-center gap-2 text-sm font-bold">
                    <Package class="size-4 text-rose-500" /> {{ items.total }} Barang
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="items.data.length === 0" class="p-8 text-center text-sm text-gray-400">Belum ada barang.</div>
                <div v-else class="divide-y">
                    <div v-for="i in items.data" :key="i.id" class="flex items-center justify-between gap-3 p-4">
                        <div>
                            <div class="flex items-center gap-2 font-bold text-gray-900 dark:text-white">
                                {{ i.name }}
                                <Badge v-if="!i.is_active" variant="secondary">Nonaktif</Badge>
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ rupiah(i.price) }} / {{ i.unit }} · stok minimum {{ Number(i.min_stock) }}
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <Button size="icon" variant="ghost" @click="openEdit(i)"><Edit3 class="size-4" /></Button>
                            <Button size="icon" variant="ghost" class="text-rose-500" @click="remove(i)"><Trash2 class="size-4" /></Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div v-if="items.links.length > 3" class="flex flex-wrap gap-1">
            <Button
                v-for="(l, idx) in items.links"
                :key="idx"
                size="sm"
                :variant="l.active ? 'default' : 'outline'"
                :disabled="!l.url"
                @click="l.url && router.get(l.url, {}, { preserveState: true })"
                v-html="l.label"
            />
        </div>

        <Dialog v-model:open="isOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Edit Barang' : 'Tambah Barang' }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label for="name">Nama Barang</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="unit">Satuan</Label>
                            <Input id="unit" v-model="form.unit" placeholder="pcs, kg, liter" />
                            <InputError :message="form.errors.unit" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="price">Harga</Label>
                            <RupiahInput id="price" v-model="form.price" />
                            <InputError :message="form.errors.price" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="min_stock">Stok Minimum (alert)</Label>
                        <Input id="min_stock" v-model="form.min_stock" type="number" min="0" step="0.01" />
                        <InputError :message="form.errors.min_stock" />
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" /> Aktif</label>
                    <DialogFooter>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="importOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Impor Daftar Barang</DialogTitle>
                    <DialogDescription>
                        Tempel dari Excel/Sheets atau ketik manual. Satu baris satu barang, format
                        <span class="font-mono">nama;satuan;harga</span>. Barang dengan nama + satuan yang sama akan di-update harganya.
                    </DialogDescription>
                </DialogHeader>
                <textarea
                    v-model="importText"
                    rows="10"
                    class="w-full rounded-md border border-gray-200 bg-white p-3 font-mono text-xs dark:border-zinc-700 dark:bg-zinc-900"
                    placeholder="Susu UHT;liter;18000&#10;Gula Pasir;kg;16000"
                />
                <p v-if="importError" class="text-sm text-red-500">{{ importError }}</p>
                <DialogFooter>
                    <Button :disabled="importing || !importText.trim()" @click="submitImport">Impor</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
