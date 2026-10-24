<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { BadgeCheck, Camera, Clock3 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import InputError from '@/components/InputError.vue';
import PhotoGalleryInput from '@/components/PhotoGalleryInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProgressLog, Project, WorkItem, WorkSection } from '@/lib/project';
import { averageProgress, itemStatusClass, itemStatusLabels } from '@/lib/project';

const props = defineProps<{ project: Project; sections: WorkSection[]; logs: ProgressLog[]; canVerify: boolean }>();

const selectCls = 'h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';

const sectionFilter = ref('');
const statusFilter = ref('');
const visibleSections = computed(() =>
    props.sections
        .filter((s) => !sectionFilter.value || String(s.id) === sectionFilter.value)
        .map((s) => ({ ...s, items: s.items.filter((i) => !statusFilter.value || i.status === statusFilter.value) }))
        .filter((s) => s.items.length > 0),
);

const open = ref(false);
const target = ref<WorkItem | null>(null);
const form = useForm<{ persen: number; catatan: string; photos: File[] }>({ persen: 0, catatan: '', photos: [] });
const logPreview = ref<{ url: string; name: string } | null>(null);

const openUpdate = (item: WorkItem) => {
    target.value = item;
    form.reset();
    form.clearErrors();
    form.persen = item.progress;
    open.value = true;
};
const lowering = computed(() => !!target.value && Number(form.persen) < target.value.progress);

const submit = () => {
    if (!target.value) return;
    form.post(`/work-items/${target.value.id}/progress`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};

const verify = (log: ProgressLog) => router.post(`/progress-logs/${log.id}/verify`, {}, { preserveScroll: true });
const when = (iso: string) => new Date(iso).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
const pendingCount = computed(() => props.logs.filter((l) => !l.verified_at).length);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <select v-model="sectionFilter" :class="selectCls">
                <option value="">Semua bidang</option>
                <option v-for="s in sections" :key="s.id" :value="String(s.id)">{{ s.kode_bidang }}. {{ s.nama }}</option>
            </select>
            <select v-model="statusFilter" :class="selectCls">
                <option value="">Semua status</option>
                <option v-for="(label, key) in itemStatusLabels" :key="key" :value="key">{{ label }}</option>
            </select>
        </div>

        <Card v-if="visibleSections.length === 0">
            <CardContent class="p-8 text-center text-sm text-gray-400">Tidak ada item pekerjaan. Isi RAB dulu di tab RAB.</CardContent>
        </Card>

        <Card v-for="sec in visibleSections" :key="sec.id">
            <CardHeader class="border-b pb-3">
                <div class="flex items-center justify-between gap-3">
                    <CardTitle class="text-sm font-bold">{{ sec.kode_bidang }}. {{ sec.nama }}</CardTitle>
                    <span class="text-xs font-bold text-rose-600">{{ averageProgress(sections.find((s) => s.id === sec.id)?.items ?? [], project.progress_mode) }}%</span>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="divide-y">
                    <button v-for="item in sec.items" :key="item.id" type="button" class="block w-full space-y-1.5 p-3 text-left hover:bg-gray-50 dark:hover:bg-zinc-900" @click="openUpdate(item)">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 text-sm">
                                <div class="font-semibold">{{ item.uraian }}</div>
                                <div v-if="item.deskripsi" class="truncate text-xs text-gray-500">{{ item.deskripsi }}</div>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold" :class="itemStatusClass[item.status]">{{ itemStatusLabels[item.status] }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-zinc-800">
                                <div class="h-full rounded-full" :class="item.progress >= 100 ? 'bg-emerald-500' : 'bg-rose-500'" :style="{ width: `${item.progress}%` }" />
                            </div>
                            <span class="w-10 text-right text-xs font-bold">{{ item.progress }}%</span>
                        </div>
                    </button>
                </div>
            </CardContent>
        </Card>

        <Card v-if="logs.length">
            <CardHeader class="border-b pb-3">
                <CardTitle class="flex items-center gap-2 text-sm font-bold">
                    Riwayat Update
                    <span v-if="pendingCount" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] text-amber-700">{{ pendingCount }} menunggu verifikasi</span>
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="divide-y">
                    <div v-for="log in logs" :key="log.id" class="space-y-1.5 p-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-semibold">{{ log.work_item.uraian }}</div>
                                <div class="text-xs text-gray-500">{{ log.user.name }} · {{ when(log.created_at) }} · {{ log.progress_dari }}% → {{ log.progress_ke }}%</div>
                            </div>
                            <span v-if="log.verified_at" class="flex shrink-0 items-center gap-1 text-[11px] font-bold text-emerald-600"><BadgeCheck class="size-3.5" /> {{ log.verifier?.name }}</span>
                            <Button v-else-if="canVerify" size="sm" variant="outline" class="h-7 shrink-0" @click="verify(log)">Verifikasi</Button>
                            <span v-else class="flex shrink-0 items-center gap-1 text-[11px] font-bold text-amber-600"><Clock3 class="size-3.5" /> Menunggu</span>
                        </div>
                        <p v-if="log.catatan" class="text-xs text-gray-600">{{ log.catatan }}</p>
                        <div v-if="log.photos.length" class="flex flex-wrap gap-2">
                            <button v-for="(ph, idx) in log.photos" :key="ph.id" type="button" @click="logPreview = { url: ph.url, name: `${log.work_item.uraian} (${idx + 1}/${log.photos.length})` }">
                                <img :src="ph.url" alt="Foto progress" class="size-16 rounded-lg border object-cover hover:opacity-80" loading="lazy" />
                            </button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <ImagePreviewDialog v-model="logPreview" />

        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Update Progress</DialogTitle>
                    <DialogDescription>{{ target?.uraian }}</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="progress">Progress</Label>
                            <span class="text-lg font-black text-rose-600">{{ form.persen }}%</span>
                        </div>
                        <input id="progress" v-model.number="form.persen" type="range" min="0" max="100" step="5" class="w-full accent-rose-600" />
                        <InputError :message="form.errors.persen" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="catatan">Catatan <span v-if="lowering" class="text-red-500">(wajib, progress diturunkan)</span></Label>
                        <Input id="catatan" v-model="form.catatan" placeholder="Kondisi pekerjaan di lapangan" />
                        <InputError :message="form.errors.catatan" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5"><Camera class="size-4" /> Foto bukti</Label>
                        <PhotoGalleryInput v-model="form.photos" :max="5" />
                        <InputError :message="form.errors.photos" />
                        <InputError v-for="(msg, key) in form.errors" v-show="String(key).startsWith('photos.')" :key="key" :message="msg" />
                    </div>
                    <DialogFooter><Button type="submit" :disabled="form.processing">Simpan Progress</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
