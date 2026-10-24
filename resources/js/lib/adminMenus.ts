import {
    Boxes,
    CalendarCheck,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    Clock,
    FileSpreadsheet,
    FileText,
    HardHat,
    MapPin,
    Package,
    PackageCheck,
    Receipt,
    Ruler,
    ScanFace,
    Settings,
    Truck,
    Users,
} from '@lucide/vue';
import type { Component } from 'vue';

export interface AdminMenuItem {
    href: string;
    title: string;
    desc: string;
    icon: Component;
    tone: string;
    badgeKey?: 'pending_cashflows_count' | 'pending_leaves_count' | 'pending_stock_reports_count';
}

export interface AdminMenuGroup {
    label: string;
    items: AdminMenuItem[];
}

// Daftar menu Admin Pusat (dipakai di Beranda mobile & sheet "Menu")
export const adminMenuGroups: AdminMenuGroup[] = [
    {
        label: 'Operasional',
        items: [
            { href: '/attendance', title: 'Absensi', desc: 'Absen & monitoring', icon: ScanFace, tone: 'bg-emerald-50 text-emerald-600' },
            { href: '/attendance-recap', title: 'Rekap Kehadiran', desc: 'Laporan per karyawan', icon: ClipboardList, tone: 'bg-purple-50 text-purple-600' },
            { href: '/schedules', title: 'Jadwal Shift', desc: 'Shift & hari libur', icon: CalendarDays, tone: 'bg-blue-50 text-blue-600' },
            { href: '/leaves', title: 'Izin, Sakit & Cuti', desc: 'Persetujuan pengajuan', icon: CalendarCheck, tone: 'bg-sky-50 text-sky-600', badgeKey: 'pending_leaves_count' },
        ],
    },
    {
        label: 'Finance',
        items: [
            { href: '/cashflow-verifications', title: 'Verifikasi Setoran', desc: 'Cek bukti transfer', icon: CheckCircle2, tone: 'bg-purple-50 text-purple-600', badgeKey: 'pending_cashflows_count' },
            { href: '/cashflow-reports', title: 'Laporan Cashflow', desc: 'Harian & bulanan', icon: FileSpreadsheet, tone: 'bg-teal-50 text-teal-600' },
            { href: '/expense-reports', title: 'Laporan Pengeluaran', desc: 'Buku kas pengeluaran', icon: Receipt, tone: 'bg-orange-50 text-orange-600' },
        ],
    },
    {
        label: 'Proyek',
        items: [
            { href: '/projects', title: 'Proyek Lapangan', desc: 'RAB & progress renovasi', icon: HardHat, tone: 'bg-yellow-50 text-yellow-600' },
            { href: '/settings/placement-template', title: 'Template Surat', desc: 'Surat penempatan kerja', icon: FileText, tone: 'bg-indigo-50 text-indigo-600' },
        ],
    },
    {
        label: 'Stok',
        items: [
            { href: '/stock-reports', title: 'Input Stok Cabang', desc: 'Barang masuk & keluar', icon: PackageCheck, tone: 'bg-emerald-50 text-emerald-600' },
            { href: '/stock-verifications', title: 'Verifikasi Stok', desc: 'Laporan stok harian', icon: Package, tone: 'bg-amber-50 text-amber-600', badgeKey: 'pending_stock_reports_count' },
            { href: '/stock-monitor', title: 'Pantau Stok', desc: 'Stok semua cabang', icon: Boxes, tone: 'bg-sky-50 text-sky-600' },
            { href: '/stock-transfers', title: 'Transfer Stok', desc: 'Antar cabang', icon: Truck, tone: 'bg-orange-50 text-orange-600' },
        ],
    },
    {
        label: 'Master',
        items: [
            { href: '/employees', title: 'Master Karyawan', desc: 'Data & akun login', icon: Users, tone: 'bg-rose-50 text-rose-600' },
            { href: '/branches', title: 'Master Cabang', desc: 'Cabang & geofencing', icon: MapPin, tone: 'bg-emerald-50 text-emerald-600' },
            { href: '/shifts', title: 'Master Shift', desc: 'Jam kerja shift', icon: Clock, tone: 'bg-amber-50 text-amber-600' },
            { href: '/items', title: 'Master Barang', desc: 'Barang & harga', icon: Package, tone: 'bg-orange-50 text-orange-600' },
            { href: '/units', title: 'Master Satuan', desc: 'Satuan & konversi', icon: Ruler, tone: 'bg-pink-50 text-pink-600' },
            { href: '/settings/application', title: 'Pengaturan Aplikasi', desc: 'Nama & icon aplikasi', icon: Settings, tone: 'bg-gray-100 text-gray-600' },
        ],
    },
];
