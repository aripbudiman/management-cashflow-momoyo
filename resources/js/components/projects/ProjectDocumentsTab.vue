<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { FileText, Trash2, Upload } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fmtDate } from '@/lib/format';
import type { Project, ProjectDocument } from '@/lib/project';
import { documentCategories } from '@/lib/project';

const props = defineProps<{ project: Project; canManage: boolean }>();

const selectCls = 'h-9 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900';

const fileInput = ref<HTMLInputElement | null>(null);
const form = useForm<{ kategori: string; file: File | null }>({ kategori: 'gambar_kerja', file: null });

const submit = () => {
    form.post(`/projects/${props.project.id}/documents`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('file');
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};
const remove = (d: ProjectDocument) => {
    if (confirm(`Hapus dokumen ${d.nama_file}?`)) router.delete(`/project-documents/${d.id}`, { preserveScroll: true });
};
</script>

<template>
    <div class="space-y-4">
        <Card v-if="canManage">
            <CardContent class="p-4">
                <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label>Kategori</Label>
                        <select v-model="form.kategori" :class="[selectCls, 'block']">
                            <option v-for="(label, key) in documentCategories" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div class="flex-1 space-y-1.5">
                        <Label>File (PDF, gambar, Excel, Word, DWG - maks 20 MB)</Label>
                        <Input ref="fileInput" type="file" @change="(e: Event) => (form.file = (e.target as HTMLInputElement).files?.[0] ?? null)" />
                    </div>
                    <Button type="submit" class="gap-1.5" :disabled="form.processing || !form.file"><Upload class="size-4" /> Unggah</Button>
                </form>
                <InputError class="mt-2" :message="form.errors.file ?? form.errors.kategori" />
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-0">
                <div v-if="!project.documents?.length" class="p-8 text-center text-sm text-gray-400">Belum ada dokumen.</div>
                <div v-else class="divide-y">
                    <div v-for="d in project.documents" :key="d.id" class="flex items-center justify-between gap-3 p-3 text-sm">
                        <a :href="d.url" target="_blank" rel="noopener" class="flex min-w-0 items-center gap-2.5 hover:underline">
                            <FileText class="size-5 shrink-0 text-rose-500" />
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ d.nama_file }}</span>
                                <span class="text-xs text-gray-500">{{ documentCategories[d.kategori] }} · {{ fmtDate(d.created_at) }}<template v-if="d.uploader"> · {{ d.uploader.name }}</template></span>
                            </span>
                        </a>
                        <Button v-if="canManage" size="icon" variant="ghost" class="size-8 shrink-0 text-rose-500" @click="remove(d)"><Trash2 class="size-4" /></Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
