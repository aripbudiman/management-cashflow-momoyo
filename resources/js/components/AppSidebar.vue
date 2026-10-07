<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarCheck,
    Camera,
    CheckCircle2,
    DollarSign,
    FileSpreadsheet,
    FileText,
    LayoutGrid,
    MapPin,
    Shield,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const userRole = computed(
    () => page.props.auth.user?.role?.name || 'crew_kasir',
);
const isOwnerOrSuper = computed(() =>
    ['owner', 'superadmin'].includes(userRole.value),
);
const isFinance = computed(() =>
    ['finance_pusat', 'owner', 'superadmin'].includes(userRole.value),
);
const isOutletStaff = computed(() =>
    ['crew_kasir', 'store_manager'].includes(userRole.value),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="border-b border-sidebar-border/60 pb-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-1 px-2 py-3">
            <!-- GROUP: Utama -->
            <SidebarGroup class="py-1">
                <SidebarGroupLabel
                    class="text-[11px] font-bold tracking-wider text-muted-foreground/70 uppercase"
                >
                    Menu Utama
                </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/dashboard')"
                            tooltip="Beranda"
                        >
                            <Link
                                href="/dashboard"
                                class="flex items-center gap-2.5"
                            >
                                <LayoutGrid class="size-4 text-rose-500" />
                                <span class="text-sm font-medium"
                                    >Beranda (Overview)</span
                                >
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- GROUP: Operasional Toko (Kru & Store Manager) -->
            <SidebarGroup v-if="isOutletStaff || isOwnerOrSuper" class="py-1">
                <SidebarGroupLabel
                    class="text-[11px] font-bold tracking-wider text-muted-foreground/70 uppercase"
                >
                    Operasional Gerai
                </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            tooltip="Absensi Selfie & GPS"
                        >
                            <a
                                href="#absensi-section"
                                class="flex items-center gap-2.5"
                            >
                                <Camera class="size-4 text-emerald-500" />
                                <span class="text-sm font-medium"
                                    >Absensi Selfie & GPS</span
                                >
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            tooltip="Closing Kasir & Kas Kecil"
                        >
                            <a
                                href="#closing-section"
                                class="flex items-center gap-2.5"
                            >
                                <DollarSign class="size-4 text-amber-500" />
                                <span class="text-sm font-medium"
                                    >Closing & Kas Kecil</span
                                >
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            tooltip="Izin, Sakit & Cuti"
                        >
                            <a
                                href="#izin-section"
                                class="flex items-center gap-2.5"
                            >
                                <CalendarCheck class="size-4 text-blue-500" />
                                <span class="text-sm font-medium"
                                    >Pengajuan Izin / Cuti</span
                                >
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- GROUP: Keuangan & Rekonsiliasi (Finance & Owner) -->
            <SidebarGroup v-if="isFinance" class="py-1">
                <SidebarGroupLabel
                    class="text-[11px] font-bold tracking-wider text-muted-foreground/70 uppercase"
                >
                    Finance Pusat
                </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            tooltip="Verifikasi Setoran Gerai"
                        >
                            <a
                                href="#reconciliation-section"
                                class="flex items-center gap-2.5"
                            >
                                <CheckCircle2 class="size-4 text-purple-500" />
                                <span class="text-sm font-medium"
                                    >Verifikasi Setoran Bank</span
                                >
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            tooltip="Rekapitulasi Cashflow Multi-Cabang"
                        >
                            <a
                                href="#reports-section"
                                class="flex items-center gap-2.5"
                            >
                                <FileSpreadsheet class="size-4 text-teal-500" />
                                <span class="text-sm font-medium"
                                    >Rekap Cashflow Jaringan</span
                                >
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- GROUP: Manajemen Cabang & Rolling (Owner & Superadmin) -->
            <SidebarGroup v-if="isOwnerOrSuper" class="py-1">
                <SidebarGroupLabel
                    class="text-[11px] font-bold tracking-wider text-muted-foreground/70 uppercase"
                >
                    Master & Rolling Cabang
                </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            tooltip="Master Cabang & GPS"
                        >
                            <a
                                href="#branches-section"
                                class="flex items-center gap-2.5"
                            >
                                <MapPin class="size-4 text-rose-500" />
                                <span class="text-sm font-medium"
                                    >Cabang & Radius GPS</span
                                >
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton as-child tooltip="Rolling Karyawan">
                            <a
                                href="#rolling-section"
                                class="flex items-center gap-2.5"
                            >
                                <Users class="size-4 text-indigo-500" />
                                <span class="text-sm font-medium"
                                    >Rolling Karyawan</span
                                >
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border/60 pt-2">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
