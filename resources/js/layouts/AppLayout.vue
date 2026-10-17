<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';
import AdminMobileLayout from '@/layouts/app/AdminMobileLayout.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import StaffMobileLayout from '@/layouts/app/StaffMobileLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

// Staff cabang: selalu layout mobile (bottom nav).
// Admin pusat: layout mobile di layar kecil, sidebar desktop di layar lebar.
const page = usePage();
const role = computed(() => page.props.auth.user?.role?.name);
const isMobile = useMediaQuery('(max-width: 767px)');
</script>

<template>
    <StaffMobileLayout v-if="role === 'staff_cabang'">
        <slot />
    </StaffMobileLayout>
    <AdminMobileLayout v-else-if="role === 'admin_pusat' && isMobile">
        <slot />
    </AdminMobileLayout>
    <AppLayout v-else :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
