<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const appName = computed(() => (page.props.name as string) || 'MOMOYO');
const iconUrl = computed(() => page.props.appIconUrl as string | null);
</script>

<template>
    <div class="flex items-center gap-2.5">
        <img
            v-if="iconUrl"
            :src="iconUrl"
            :alt="appName"
            class="size-9 shrink-0 rounded-xl object-cover shadow-md shadow-red-500/25"
        />
        <div
            v-else
            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-rose-600 via-red-500 to-amber-400 text-lg font-black tracking-tighter text-white uppercase shadow-md shadow-red-500/25"
        >
            <span>{{ appName.charAt(0) }}</span>
        </div>
        <div class="flex min-w-0 flex-col text-left">
            <span
                class="truncate text-base leading-none font-extrabold tracking-tight text-gray-900 uppercase dark:text-white"
            >
                {{ appName }}
            </span>
            <span
                class="mt-0.5 text-[10px] font-semibold tracking-wider text-rose-500 uppercase dark:text-rose-400"
            >
                Ops & Cashflow
            </span>
        </div>
    </div>
</template>
