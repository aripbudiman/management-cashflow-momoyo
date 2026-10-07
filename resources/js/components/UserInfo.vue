<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

const showAvatar = computed(
    () => props.user?.avatar && props.user.avatar !== '',
);

const roleBadge = computed(() => {
    return props.user?.role?.display_name || 'Karyawan';
});

const branchName = computed(() => {
    return props.user?.employee?.current_branch?.branch_name;
});
</script>

<template>
    <Avatar
        class="h-8 w-8 overflow-hidden rounded-lg border border-rose-200/50 bg-rose-50 dark:border-zinc-700 dark:bg-zinc-800"
    >
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback
            class="rounded-lg text-xs font-bold text-rose-600 dark:text-rose-400"
        >
            {{ getInitials(user?.name || 'M') }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-semibold text-gray-900 dark:text-gray-100">{{
            user?.name
        }}</span>
        <div
            class="flex items-center gap-1.5 truncate text-[11px] text-muted-foreground"
        >
            <span class="font-medium text-rose-600 dark:text-rose-400">{{
                roleBadge
            }}</span>
            <span v-if="branchName" class="text-gray-400"
                >• {{ branchName }}</span
            >
        </div>
    </div>
</template>
