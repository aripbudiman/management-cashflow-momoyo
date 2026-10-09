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
    () => page.props.auth.user?.role?.name || 'staff_cabang',
);
const isAdminPusat = computed(() => userRole.value === 'admin_pusat');
const isStaffCabang = computed(() => userRole.value === 'staff_cabang');
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

            <!-- GROUP: Operasional Gerai (Staff Cabang & Supervisi Pusat) -->
            <SidebarGroup v-if="isStaffCabang || isAdminPusat" class="py-1">
                <SidebarGroupLabel
                    class="text-[11px] font-bold tracking-wider text-muted-foreground/70 uppercase"
                >
                    Operasional Gerai
                </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/attendance')"
                            tooltip="Absensi Selfie & GPS"
                        >
                            <Link
                                href="/attendance"
                                class="flex items-center gap-2.5"
                            >
                                <Camera class="size-4 text-emerald-500" />
                                <span class="text-sm font-medium"
                                    >Absensi Kru</span
                                >
                            </Link>
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

            <!-- GROUP: Keuangan & Rekonsiliasi (Admin Pusat) -->
            <SidebarGroup v-if="isAdminPusat" class="py-1">
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

            <!-- GROUP: Master & Rolling Cabang (Admin Pusat) -->
            <SidebarGroup v-if="isAdminPusat" class="py-1">
                <SidebarGroupLabel
                    class="text-[11px] font-bold tracking-wider text-muted-foreground/70 uppercase"
                >
                    Master & Rolling Cabang
                </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/employees')"
                            tooltip="Master Karyawan"
                        >
                            <Link
                                href="/employees"
                                class="flex items-center gap-2.5"
                            >
                                <Users class="size-4 text-rose-500" />
                                <span class="text-sm font-medium"
                                    >Master Karyawan</span
                                >
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/branches')"
                            tooltip="Master Cabang"
                        >
                            <Link
                                href="/branches"
                                class="flex items-center gap-2.5"
                            >
                                <MapPin class="size-4 text-emerald-500" />
                                <span class="text-sm font-medium"
                                    >Master Cabang</span
                                >
                            </Link>
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
