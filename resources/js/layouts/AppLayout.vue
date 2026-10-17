<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import StaffMobileLayout from '@/layouts/app/StaffMobileLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

// Staff cabang memakai layout mobile (bottom nav); admin & lainnya memakai sidebar desktop
const page = usePage();
const isStaff = computed(() => page.props.auth.user?.role?.name === 'staff_cabang');
</script>

<template>
    <StaffMobileLayout v-if="isStaff">
        <slot />
    </StaffMobileLayout>
    <AppLayout v-else :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
