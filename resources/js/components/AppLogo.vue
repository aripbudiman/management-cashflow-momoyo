<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const appName = usePage().props.name || 'MOMOYO';
</script>

<template>
    <div class="flex items-center gap-2.5">
        <div
            class="flex size-9 items-center justify-center rounded-xl bg-gradient-to-tr from-rose-600 via-red-500 to-amber-400 text-lg font-black tracking-tighter text-white shadow-md shadow-red-500/25"
        >
            <span>M</span>
        </div>
        <div class="flex flex-col text-left">
            <span
                class="truncate text-base leading-none font-extrabold tracking-tight text-gray-900 dark:text-white"
            >
                MOMOYO
            </span>
            <span
                class="mt-0.5 text-[10px] font-semibold tracking-wider text-rose-500 uppercase dark:text-rose-400"
            >
                Ops & Cashflow
            </span>
        </div>
    </div>
</template>
