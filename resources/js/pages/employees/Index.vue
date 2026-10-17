<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Edit3,
    KeyRound,
    Lock,
    Mail,
    MapPin,
    Phone,
    Plus,
    RefreshCw,
    Search,
    Shield,
    Trash2,
    UserCheck,
    UserPlus,
    Users,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useInitials } from '@/composables/useInitials';

// Props dari EmployeeController
const props = defineProps<{
    employees: {
        data: Array<{
            id: number;
            user_id: number | null;
            nik: string;
            full_name: string;
            phone_number: string | null;
            attendance_type?: 'radius' | 'flexible';
            birth_place: string | null;
            birth_date: string | null;
            address: string | null;
            bank_name: string | null;
            bank_account_number: string | null;
            bank_account_name: string | null;
            current_branch_id: number;
            status: 'active' | 'inactive' | 'probation';
            created_at: string;
            user: {
                id: number;
                name: string;
                username: string | null;
                email: string;
                role_id: number | null;
                is_active: boolean;
                role?: {
                    id: number;
                    name: string;
                    display_name: string;
                } | null;
            } | null;
            current_branch?: {
                id: number;
                branch_code: string;
                branch_name: string;
            } | null;
        }>;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    branches: Array<{
        id: number;
        branch_code: string;
        branch_name: string;
    }>;
    roles: Array<{
        id: number;
        name: string;
        display_name: string;
        description?: string | null;
    }>;
    filters: {
        search?: string;
        branch_id?: string;
        status?: string;
        role_id?: string;
    };
    stats: {
        total: number;
        active: number;
        probation: number;
        inactive: number;
        has_login: number;
    };
}>();

const { getInitials } = useInitials();

// Search & Filter State
const search = ref(props.filters.search || '');
const branchFilter = ref(props.filters.branch_id || '');
const statusFilter = ref(props.filters.status || '');
const roleFilter = ref(props.filters.role_id || '');

const applyFilters = () => {
    router.get(
        '/employees',
        {
            search: search.value || undefined,
            branch_id: branchFilter.value || undefined,
            status: statusFilter.value || undefined,
            role_id: roleFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const resetFilters = () => {
    search.value = '';
    branchFilter.value = '';
    statusFilter.value = '';
    roleFilter.value = '';
    router.get('/employees');
};

// Modal State: Tambah / Edit Karyawan
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingEmployeeId = ref<number | null>(null);

const form = useForm({
    nik: '',
    full_name: '',
    phone_number: '',
    birth_place: '',
    birth_date: '',
    address: '',
    bank_name: '',
    bank_account_number: '',
    bank_account_name: '',
    current_branch_id: props.branches[0]?.id || '',
    status: 'active',
    attendance_type: 'radius',
    // Login User Info
    email: '',
    username: '',
    password: '',
    role_id:
        props.roles.find((r) => r.name === 'staff_cabang')?.id ||
        props.roles[0]?.id ||
        '',
});

const bankOptions = ['Bank Mandiri', 'BCA', 'BRI', 'BNI', 'BSI', 'CIMB Niaga', 'Bank Permata', 'Bank Danamon', 'BTN', 'Jago', 'SeaBank'];

// Saat membuat karyawan baru dengan role Admin Pusat, tipe absensi default Fleksibel
watch(
    () => form.role_id,
    (id) => {
        if (isEditing.value) return;
        const role = props.roles.find((r) => r.id === id);
        form.attendance_type = role?.name === 'admin_pusat' ? 'flexible' : 'radius';
    },
);

// Auto-generate suggestion email & username saat mengetik nama
const handleNameInput = () => {
    if (!isEditing.value) {
        const cleanName = form.full_name
            .toLowerCase()
            .replace(/[^a-z0-9]/g, '');
        if (cleanName) {
            form.username = cleanName;
            form.email = `${cleanName}@momoyo.id`;
        }
    }
};

const openCreateModal = () => {
    isEditing.value = false;
    editingEmployeeId.value = null;
    form.reset();
    form.clearErrors();
    form.status = 'active';
    form.password = 'password';
    if (props.branches.length) {
        form.current_branch_id = props.branches[0].id;
    }
    const defaultRole =
        props.roles.find((r) => r.name === 'staff_cabang') || props.roles[0];
    if (defaultRole) {
        form.role_id = defaultRole.id;
    }
    isModalOpen.value = true;
};

const openEditModal = (emp: (typeof props.employees.data)[number]) => {
    isEditing.value = true;
    editingEmployeeId.value = emp.id;
    form.clearErrors();

    form.nik = emp.nik;
    form.full_name = emp.full_name;
    form.phone_number = emp.phone_number || '';
    form.birth_place = emp.birth_place || '';
    form.birth_date = emp.birth_date ? emp.birth_date.slice(0, 10) : '';
    form.address = emp.address || '';
    form.bank_name = emp.bank_name || '';
    form.bank_account_number = emp.bank_account_number || '';
    form.bank_account_name = emp.bank_account_name || '';
    form.current_branch_id = emp.current_branch_id;
    form.status = emp.status;
    form.attendance_type = emp.attendance_type || 'radius';

    form.email = emp.user?.email || '';
    form.username = emp.user?.username || '';
    form.password = ''; // Kosong jika tidak ingin ganti password
    form.role_id = emp.user?.role_id || props.roles[0]?.id || '';

    isModalOpen.value = true;
};

const submitForm = () => {
    if (isEditing.value && editingEmployeeId.value) {
        form.put(`/employees/${editingEmployeeId.value}`, {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            },
        });
    } else {
        form.post('/employees', {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            },
        });
    }
};

const toggleDeactivate = (emp: (typeof props.employees.data)[number]) => {
    if (
        confirm(
            `Yakin ingin menonaktifkan karyawan ${emp.full_name}? Akses login juga akan dimatikan.`,
        )
    ) {
        router.delete(`/employees/${emp.id}`);
    }
};

// Helper badge role
const getRoleBadgeClass = (roleName?: string) => {
    switch (roleName) {
        case 'admin_pusat':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border-purple-300';
        case 'staff_cabang':
        default:
            return 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-300';
    }
};

// Helper status badge
const getStatusBadge = (status: string) => {
    switch (status) {
        case 'active':
            return {
                label: 'Aktif',
                class: 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-400',
            };
        case 'probation':
            return {
                label: 'Probation',
                class: 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/40 dark:text-amber-400',
            };
        case 'inactive':
        default:
            return {
                label: 'Nonaktif',
                class: 'bg-gray-100 text-gray-600 border-gray-300 dark:bg-zinc-800 dark:text-gray-400',
            };
    }
};
</script>

<template>
    <Head title="Master Karyawan - Momoyo Ops" />

    <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- HEADER BANNER -->
        <div
            class="flex flex-col gap-4 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="space-y-1">
                <div
                    class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-bold text-rose-600 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-400"
                >
                    <Shield class="size-3.5" />
                    <span>Akses Khusus Admin Pusat</span>
                </div>
                <h1
                    class="text-2xl font-black tracking-tight text-gray-900 sm:text-3xl dark:text-white"
                >
                    Master Karyawan
                </h1>
                <p
                    class="max-w-2xl text-xs text-gray-500 sm:text-sm dark:text-gray-400"
                >
                    Pusat penambahan dan pengelolaan seluruh kru gerai Momoyo.
                    Pembuatan profil karyawan otomatis menerbitkan akun login
                    sistem sesuai peran (RBAC).
                </p>
            </div>

            <!-- ACTION BUTTON: TAMBAH KARYAWAN -->
            <Button
                @click="openCreateModal"
                class="flex h-11 shrink-0 items-center gap-2 self-start rounded-2xl bg-gradient-to-r from-red-600 via-rose-600 to-rose-700 px-5 text-sm font-bold text-white shadow-md shadow-rose-600/25 hover:from-red-700 hover:to-rose-800 md:self-auto"
            >
                <UserPlus class="size-4" />
                <span>Tambah Karyawan & Login</span>
            </Button>
        </div>

        <!-- 4 STATS CARDS -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <Card class="border-gray-200 shadow-sm dark:border-zinc-800">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <span
                            class="block text-xs font-bold tracking-wider text-gray-400 uppercase"
                            >Total Karyawan</span
                        >
                        <span
                            class="text-2xl font-black text-gray-900 dark:text-white"
                            >{{ stats.total }}</span
                        >
                    </div>
                    <div
                        class="flex size-10 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950/50"
                    >
                        <Users class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-gray-200 shadow-sm dark:border-zinc-800">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <span
                            class="block text-xs font-bold tracking-wider text-gray-400 uppercase"
                            >Karyawan Aktif</span
                        >
                        <span
                            class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                            >{{ stats.active }}</span
                        >
                    </div>
                    <div
                        class="flex size-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50"
                    >
                        <UserCheck class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-gray-200 shadow-sm dark:border-zinc-800">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <span
                            class="block text-xs font-bold tracking-wider text-gray-400 uppercase"
                            >Masa Probation</span
                        >
                        <span
                            class="text-2xl font-black text-amber-600 dark:text-amber-400"
                            >{{ stats.probation }}</span
                        >
                    </div>
                    <div
                        class="flex size-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-950/50"
                    >
                        <Clock class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-gray-200 shadow-sm dark:border-zinc-800">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <span
                            class="block text-xs font-bold tracking-wider text-gray-400 uppercase"
                            >Akun Login Aktif</span
                        >
                        <span
                            class="text-2xl font-black text-blue-600 dark:text-blue-400"
                            >{{ stats.has_login }}</span
                        >
                    </div>
                    <div
                        class="flex size-10 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-950/50"
                    >
                        <KeyRound class="size-5" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- FILTER & SEARCH BAR -->
        <Card class="border-gray-200 shadow-sm dark:border-zinc-800">
            <CardContent class="p-4">
                <div
                    class="grid grid-cols-1 items-center gap-3 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <!-- Search Input -->
                    <div class="relative lg:col-span-2">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400"
                        />
                        <Input
                            v-model="search"
                            @keydown.enter="applyFilters"
                            placeholder="Cari Nama, NIK, Email, atau No. HP..."
                            class="h-10 rounded-xl pl-10 text-xs"
                        />
                    </div>

                    <!-- Filter Cabang -->
                    <div>
                        <select
                            v-model="branchFilter"
                            @change="applyFilters"
                            class="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-rose-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300"
                        >
                            <option value="">Semua Cabang Gerai</option>
                            <option
                                v-for="b in branches"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.branch_name }}
                            </option>
                        </select>
                    </div>

                    <!-- Filter Status -->
                    <div>
                        <select
                            v-model="statusFilter"
                            @change="applyFilters"
                            class="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-rose-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300"
                        >
                            <option value="">Semua Status</option>
                            <option value="active">Aktif</option>
                            <option value="probation">Probation</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>

                    <!-- Filter Role -->
                    <div class="flex items-center gap-2">
                        <select
                            v-model="roleFilter"
                            @change="applyFilters"
                            class="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-rose-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300"
                        >
                            <option value="">Semua Role</option>
                            <option
                                v-for="r in roles"
                                :key="r.id"
                                :value="r.id"
                            >
                                {{ r.display_name }}
                            </option>
                        </select>

                        <Button
                            variant="outline"
                            size="icon"
                            @click="resetFilters"
                            title="Reset Filter"
                            class="size-10 shrink-0 rounded-xl text-gray-500"
                        >
                            <RefreshCw class="size-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- EMPLOYEES DATA TABLE -->
        <Card
            class="overflow-hidden border-gray-200 shadow-md dark:border-zinc-800"
        >
            <CardHeader
                class="flex flex-row items-center justify-between border-b border-gray-100 p-4 dark:border-zinc-800"
            >
                <div>
                    <CardTitle class="text-base font-bold"
                        >Daftar Karyawan Terdaftar</CardTitle
                    >
                    <CardDescription class="text-xs">
                        Total {{ employees.total }} karyawan tercatat di
                        jaringan gerai Momoyo
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="border-b border-gray-200 bg-gray-50 text-gray-500 dark:border-zinc-800 dark:bg-zinc-900/60"
                        >
                            <tr>
                                <th class="px-4 py-3 font-bold">Karyawan</th>
                                <th class="px-4 py-3 font-bold">
                                    Akun Login Sistem
                                </th>
                                <th class="px-4 py-3 font-bold">
                                    Role Akses (RBAC)
                                </th>
                                <th class="px-4 py-3 font-bold">
                                    Cabang Penempatan
                                </th>
                                <th class="px-4 py-3 font-bold">No. Kontak</th>
                                <th class="px-4 py-3 font-bold">Status</th>
                                <th class="px-4 py-3 text-center font-bold">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-zinc-800"
                        >
                            <tr
                                v-for="emp in employees.data"
                                :key="emp.id"
                                class="transition-colors hover:bg-gray-50/50 dark:hover:bg-zinc-800/30"
                            >
                                <!-- Karyawan -->
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-rose-500 to-amber-400 text-xs font-bold text-white shadow-sm"
                                        >
                                            {{ getInitials(emp.full_name) }}
                                        </div>
                                        <div>
                                            <span
                                                class="block text-sm font-bold text-gray-900 dark:text-white"
                                            >
                                                {{ emp.full_name }}
                                            </span>
                                            <span
                                                class="font-mono text-[11px] font-semibold text-rose-600 dark:text-rose-400"
                                            >
                                                {{ emp.nik }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Akun Login -->
                                <td class="px-4 py-3.5">
                                    <div v-if="emp.user" class="space-y-0.5">
                                        <div
                                            class="flex items-center gap-1.5 font-semibold text-gray-800 dark:text-gray-200"
                                        >
                                            <Mail
                                                class="size-3.5 text-gray-400"
                                            />
                                            <span>{{ emp.user.email }}</span>
                                        </div>
                                        <span
                                            class="block font-mono text-[11px] text-gray-400"
                                        >
                                            @{{ emp.user.username || '-' }}
                                        </span>
                                    </div>
                                    <div
                                        v-else
                                        class="flex items-center gap-1 text-[11px] font-semibold text-amber-600"
                                    >
                                        <AlertCircle class="size-3.5" />
                                        <span>Belum ada akun login</span>
                                    </div>
                                </td>

                                <!-- Role Akses -->
                                <td class="px-4 py-3.5">
                                    <Badge
                                        variant="outline"
                                        :class="[
                                            'border px-2 py-0.5 text-[10px] font-bold',
                                            getRoleBadgeClass(
                                                emp.user?.role?.name,
                                            ),
                                        ]"
                                    >
                                        {{
                                            emp.user?.role?.display_name ||
                                            'Tanpa Role'
                                        }}
                                    </Badge>
                                </td>

                                <!-- Cabang -->
                                <td class="px-4 py-3.5">
                                    <div
                                        class="flex items-center gap-1.5 font-medium text-gray-800 dark:text-gray-200"
                                    >
                                        <Building2
                                            class="size-3.5 shrink-0 text-rose-500"
                                        />
                                        <span>{{
                                            emp.current_branch?.branch_name ||
                                            'Belum Ditempatkan'
                                        }}</span>
                                    </div>
                                    <span
                                        class="ml-5 block font-mono text-[10px] text-gray-400"
                                    >
                                        {{ emp.current_branch?.branch_code }}
                                    </span>
                                </td>

                                <!-- No Kontak -->
                                <td
                                    class="px-4 py-3.5 text-gray-600 dark:text-gray-400"
                                >
                                    <a
                                        v-if="emp.phone_number"
                                        :href="`https://wa.me/${emp.phone_number.replace(/^0/, '62')}`"
                                        target="_blank"
                                        class="flex items-center gap-1.5 transition-colors hover:text-emerald-600"
                                    >
                                        <Phone
                                            class="size-3.5 text-emerald-500"
                                        />
                                        <span>{{ emp.phone_number }}</span>
                                    </a>
                                    <span v-else class="text-gray-400">-</span>
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3.5">
                                    <Badge
                                        variant="outline"
                                        :class="[
                                            'border text-[10px] font-bold',
                                            getStatusBadge(emp.status).class,
                                        ]"
                                    >
                                        {{ getStatusBadge(emp.status).label }}
                                    </Badge>
                                </td>

                                <!-- Aksi -->
                                <td class="px-4 py-3.5 text-center">
                                    <div class="inline-flex items-center gap-1">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="openEditModal(emp)"
                                            class="h-8 rounded-xl text-xs font-semibold text-gray-700 hover:border-rose-300 hover:text-rose-600 dark:text-gray-300"
                                            title="Edit Karyawan & Akun Login"
                                        >
                                            <Edit3 class="mr-1 size-3.5" /> Edit
                                        </Button>

                                        <Button
                                            v-if="emp.status !== 'inactive'"
                                            size="sm"
                                            variant="ghost"
                                            @click="toggleDeactivate(emp)"
                                            class="h-8 rounded-xl text-xs font-semibold text-gray-400 hover:bg-rose-50 hover:text-rose-600"
                                            title="Nonaktifkan Karyawan"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!employees.data.length">
                                <td
                                    colspan="7"
                                    class="py-12 text-center text-gray-400"
                                >
                                    <Users
                                        class="mx-auto mb-2 size-10 text-gray-300"
                                    />
                                    <p class="text-sm font-bold">
                                        Belum ada data karyawan terdaftar
                                    </p>
                                    <p class="text-xs">
                                        Klik tombol "Tambah Karyawan Baru" di
                                        atas untuk mendaftarkan karyawan dan
                                        akun login perdana.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION LINKS -->
                <div
                    v-if="employees.last_page > 1"
                    class="flex items-center justify-between border-t border-gray-100 p-4 text-xs dark:border-zinc-800"
                >
                    <span class="text-gray-500">
                        Menampilkan halaman
                        <strong>{{ employees.current_page }}</strong> dari
                        <strong>{{ employees.last_page }}</strong>
                    </span>
                    <div class="flex items-center gap-1">
                        <template v-for="(link, i) in employees.links" :key="i">
                            <Button
                                v-if="link.url"
                                variant="outline"
                                size="sm"
                                :class="[
                                    'h-8 rounded-lg px-3 text-xs',
                                    link.active
                                        ? 'border-rose-600 bg-rose-600 font-bold text-white'
                                        : '',
                                ]"
                                @click="router.get(link.url)"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- MODAL DIALOG: TAMBAH / EDIT KARYAWAN & LOGIN -->
    <Dialog :open="isModalOpen" @update:open="isModalOpen = $event">
        <DialogContent
            class="max-h-[90vh] max-w-2xl overflow-y-auto rounded-3xl p-6"
        >
            <DialogHeader
                class="border-b border-gray-100 pb-3 dark:border-zinc-800"
            >
                <DialogTitle
                    class="flex items-center gap-2 text-lg font-black text-gray-900 dark:text-white"
                >
                    <UserPlus v-if="!isEditing" class="size-5 text-rose-600" />
                    <Edit3 v-else class="size-5 text-rose-600" />
                    <span>{{
                        isEditing
                            ? 'Edit Data Karyawan & Akun Login'
                            : 'Tambah Karyawan Baru & Terbitkan Akun Login'
                    }}</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-gray-500">
                    Akses khusus Admin Pusat Momoyo. Akun login akan dibuat
                    bersamaan dengan profil karyawan.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitForm" class="space-y-6 pt-2">
                <!-- SECTION 1: PROFIL KARYAWAN FISIK -->
                <div class="space-y-4">
                    <div
                        class="flex items-center gap-2 border-b border-gray-100 pb-1 text-xs font-bold tracking-wider text-gray-500 uppercase dark:border-zinc-800"
                    >
                        <Users class="size-3.5 text-rose-500" />
                        <span>1. Informasi Identitas Karyawan</span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- NIK -->
                        <div class="space-y-1.5">
                            <Label for="nik" class="text-xs font-semibold">
                                Nomor Induk Karyawan (NIK)
                                <span class="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="nik"
                                v-model="form.nik"
                                placeholder="Contoh: EMP-MMY-004"
                                required
                                class="h-10 rounded-xl font-mono text-xs uppercase"
                            />
                            <InputError :message="form.errors.nik" />
                        </div>

                        <!-- Nama Lengkap -->
                        <div class="space-y-1.5">
                            <Label
                                for="full_name"
                                class="text-xs font-semibold"
                            >
                                Nama Lengkap Karyawan
                                <span class="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="full_name"
                                v-model="form.full_name"
                                @input="handleNameInput"
                                placeholder="Nama lengkap sesuai KTP"
                                required
                                class="h-10 rounded-xl text-xs"
                            />
                            <InputError :message="form.errors.full_name" />
                        </div>

                        <!-- No WhatsApp / Telepon -->
                        <div class="space-y-1.5">
                            <Label
                                for="phone_number"
                                class="text-xs font-semibold"
                            >
                                Nomor WhatsApp / Telepon
                            </Label>
                            <Input
                                id="phone_number"
                                v-model="form.phone_number"
                                placeholder="081234567890"
                                class="h-10 rounded-xl text-xs"
                            />
                            <InputError :message="form.errors.phone_number" />
                        </div>

                        <!-- Cabang Penempatan -->
                        <div class="space-y-1.5">
                            <Label
                                for="current_branch_id"
                                class="text-xs font-semibold"
                            >
                                Cabang Gerai Penempatan
                                <span class="text-rose-500">*</span>
                            </Label>
                            <select
                                id="current_branch_id"
                                v-model="form.current_branch_id"
                                required
                                class="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-rose-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300"
                            >
                                <option value="" disabled>
                                    {{
                                        branches.length
                                            ? '-- Pilih Cabang Gerai Momoyo --'
                                            : '-- Belum Ada Cabang Terdaftar --'
                                    }}
                                </option>
                                <option
                                    v-for="b in branches"
                                    :key="b.id"
                                    :value="b.id"
                                >
                                    {{ b.branch_name }} ({{ b.branch_code }})
                                </option>
                            </select>
                            <p
                                v-if="!branches.length"
                                class="mt-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400"
                            >
                                ⚠️ Belum ada cabang gerai yang terdaftar. Harap
                                tambahkan cabang di menu Master Cabang terlebih
                                dahulu.
                            </p>
                            <p
                                v-else
                                class="mt-0.5 text-[10px] text-muted-foreground"
                            >
                                Pilihan cabang terisi dinamis dari Master Cabang
                                & Geofencing.
                            </p>
                            <InputError
                                :message="form.errors.current_branch_id"
                            />
                        </div>

                        <!-- Status Karyawan -->
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="status" class="text-xs font-semibold">
                                Status Kepegawaian
                                <span class="text-rose-500">*</span>
                            </Label>
                            <select
                                id="status"
                                v-model="form.status"
                                required
                                class="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-rose-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300"
                            >
                                <option value="active">
                                    Aktif (Karyawan Tetap / Kontrak Aktif)
                                </option>
                                <option value="probation">
                                    Probation (Masa Percobaan 3 Bulan)
                                </option>
                                <option value="inactive">
                                    Nonaktif (Resign / Tidak Aktif)
                                </option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>

                        <!-- Tipe Absensi -->
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="attendance_type" class="text-xs font-semibold">
                                Tipe Absensi
                                <span class="text-rose-500">*</span>
                            </Label>
                            <select
                                id="attendance_type"
                                v-model="form.attendance_type"
                                class="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-rose-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300"
                            >
                                <option value="radius">Radius Cabang (wajib absen di area gerai)</option>
                                <option value="flexible">Fleksibel (bisa absen di mana saja)</option>
                            </select>
                            <p class="text-[10px] text-muted-foreground">
                                Fleksibel cocok untuk Admin Pusat atau karyawan lapangan. Titik lokasi (lat/long) tetap dicatat dan bisa dilihat di peta.
                            </p>
                            <InputError :message="form.errors.attendance_type" />
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: DATA PRIBADI & REKENING GAJI -->
                <div class="space-y-4">
                    <div
                        class="flex items-center gap-2 border-b border-gray-100 pb-1 text-xs font-bold tracking-wider text-gray-500 uppercase dark:border-zinc-800"
                    >
                        <Wallet class="size-3.5 text-emerald-500" />
                        <span>2. Data Pribadi & Rekening Gaji</span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="birth_place" class="text-xs font-semibold">Tempat Lahir</Label>
                            <Input id="birth_place" v-model="form.birth_place" placeholder="Contoh: Bekasi" class="h-10 rounded-xl text-xs" />
                            <InputError :message="form.errors.birth_place" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="birth_date" class="text-xs font-semibold">Tanggal Lahir</Label>
                            <Input id="birth_date" v-model="form.birth_date" type="date" class="h-10 rounded-xl text-xs" />
                            <InputError :message="form.errors.birth_date" />
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="address" class="text-xs font-semibold">Alamat Lengkap</Label>
                            <textarea
                                id="address"
                                v-model="form.address"
                                rows="2"
                                placeholder="Jl. Sadariyah RT/RW 004/017, ..."
                                class="w-full rounded-xl border border-gray-300 bg-white p-3 text-xs focus:ring-2 focus:ring-rose-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900"
                            />
                            <InputError :message="form.errors.address" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="bank_name" class="text-xs font-semibold">Nama Bank</Label>
                            <Input id="bank_name" v-model="form.bank_name" list="bank-options" placeholder="Contoh: Bank Mandiri" class="h-10 rounded-xl text-xs" />
                            <datalist id="bank-options">
                                <option v-for="b in bankOptions" :key="b" :value="b" />
                            </datalist>
                            <InputError :message="form.errors.bank_name" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="bank_account_number" class="text-xs font-semibold">Nomor Rekening</Label>
                            <Input id="bank_account_number" v-model="form.bank_account_number" inputmode="numeric" placeholder="1560028450155" class="h-10 rounded-xl font-mono text-xs" />
                            <InputError :message="form.errors.bank_account_number" />
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="bank_account_name" class="text-xs font-semibold">Atas Nama Rekening</Label>
                            <Input id="bank_account_name" v-model="form.bank_account_name" placeholder="Kosongkan jika sama dengan nama karyawan" class="h-10 rounded-xl text-xs uppercase" />
                            <InputError :message="form.errors.bank_account_name" />
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: AKUN LOGIN PENGGUNA (RBAC) -->
                <div class="space-y-4">
                    <div
                        class="flex items-center gap-2 border-b border-gray-100 pb-1 text-xs font-bold tracking-wider text-gray-500 uppercase dark:border-zinc-800"
                    >
                        <KeyRound class="size-3.5 text-blue-500" />
                        <span>3. Hak Akses & Akun Login Aplikasi</span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Role RBAC -->
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="role_id" class="text-xs font-semibold">
                                Peran / Role Akses
                                <span class="text-rose-500">*</span>
                            </Label>
                            <select
                                id="role_id"
                                v-model="form.role_id"
                                required
                                class="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 focus:ring-2 focus:ring-rose-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-gray-300"
                            >
                                <option
                                    v-for="r in roles"
                                    :key="r.id"
                                    :value="r.id"
                                >
                                    {{ r.display_name }} ({{ r.description }})
                                </option>
                            </select>
                            <InputError :message="form.errors.role_id" />
                        </div>

                        <!-- Email Login -->
                        <div class="space-y-1.5">
                            <Label for="email" class="text-xs font-semibold">
                                Email Login Momoyo
                                <span class="text-rose-500">*</span>
                            </Label>
                            <div class="relative">
                                <Mail
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400"
                                />
                                <Input
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    placeholder="nama@momoyo.id"
                                    required
                                    class="h-10 rounded-xl pl-9 text-xs"
                                />
                            </div>
                            <InputError :message="form.errors.email" />
                        </div>

                        <!-- Username -->
                        <div class="space-y-1.5">
                            <Label for="username" class="text-xs font-semibold">
                                Username Sistem
                            </Label>
                            <Input
                                id="username"
                                v-model="form.username"
                                placeholder="nama_pengguna"
                                class="h-10 rounded-xl font-mono text-xs"
                            />
                            <InputError :message="form.errors.username" />
                        </div>

                        <!-- Password -->
                        <div class="space-y-1.5 sm:col-span-2">
                            <div class="flex items-center justify-between">
                                <Label
                                    for="password"
                                    class="text-xs font-semibold"
                                >
                                    Password Login
                                    <span
                                        v-if="!isEditing"
                                        class="text-rose-500"
                                        >*</span
                                    >
                                </Label>
                                <span
                                    v-if="isEditing"
                                    class="text-[11px] text-gray-400"
                                    >Kosongkan jika tidak ingin mengubah
                                    password</span
                                >
                            </div>
                            <div class="relative">
                                <Lock
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400"
                                />
                                <Input
                                    id="password"
                                    type="password"
                                    v-model="form.password"
                                    :placeholder="
                                        isEditing
                                            ? '•••••••• (Biarkan kosong jika tidak diubah)'
                                            : 'Minimal 6 karakter (Default: password)'
                                    "
                                    :required="!isEditing"
                                    class="h-10 rounded-xl pl-9 text-xs"
                                />
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>
                    </div>
                </div>

                <!-- DIALOG FOOTER -->
                <DialogFooter
                    class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4 dark:border-zinc-800"
                >
                    <Button
                        type="button"
                        variant="outline"
                        @click="isModalOpen = false"
                        class="h-10 rounded-xl text-xs"
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="h-10 rounded-xl bg-gradient-to-r from-red-600 via-rose-600 to-rose-700 px-5 text-xs font-bold text-white shadow-sm hover:from-red-700 hover:to-rose-800"
                    >
                        <Spinner v-if="form.processing" class="mr-1 size-3.5" />
                        <span>{{
                            isEditing
                                ? 'Simpan Perubahan Data'
                                : 'Daftarkan Karyawan & Akun'
                        }}</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
