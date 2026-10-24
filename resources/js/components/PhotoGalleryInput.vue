<script setup lang="ts">
import { ImagePlus, Plus, X } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';

// Galeri unggah foto: thumbnail + tombol tambah, klik thumbnail untuk preview, dukung drag & drop.
// Model berisi File[] (akumulatif, bukan diganti tiap pilih file).
const model = defineModel<File[]>({ required: true });
const { max = 5, maxSizeMb = 8 } = defineProps<{ max?: number; maxSizeMb?: number }>();

interface Thumb {
    file: File;
    url: string;
}

const thumbs = ref<Thumb[]>([]);
const input = ref<HTMLInputElement | null>(null);
const preview = ref<{ url: string; name: string } | null>(null);
const dragging = ref(false);
const message = ref('');

const revoke = () => thumbs.value.forEach((t) => URL.revokeObjectURL(t.url));

// Sinkron dari luar (mis. form di-reset): buang thumbnail yang filenya sudah tidak ada di model
watch(
    model,
    (files) => {
        thumbs.value = thumbs.value.filter((t) => {
            const keep = files.includes(t.file);
            if (!keep) URL.revokeObjectURL(t.url);
            return keep;
        });
        for (const file of files) {
            if (!thumbs.value.some((t) => t.file === file)) thumbs.value.push({ file, url: URL.createObjectURL(file) });
        }
    },
    { immediate: true, deep: false },
);

const add = (list: FileList | File[] | null) => {
    message.value = '';
    const next = [...model.value];
    for (const file of Array.from(list ?? [])) {
        if (!file.type.startsWith('image/')) {
            message.value = 'Hanya file gambar yang bisa diunggah.';
            continue;
        }
        if (file.size > maxSizeMb * 1024 * 1024) {
            message.value = `${file.name} lebih dari ${maxSizeMb} MB.`;
            continue;
        }
        if (next.length >= max) {
            message.value = `Maksimal ${max} foto.`;
            break;
        }
        next.push(file);
    }
    model.value = next;
};

const onPick = (e: Event) => {
    const el = e.target as HTMLInputElement;
    add(el.files);
    el.value = ''; // supaya file yang sama bisa dipilih lagi setelah dihapus
};

const onDrop = (e: DragEvent) => {
    dragging.value = false;
    add(e.dataTransfer?.files ?? null);
};

const remove = (file: File) => {
    model.value = model.value.filter((f) => f !== file);
};

onBeforeUnmount(revoke);
</script>

<template>
    <div class="space-y-2">
        <div
            class="grid grid-cols-3 gap-2 rounded-xl border-2 border-dashed p-2 transition-colors sm:grid-cols-4"
            :class="dragging ? 'border-rose-400 bg-rose-50 dark:bg-rose-950/20' : 'border-gray-200 dark:border-zinc-700'"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <div v-for="t in thumbs" :key="t.url" class="group relative aspect-square overflow-hidden rounded-lg border bg-gray-50 dark:border-zinc-700">
                <button type="button" class="size-full" :aria-label="`Preview ${t.file.name}`" @click="preview = { url: t.url, name: t.file.name }">
                    <img :src="t.url" :alt="t.file.name" class="size-full object-cover" />
                </button>
                <button
                    type="button"
                    class="absolute top-1 right-1 flex size-5 items-center justify-center rounded-full bg-black/60 text-white hover:bg-black/80"
                    :aria-label="`Hapus ${t.file.name}`"
                    @click.stop="remove(t.file)"
                >
                    <X class="size-3" />
                </button>
            </div>

            <button
                v-if="thumbs.length < max"
                type="button"
                class="flex aspect-square flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-gray-300 text-gray-500 hover:border-rose-400 hover:bg-rose-50 hover:text-rose-600 dark:border-zinc-600 dark:hover:bg-rose-950/20"
                @click="input?.click()"
            >
                <Plus class="size-6" />
                <span class="text-[11px] font-semibold">Tambah</span>
            </button>

            <div v-if="thumbs.length === 0" class="col-span-full -mt-1 flex items-center justify-center gap-1.5 pb-1 text-[11px] text-gray-400">
                <ImagePlus class="size-3.5" /> Klik Tambah atau seret foto ke sini
            </div>
        </div>

        <div class="flex items-center justify-between text-[11px] text-gray-500">
            <span :class="message && 'text-red-500'">{{ message || `Format gambar, maks ${maxSizeMb} MB per foto` }}</span>
            <span>{{ thumbs.length }}/{{ max }}</span>
        </div>

        <input ref="input" type="file" accept="image/*" multiple class="hidden" @change="onPick" />
        <ImagePreviewDialog v-model="preview" />
    </div>
</template>
