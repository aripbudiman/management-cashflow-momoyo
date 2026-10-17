<script setup lang="ts">
import AdminBottomNav from '@/components/AdminBottomNav.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="min-h-svh bg-gray-50 dark:bg-zinc-950">
        <main class="mx-auto w-full pb-28">
            <slot />
        </main>
        <AdminBottomNav />
        <Toaster />
    </div>
</template>
