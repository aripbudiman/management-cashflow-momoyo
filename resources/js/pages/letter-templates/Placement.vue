<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Eye, RotateCcw, Save } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import LetterEditor from '@/components/LetterEditor.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    template: { html: string; number_format: string; margin_cm: number };
    variables: Record<string, string>;
}>();

const form = useForm({
    html: props.template.html,
    number_format: props.template.number_format,
    margin_cm: props.template.margin_cm,
});

const editor = ref<InstanceType<typeof LetterEditor> | null>(null);
const insertVariable = (key: string) => editor.value?.insertText(`{{${key}}}`);

const save = () => form.put('/settings/placement-template', { preserveScroll: true });
const reset = () => {
    if (confirm('Kembalikan template ke bawaan? Isi template saat ini akan hilang.')) {
        router.delete('/settings/placement-template', {
            preserveScroll: true,
            onSuccess: () => (form.html = props.template.html),
        });
    }
};

const previewOpen = ref(false);
const previewHtml = ref('');
const previewError = ref('');
const loadingPreview = ref(false);

const xsrf = () => decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

const preview = async () => {
    loadingPreview.value = true;
    previewError.value = '';
    try {
        const res = await fetch('/settings/placement-template/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
            body: JSON.stringify({ html: form.html, margin_cm: form.margin_cm }),
        });
        if (!res.ok) throw new Error('Preview gagal dimuat.');
        previewHtml.value = (await res.json()).html;
        previewOpen.value = true;
    } catch (e) {
        previewError.value = (e as Error).message;
    } finally {
        loadingPreview.value = false;
    }
};
</script>

<template>
    <Head title="Template Surat Penempatan" />

    <div class="flex-1 space-y-5 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Template Surat Penempatan</h1>
                <p class="text-sm text-gray-500">Atur isi surat seperti di Word. Variabel <code v-text="'{{nama}}'" /> diganti data penempatan saat surat dicetak. Surat yang sudah terbit tidak ikut berubah.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" class="gap-1.5" @click="reset"><RotateCcw class="size-4" /> Bawaan</Button>
                <Button variant="outline" class="gap-1.5" :disabled="loadingPreview" @click="preview"><Eye class="size-4" /> Preview</Button>
                <Button class="gap-1.5" :disabled="form.processing" @click="save"><Save class="size-4" /> Simpan</Button>
            </div>
        </div>

        <p v-if="previewError" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ previewError }}</p>

        <div class="grid gap-5 lg:grid-cols-[1fr_280px]">
            <div class="min-w-0 space-y-2">
                <LetterEditor ref="editor" v-model="form.html" />
                <InputError :message="form.errors.html" />
            </div>

            <div class="space-y-4">
                <Card>
                    <CardHeader class="border-b pb-3"><CardTitle class="text-sm font-bold">Variabel</CardTitle></CardHeader>
                    <CardContent class="space-y-1 p-3">
                        <p class="pb-1 text-xs text-gray-500">Klik untuk menyisipkan di posisi kursor.</p>
                        <button
                            v-for="(label, key) in variables"
                            :key="key"
                            type="button"
                            class="block w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-left hover:border-rose-300 hover:bg-rose-50 dark:border-zinc-700"
                            @click="insertVariable(String(key))"
                        >
                            <span class="block font-mono text-xs font-bold text-rose-700" v-text="`{{${key}}}`" />
                            <span class="block text-[11px] text-gray-500">{{ label }}</span>
                        </button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="border-b pb-3"><CardTitle class="text-sm font-bold">Pengaturan Surat</CardTitle></CardHeader>
                    <CardContent class="space-y-3 p-3">
                        <div class="space-y-1.5">
                            <Label for="fmt">Format nomor surat</Label>
                            <Input id="fmt" v-model="form.number_format" class="font-mono text-xs" />
                            <p class="text-[11px] text-gray-500">Pakai {kode-proyek}, {bulan-romawi}, {tahun}, {urut}.</p>
                            <InputError :message="form.errors.number_format" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="margin">Margin kertas A4 (cm)</Label>
                            <Input id="margin" v-model="form.margin_cm" type="number" step="0.1" min="1" max="5" />
                            <InputError :message="form.errors.margin_cm" />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="previewOpen">
            <DialogContent class="max-h-[92vh] sm:max-w-3xl">
                <DialogHeader><DialogTitle>Preview (data contoh)</DialogTitle></DialogHeader>
                <iframe :srcdoc="previewHtml" sandbox="" class="h-[70vh] w-full rounded-lg border bg-white" title="Preview surat" />
            </DialogContent>
        </Dialog>
    </div>
</template>
