<script setup lang="ts">
import StaffBottomNav from '@/components/StaffBottomNav.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="min-h-svh bg-gray-50 dark:bg-zinc-950">
        <main class="mx-auto w-full max-w-3xl pb-28">
            <slot />
        </main>
        <StaffBottomNav />
        <Toaster />
    </div>
</template>
