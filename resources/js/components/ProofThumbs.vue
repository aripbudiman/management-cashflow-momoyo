<script setup lang="ts">
import { ref } from 'vue';
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';

// Thumbnail bukti di daftar; klik untuk preview di modal.
defineProps<{ proofs?: Array<{ id: number; url: string }> }>();
const preview = ref<{ url: string; name: string } | null>(null);
</script>

<template>
    <div v-if="proofs?.length" class="mt-1.5 flex flex-wrap gap-1.5">
        <button v-for="p in proofs" :key="p.id" type="button" class="size-9 overflow-hidden rounded-md border shadow-sm hover:ring-2 hover:ring-rose-400" title="Lihat bukti" @click="preview = { url: p.url, name: 'Bukti pengeluaran' }">
            <img :src="p.url" alt="Bukti" class="size-full object-cover" />
        </button>
        <ImagePreviewDialog v-model="preview" />
    </div>
</template>
