<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { adminMenuGroups } from '@/lib/adminMenus';

const open = defineModel<boolean>('open', { default: false });
const page = usePage();

const go = (href: string) => {
    open.value = false;
    router.visit(href);
};
const badge = (key?: string) => {
    if (!key) return 0;
    const m = (page.props as { metrics?: Record<string, number> }).metrics;
    return m?.[key] ?? 0;
};
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="bottom" class="max-h-[85vh] overflow-y-auto rounded-t-3xl px-4 pb-8">
            <SheetHeader class="text-left">
                <SheetTitle>Semua Menu</SheetTitle>
                <SheetDescription class="sr-only">Pintasan seluruh menu Admin Pusat</SheetDescription>
            </SheetHeader>
            <div v-for="g in adminMenuGroups" :key="g.label" class="mt-3">
                <div class="mb-2 text-[11px] font-black tracking-wider text-gray-400 uppercase">{{ g.label }}</div>
                <div class="grid grid-cols-2 gap-2.5">
                    <button
                        v-for="m in g.items"
                        :key="m.href"
                        type="button"
                        class="relative flex items-center gap-2.5 rounded-2xl border border-gray-100 bg-white p-3 text-left active:scale-[0.98] dark:border-zinc-800 dark:bg-zinc-900"
                        @click="go(m.href)"
                    >
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl" :class="m.tone"><component :is="m.icon" class="size-5" /></span>
                        <span class="min-w-0 text-[13px] leading-tight font-bold">{{ m.title }}</span>
                        <span v-if="badge(m.badgeKey)" class="absolute top-1.5 right-1.5 flex min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">{{ badge(m.badgeKey) }}</span>
                    </button>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
