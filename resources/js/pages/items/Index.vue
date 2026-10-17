<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Edit3, Package, Plus, Trash2, Upload } from '@lucide/vue';
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
import type { UnitOption } from '@/lib/units';

interface ItemRow {
    id: number;
    name: string;
    unit: string;
    unit_options: UnitOption[];
    price: string;
    price_unit_id: number | null;
    default_in_unit_id: number | null;
    default_out_unit_id: number | null;
    min_stock: string;
    is_active: boolean;
}

const props = defineProps<{
    items: { data: ItemRow[]; links: Array<{ url: string | null; label: string; active: boolean }>; total: number };
    units: Array<{ id: number; name: string }>;
    filters: { search?: string };
}>();

const isOpen = ref(false);
const editingId = ref<number | null>(null);
const search = ref(props.filters.search ?? '');

// Satuan diurut dari terkecil ke terbesar. Baris pertama = satuan terkecil (dasar stok);
// baris berikutnya: "1 satuan ini = N satuan sebelumnya".
interface UnitRowForm {
    unit_id: number | '';
    per: number | '';
}

const newUnitRows = (): UnitRowForm[] => [{ unit_id: '', per: 1 }];

const unitRows = ref<UnitRowForm[]>(newUnitRows());

const form = useForm({
    name: '',
    price: '' as number | '',
    price_unit_id: '' as number | '',
    default_in_unit_id: '' as number | '',
    default_out_unit_id: '' as number | '',
    min_stock: 0,
    is_active: true,
    units: [] as Array<{ unit_id: number; factor: number }>,
});

const factors = computed(() => {
    let acc = 1;
    return unitRows.value.map((r, i) => {
        acc = i === 0 ? 1 : acc * Number(r.per || 0);
        return acc;
    });
});

const chosenUnits = computed(() =>
    unitRows.value
        .map((r, i) => ({ r, i }))
        .filter(({ r }) => r.unit_id !== '')
        .map(({ r, i }) => ({ unit_id: Number(r.unit_id), name: unitName(r.unit_id), factor: factors.value[i] })),
);

const unitName = (id: number | ''): string => props.units.find((u) => u.id === id)?.name ?? '';

// Satu baris per satuan: "1 dus = 5 karton = 100 botol"
const preview = computed(() => {
    const rows = chosenUnits.value;
    return rows.slice(1).map((u, i) => {
        const lower = rows[i];
        const per = u.factor / lower.factor;
        const toBase = lower.factor === 1 ? '' : ` = ${u.factor} ${rows[0].name}`;
        return `1 ${u.name} = ${per} ${lower.name}${toBase}`;
    });
});

const addUnitRow = () => unitRows.value.push({ unit_id: '', per: '' });
const removeUnitRow = (i: number) => unitRows.value.splice(i, 1);

const selectCls = 'h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';

const doSearch = () => router.get('/items', { search: search.value || undefined }, { preserveState: true });

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    unitRows.value = newUnitRows();
    isOpen.value = true;
};

const openEdit = (i: ItemRow) => {
    editingId.value = i.id;
    form.clearErrors();
    form.name = i.name;
    form.price = Number(i.price);
    form.price_unit_id = i.price_unit_id ?? '';
    form.default_in_unit_id = i.default_in_unit_id ?? '';
    form.default_out_unit_id = i.default_out_unit_id ?? '';
    form.min_stock = Number(i.min_stock);
    form.is_active = i.is_active;
    unitRows.value = i.unit_options.map((u, idx) => ({
        unit_id: u.unit_id,
        per: idx === 0 ? 1 : Math.round((u.factor / i.unit_options[idx - 1].factor) * 100) / 100,
    }));
    isOpen.value = true;
};

const submit = () => {
    form.units = chosenUnits.value.map((u) => ({ unit_id: u.unit_id, factor: u.factor }));
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            isOpen.value = false;
            form.reset();
            form.clearErrors();
            unitRows.value = newUnitRows();
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
                                {{ rupiah(i.price) }} / {{ i.unit_options.find((u) => u.unit_id === i.price_unit_id)?.name ?? i.unit }}
                                · {{ [...i.unit_options].reverse().map((u) => u.name).join(' › ') }}
                                · stok minimum {{ Number(i.min_stock) }} {{ i.unit }}
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
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Edit Barang' : 'Tambah Barang' }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label for="name">Nama Barang</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="space-y-2 rounded-xl border border-gray-200 p-3 dark:border-zinc-700">
                        <Label>Satuan & Konversi</Label>
                        <p class="text-xs text-gray-500">Mulai dari satuan terkecil (dasar stok), lalu tambah satuan yang lebih besar.</p>
                        <div v-for="(row, idx) in unitRows" :key="idx" class="flex items-center gap-2">
                            <template v-if="idx > 0">
                                <span class="shrink-0 text-xs text-gray-500">1</span>
                            </template>
                            <select v-model="row.unit_id" :class="selectCls">
                                <option value="">{{ idx === 0 ? 'Satuan terkecil...' : 'Satuan lebih besar...' }}</option>
                                <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                            <template v-if="idx > 0">
                                <span class="shrink-0 text-xs text-gray-500">=</span>
                                <Input v-model="row.per" type="number" min="1" step="0.01" class="h-9 w-20" />
                                <span class="shrink-0 text-xs text-gray-500">{{ unitName(unitRows[idx - 1].unit_id) || '…' }}</span>
                                <Button type="button" size="icon" variant="ghost" class="shrink-0 text-rose-500" @click="removeUnitRow(idx)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </template>
                        </div>
                        <Button v-if="unitRows.length < 6" type="button" size="sm" variant="outline" class="gap-1" @click="addUnitRow">
                            <Plus class="size-3.5" /> Tambah satuan
                        </Button>
                        <div v-if="preview.length" class="font-mono text-xs text-emerald-600">
                            <p v-for="line in preview" :key="line">{{ line }}</p>
                        </div>
                        <InputError :message="form.errors.units || form.errors['units.0.unit_id']" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="price">Harga</Label>
                            <RupiahInput id="price" v-model="form.price" />
                            <InputError :message="form.errors.price" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Per satuan</Label>
                            <select v-model="form.price_unit_id" :class="selectCls">
                                <option value="">Pilih...</option>
                                <option v-for="u in chosenUnits" :key="u.unit_id" :value="u.unit_id">{{ u.name }}</option>
                            </select>
                            <InputError :message="form.errors.price_unit_id" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Default satuan masuk</Label>
                            <select v-model="form.default_in_unit_id" :class="selectCls">
                                <option value="">Otomatis (terbesar)</option>
                                <option v-for="u in chosenUnits" :key="u.unit_id" :value="u.unit_id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Default satuan keluar</Label>
                            <select v-model="form.default_out_unit_id" :class="selectCls">
                                <option value="">Otomatis (terkecil)</option>
                                <option v-for="u in chosenUnits" :key="u.unit_id" :value="u.unit_id">{{ u.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="min_stock">Stok Minimum (alert, dalam satuan terkecil)</Label>
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
                        <span class="font-mono">nama;satuan;harga</span>. Barang baru dibuat dengan satu satuan (terkecil); tambah satuan lain lewat Edit. Barang yang sudah ada hanya di-update harganya bila satuannya sama dengan satuan harga barang itu.
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
