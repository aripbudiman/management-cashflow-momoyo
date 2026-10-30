<script setup lang="ts">
import { computed, ref } from 'vue';
import { X } from '@lucide/vue';
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import PhotoGalleryInput from '@/components/PhotoGalleryInput.vue';
import { Label } from '@/components/ui/label';

// Bukti opsional (maks 5): foto lama (bisa ditandai hapus) + foto baru.
const files = defineModel<File[]>('files', { required: true });
const removed = defineModel<number[]>('removed', { required: true });
const props = defineProps<{ existing: Array<{ id: number; url: string }> }>();

const MAX = 5;
const kept = computed(() => props.existing.filter((p) => !removed.value.includes(p.id)));
const preview = ref<{ url: string; name: string } | null>(null);
</script>

<template>
    <div class="space-y-2">
        <Label>Bukti <span class="text-gray-400">(opsional, maks {{ MAX }} foto)</span></Label>
        <div v-if="kept.length" class="flex flex-wrap gap-2">
            <div v-for="p in kept" :key="p.id" class="group relative size-16 overflow-hidden rounded-lg border">
                <button type="button" class="size-full" @click="preview = { url: p.url, name: 'Bukti pengeluaran' }"><img :src="p.url" alt="Bukti pengeluaran" class="size-full object-cover" /></button>
                <button type="button" class="absolute top-0.5 right-0.5 flex size-5 items-center justify-center rounded-full bg-black/60 text-white" title="Hapus foto" @click="removed = [...removed, p.id]"><X class="size-3" /></button>
            </div>
        </div>
        <PhotoGalleryInput v-if="kept.length < MAX" v-model="files" :max="MAX - kept.length" />
        <ImagePreviewDialog v-model="preview" />
    </div>
</template>
