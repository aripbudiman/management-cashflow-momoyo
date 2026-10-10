<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';

// Input nominal dengan pemisah ribuan "." — model berupa angka (atau '' jika kosong), tanpa spinner.
const props = defineProps<{ modelValue: number | ''; placeholder?: string }>();
const emit = defineEmits<{ (e: 'update:modelValue', value: number | ''): void }>();

const fmt = new Intl.NumberFormat('id-ID');
const display = computed(() => (props.modelValue === '' ? '' : fmt.format(props.modelValue)));

const onInput = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const digits = el.value.replace(/\D/g, '').slice(0, 12);
    const value = digits === '' ? '' : Number(digits);
    emit('update:modelValue', value);
    // paksa tampilan terformat langsung (tanpa menunggu re-render)
    el.value = value === '' ? '' : fmt.format(value);
};
</script>

<template>
    <Input
        type="text"
        inputmode="numeric"
        autocomplete="off"
        :model-value="display"
        :placeholder="placeholder ?? '0'"
        class="text-right font-mono"
        @input="onInput"
    />
</template>
