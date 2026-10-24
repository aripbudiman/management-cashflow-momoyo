<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarCheck,
    Camera,
    CheckCircle2,
    CalendarDays,
    ClipboardList,
    Receipt,
    Clock,
    DollarSign,
    FileSpreadsheet,
    FileText,
    HardHat,
    LayoutGrid,
    MapPin,
    Package,
    Ruler,
    PackageCheck,
    Boxes,
    ArrowLeftRight,
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
const isStaffCabang = computed(() =>
    ['staff_cabang', 'kepala_toko'].includes(userRole.value),
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

                    <SidebarMenuItem v-if="isStaffCabang">
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/cashflows')"
                            tooltip="Input Cashflow Harian"
                        >
                            <Link
                                href="/cashflows"
                                class="flex items-center gap-2.5"
                            >
                                <DollarSign class="size-4 text-amber-500" />
                                <span class="text-sm font-medium"
                                    >Input Cashflow Harian</span
                                >
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/stock-reports')"
                            tooltip="Input Stok Harian"
                        >
                            <Link href="/stock-reports" class="flex items-center gap-2.5">
                                <PackageCheck class="size-4 text-sky-500" />
                                <span class="text-sm font-medium">{{ isAdminPusat ? 'Input Stok Cabang' : 'Input Stok Harian' }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/stock-monitor')"
                            tooltip="Pantauan Stok"
                        >
                            <Link href="/stock-monitor" class="flex items-center gap-2.5">
                                <Boxes class="size-4 text-indigo-500" />
                                <span class="text-sm font-medium">Pantauan Stok</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/stock-transfers')"
                            tooltip="Transfer Antar Cabang"
                        >
                            <Link href="/stock-transfers" class="flex items-center gap-2.5">
                                <ArrowLeftRight class="size-4 text-cyan-500" />
                                <span class="text-sm font-medium">Transfer Stok</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/leaves')"
                            tooltip="Izin, Sakit & Cuti"
                        >
                            <Link
                                href="/leaves"
                                class="flex items-center gap-2.5"
                            >
                                <CalendarCheck class="size-4 text-blue-500" />
                                <span class="text-sm font-medium"
                                    >Izin, Sakit & Cuti</span
                                >
                            </Link>
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
                            :is-active="isCurrentUrl('/cashflow-verifications')"
                            tooltip="Verifikasi Setoran Gerai"
                        >
                            <Link
                                href="/cashflow-verifications"
                                class="flex items-center gap-2.5"
                            >
                                <CheckCircle2 class="size-4 text-purple-500" />
                                <span class="text-sm font-medium"
                                    >Verifikasi Setoran</span
                                >
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/cashflow-reports')"
                            tooltip="Laporan Cashflow Harian & Bulanan"
                        >
                            <Link
                                href="/cashflow-reports"
                                class="flex items-center gap-2.5"
                            >
                                <FileSpreadsheet class="size-4 text-teal-500" />
                                <span class="text-sm font-medium"
                                    >Laporan Cashflow</span
                                >
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/expense-reports')"
                            tooltip="Laporan Pengeluaran Kas"
                        >
                            <Link
                                href="/expense-reports"
                                class="flex items-center gap-2.5"
                            >
                                <Receipt class="size-4 text-orange-500" />
                                <span class="text-sm font-medium"
                                    >Laporan Pengeluaran</span
                                >
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/stock-verifications')"
                            tooltip="Verifikasi Stok Harian"
                        >
                            <Link href="/stock-verifications" class="flex items-center gap-2.5">
                                <PackageCheck class="size-4 text-sky-500" />
                                <span class="text-sm font-medium">Verifikasi Stok</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- GROUP: Proyek Lapangan (semua role) -->
            <SidebarGroup class="py-1">
                <SidebarGroupLabel
                    class="text-[11px] font-bold tracking-wider text-muted-foreground/70 uppercase"
                >
                    Proyek
                </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/projects')"
                            tooltip="Monitoring Pekerjaan Lapangan"
                        >
                            <Link
                                href="/projects"
                                class="flex items-center gap-2.5"
                            >
                                <HardHat class="size-4 text-yellow-500" />
                                <span class="text-sm font-medium"
                                    >Proyek Lapangan</span
                                >
                            </Link>
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

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/shifts')"
                            tooltip="Master Shift"
                        >
                            <Link href="/shifts" class="flex items-center gap-2.5">
                                <Clock class="size-4 text-amber-500" />
                                <span class="text-sm font-medium">Master Shift</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/schedules')"
                            tooltip="Jadwal Shift & Libur"
                        >
                            <Link href="/schedules" class="flex items-center gap-2.5">
                                <CalendarDays class="size-4 text-blue-500" />
                                <span class="text-sm font-medium">Jadwal Shift & Libur</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/attendance-recap')"
                            tooltip="Rekap Kehadiran"
                        >
                            <Link href="/attendance-recap" class="flex items-center gap-2.5">
                                <ClipboardList class="size-4 text-purple-500" />
                                <span class="text-sm font-medium">Rekap Kehadiran</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/items')"
                            tooltip="Master Barang"
                        >
                            <Link href="/items" class="flex items-center gap-2.5">
                                <Package class="size-4 text-orange-500" />
                                <span class="text-sm font-medium">Master Barang</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/units')"
                            tooltip="Master Satuan"
                        >
                            <Link href="/units" class="flex items-center gap-2.5">
                                <Ruler class="size-4 text-pink-500" />
                                <span class="text-sm font-medium">Master Satuan</span>
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
