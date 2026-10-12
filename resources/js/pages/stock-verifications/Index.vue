<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, Eye, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { fmtDate } from '@/lib/format';

interface Report {
    id: number;
    report_date: string;
    status: string;
    notes: string | null;
    revision_notes: string | null;
    branch: { branch_name: string };
    submitted_by: { name: string } | null;
    verified_by: { name: string } | null;
    items: Array<{
        id: number;
        opening_qty: string;
        in_qty: string;
        out_qty: string;
        waste_qty: string;
        expected_qty: string;
        physical_qty: string | null;
        diff_qty: string;
        item: { name: string; unit: string };
    }>;
}

const props = defineProps<{
    reports: { data: Report[]; links: Array<{ url: string | null; label: string; active: boolean }>; total: number };
    branches: Array<{ id: number; branch_name: string }>;
    filters: { branch_id?: string; status?: string; month?: string };
}>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const statusMap: Record<string, { label: string; cls: string }> = {
    submitted: { label: 'Menunggu', cls: 'bg-amber-100 text-amber-700' },
    verified: { label: 'Terverifikasi', cls: 'bg-emerald-100 text-emerald-700' },
    revision_needed: { label: 'Perlu Revisi', cls: 'bg-red-100 text-red-700' },
};

const filter = (k: string, v: string) => router.get('/stock-verifications', { ...props.filters, [k]: v || undefined }, { preserveState: true, preserveScroll: true });
const selectCls = 'h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';

const detail = ref<Report | null>(null);
const revising = ref(false);
const notes = ref('');

const diffCount = (r: Report) => r.items.filter((i) => Number(i.diff_qty) !== 0).length;

const verify = (r: Report) =>
    router.post(`/stock-verifications/${r.id}/verify`, {}, { preserveScroll: true, onSuccess: () => (detail.value = null) });

const revise = () => {
    if (!detail.value) return;
    router.post(`/stock-verifications/${detail.value.id}/revise`, { revision_notes: notes.value }, {
        preserveScroll: true,
        onSuccess: () => {
            revising.value = false;
            detail.value = null;
            notes.value = '';
        },
    });
};
</script>

<template>
    <Head title="Verifikasi Stok - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Verifikasi Stok Harian</h1>
                <p class="text-sm text-gray-500">{{ reports.total }} laporan stok dari cabang.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <select :value="filters.branch_id ?? ''" :class="selectCls" @change="filter('branch_id', ($event.target as HTMLSelectElement).value)">
                    <option value="">Semua Cabang</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.branch_name }}</option>
                </select>
                <select :value="filters.status ?? ''" :class="selectCls" @change="filter('status', ($event.target as HTMLSelectElement).value)">
                    <option value="">Semua Status</option>
                    <option v-for="(s, k) in statusMap" :key="k" :value="k">{{ s.label }}</option>
                </select>
                <input type="month" :value="filters.month ?? ''" :class="selectCls" @change="filter('month', ($event.target as HTMLInputElement).value)" />
            </div>
        </div>

        <Card>
            <CardContent class="p-0">
                <div v-if="reports.data.length === 0" class="p-8 text-center text-sm text-gray-400">Belum ada laporan.</div>
                <div v-else class="divide-y">
                    <div v-for="r in reports.data" :key="r.id" class="flex items-center justify-between gap-3 p-4">
                        <div>
                            <div class="font-bold text-gray-900 dark:text-white">{{ r.branch.branch_name }} · {{ fmtDate(r.report_date) }}</div>
                            <div class="text-xs text-gray-500">
                                {{ r.items.length }} barang dicatat ·
                                <span :class="diffCount(r) ? 'font-bold text-red-600' : ''">{{ diffCount(r) }} selisih opname</span>
                                · oleh {{ r.submitted_by?.name ?? '-' }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge :class="statusMap[r.status]?.cls">{{ statusMap[r.status]?.label }}</Badge>
                            <Button size="icon" variant="ghost" @click="detail = r"><Eye class="size-4" /></Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div v-if="reports.links.length > 3" class="flex flex-wrap gap-1">
            <Button
                v-for="(l, idx) in reports.links"
                :key="idx"
                size="sm"
                :variant="l.active ? 'default' : 'outline'"
                :disabled="!l.url"
                @click="l.url && router.get(l.url, {}, { preserveState: true })"
                v-html="l.label"
            />
        </div>

        <Dialog :open="!!detail" @update:open="(v: boolean) => !v && (detail = null)">
            <DialogContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{ detail?.branch.branch_name }} · {{ detail && fmtDate(detail.report_date) }}</DialogTitle>
                </DialogHeader>
                <div v-if="detail" class="space-y-3">
                    <div class="max-h-[50vh] overflow-auto rounded-md border">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-gray-50 text-xs text-gray-500 dark:bg-zinc-900">
                                <tr>
                                    <th class="p-2 text-left">Barang</th>
                                    <th class="p-2 text-right">Awal</th>
                                    <th class="p-2 text-right">Masuk</th>
                                    <th class="p-2 text-right">Keluar</th>
                                    <th class="p-2 text-right">Rusak</th>
                                    <th class="p-2 text-right">Fisik</th>
                                    <th class="p-2 text-right">Selisih</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="i in detail.items" :key="i.id">
                                    <td class="p-2">{{ i.item.name }} <span class="text-xs text-gray-400">({{ i.item.unit }})</span></td>
                                    <td class="p-2 text-right font-mono">{{ Number(i.opening_qty) }}</td>
                                    <td class="p-2 text-right font-mono">{{ Number(i.in_qty) }}</td>
                                    <td class="p-2 text-right font-mono">{{ Number(i.out_qty) }}</td>
                                    <td class="p-2 text-right font-mono">{{ Number(i.waste_qty) }}</td>
                                    <td class="p-2 text-right font-mono">{{ i.physical_qty === null ? '-' : Number(i.physical_qty) }}</td>
                                    <td class="p-2 text-right font-mono font-bold" :class="Number(i.diff_qty) ? 'text-red-600' : 'text-gray-400'">
                                        {{ Number(i.diff_qty) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="detail.notes" class="text-sm text-gray-600">Catatan: {{ detail.notes }}</p>
                    <p v-if="detail.revision_notes" class="text-sm text-red-600">Revisi: {{ detail.revision_notes }}</p>
                    <InputError :message="errors.status" />

                    <div v-if="revising" class="space-y-2">
                        <textarea v-model="notes" rows="2" placeholder="Alasan revisi" class="w-full rounded-md border border-gray-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        <InputError :message="errors.revision_notes" />
                    </div>
                </div>
                <DialogFooter v-if="detail?.status === 'submitted'" class="gap-2">
                    <template v-if="!revising">
                        <Button variant="outline" class="gap-1.5 text-red-600" @click="revising = true"><X class="size-4" /> Minta Revisi</Button>
                        <Button class="gap-1.5" @click="verify(detail)"><Check class="size-4" /> Verifikasi</Button>
                    </template>
                    <template v-else>
                        <Button variant="outline" @click="revising = false">Batal</Button>
                        <Button class="bg-red-600 hover:bg-red-700" @click="revise">Kirim Revisi</Button>
                    </template>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
