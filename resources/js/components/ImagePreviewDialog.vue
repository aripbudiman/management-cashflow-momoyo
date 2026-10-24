<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';

// Modal preview foto bukti. v-model berisi { url, name } atau null untuk tertutup.
defineProps<{ modelValue: { url: string; name: string } | null }>();
const emit = defineEmits<{ (e: 'update:modelValue', value: null): void }>();
</script>

<template>
    <Dialog :open="!!modelValue" @update:open="(v: boolean) => !v && emit('update:modelValue', null)">
        <DialogContent class="max-h-[90vh] max-w-xl overflow-auto rounded-3xl p-4 [&>*]:min-w-0">
            <DialogHeader class="min-w-0 pr-8">
                <DialogTitle class="block truncate text-sm font-bold" :title="modelValue?.name">{{ modelValue?.name }}</DialogTitle>
            </DialogHeader>
            <img v-if="modelValue" :src="modelValue.url" :alt="modelValue.name" class="mx-auto max-h-[70vh] max-w-full rounded-xl object-contain" />
        </DialogContent>
    </Dialog>
</template>
