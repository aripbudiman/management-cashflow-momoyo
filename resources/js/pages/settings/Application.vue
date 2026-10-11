<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ImagePlus, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    appName: string;
    iconUrl: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pengaturan Aplikasi', href: '/settings/application' }],
    },
});

const form = useForm<{ app_name: string; icon: File | null; remove_icon: boolean }>({
    app_name: props.appName,
    icon: null,
    remove_icon: false,
});

// Preview icon: file baru > icon tersimpan (kecuali ditandai hapus)
const localPreview = ref<string | null>(null);
const preview = computed(() => localPreview.value ?? (form.remove_icon ? null : props.iconUrl));

const pickIcon = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    if (localPreview.value) URL.revokeObjectURL(localPreview.value);
    localPreview.value = URL.createObjectURL(file);
    form.icon = file;
    form.remove_icon = false;
    input.value = '';
};

const removeIcon = () => {
    if (localPreview.value) URL.revokeObjectURL(localPreview.value);
    localPreview.value = null;
    form.icon = null;
    form.remove_icon = !!props.iconUrl;
};

onBeforeUnmount(() => localPreview.value && URL.revokeObjectURL(localPreview.value));

const submit = () =>
    form.post('/settings/application', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (localPreview.value) URL.revokeObjectURL(localPreview.value);
            localPreview.value = null;
            form.reset('icon', 'remove_icon');
        },
    });
</script>

<template>
    <Head title="Pengaturan Aplikasi" />

    <h1 class="sr-only">Pengaturan Aplikasi</h1>

    <div class="space-y-6">
        <Heading variant="small" title="Pengaturan Aplikasi" description="Ubah nama dan icon aplikasi. Tampil di sidebar, halaman login, judul tab, dan favicon." />

        <form class="max-w-xl space-y-6" @submit.prevent="submit">
            <div class="space-y-1.5">
                <Label for="app_name">Nama Aplikasi</Label>
                <Input id="app_name" v-model="form.app_name" maxlength="50" placeholder="Momoyo" />
                <InputError :message="form.errors.app_name" />
            </div>

            <div class="space-y-2">
                <Label>Icon Aplikasi</Label>
                <div class="flex items-center gap-4">
                    <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border bg-gray-50 dark:bg-zinc-900">
                        <img v-if="preview" :src="preview" alt="Icon aplikasi" class="size-full object-cover" />
                        <div v-else class="flex size-full items-center justify-center bg-gradient-to-tr from-rose-600 via-red-500 to-amber-400 text-3xl font-black text-white uppercase">
                            {{ form.app_name.charAt(0) || 'M' }}
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex gap-2">
                            <label class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-md border bg-background px-3 text-sm font-medium shadow-xs hover:bg-accent">
                                <ImagePlus class="size-4" /> {{ preview ? 'Ganti Icon' : 'Upload Icon' }}
                                <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="pickIcon" />
                            </label>
                            <Button v-if="preview" type="button" variant="outline" class="gap-1.5 text-rose-600" @click="removeIcon">
                                <Trash2 class="size-4" /> Hapus
                            </Button>
                        </div>
                        <p class="text-xs text-muted-foreground">PNG, JPG, atau WEBP · rasio persegi · minimal 64×64 px · maks 2 MB</p>
                    </div>
                </div>
                <InputError :message="form.errors.icon" />
            </div>

            <Button type="submit" :disabled="form.processing">Simpan</Button>
        </form>
    </div>
</template>
