export interface WorkItem {
    id: number;
    section_id: number;
    uraian: string;
    deskripsi: string | null;
    qty: number;
    satuan: string;
    harga_satuan: number;
    total: number;
    durasi_hari: number | null;
    progress: number;
    status: 'belum_mulai' | 'berjalan' | 'selesai';
    tgl_rencana_mulai: string | null;
    tgl_rencana_selesai: string | null;
    tgl_aktual_mulai: string | null;
    tgl_aktual_selesai: string | null;
    is_terlambat: boolean;
}

export interface WorkSection {
    id: number;
    kode_bidang: string;
    nama: string;
    items: WorkItem[];
}

export interface RabVersion {
    id: number;
    versi: number;
    status: 'draft' | 'sent';
    sent_at: string | null;
    sections?: WorkSection[];
}

export interface ProgressPhoto {
    id: number;
    url: string;
}

export interface ProgressLog {
    id: number;
    progress_dari: number;
    progress_ke: number;
    catatan: string | null;
    verified_at: string | null;
    created_at: string;
    user: { id: number; name: string };
    verifier: { id: number; name: string } | null;
    work_item: { id: number; uraian: string };
    photos: ProgressPhoto[];
}

export interface ProjectDocument {
    id: number;
    kategori: string;
    nama_file: string;
    url: string;
    created_at: string;
    uploader: { id: number; name: string } | null;
}

export interface Project {
    id: number;
    kode: string;
    nama: string;
    client_name: string | null;
    branch_id: number;
    pic_id: number | null;
    alamat: string | null;
    luas_m2: number | null;
    tgl_mulai_rencana: string | null;
    tgl_selesai_rencana: string | null;
    nilai_kontrak: number;
    progress_mode: 'weighted' | 'simple';
    progress: number;
    status: string;
    catatan: string | null;
    branch?: { id: number; branch_name: string };
    pic?: { id: number; full_name: string } | null;
    documents?: ProjectDocument[];
    rab_versions?: RabVersion[];
    current_rab?: RabVersion | null;
}

export const statusLabels: Record<string, string> = {
    draft: 'Draft',
    rab_dikirim: 'RAB Dikirim',
    berjalan: 'Berjalan',
    tagihan_dikirim: 'Tagihan Dikirim',
    selesai: 'Selesai',
    dibatalkan: 'Dibatalkan',
};

export const statusClass: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-700',
    rab_dikirim: 'bg-sky-100 text-sky-700',
    berjalan: 'bg-amber-100 text-amber-700',
    tagihan_dikirim: 'bg-purple-100 text-purple-700',
    selesai: 'bg-emerald-100 text-emerald-700',
    dibatalkan: 'bg-red-100 text-red-700',
};

export const itemStatusLabels: Record<string, string> = {
    belum_mulai: 'Belum Mulai',
    berjalan: 'Berjalan',
    selesai: 'Selesai',
};

export const itemStatusClass: Record<string, string> = {
    belum_mulai: 'bg-gray-100 text-gray-600',
    berjalan: 'bg-amber-100 text-amber-700',
    selesai: 'bg-emerald-100 text-emerald-700',
};

export const documentCategories: Record<string, string> = {
    gambar_kerja: 'Gambar Kerja',
    rendering_3d: '3D Rendering',
    material_list: 'Material List',
    lainnya: 'Lainnya',
};

/** Rata-rata progress item; berbobot nilai RAB atau sederhana (sama dengan aturan di server). */
export const averageProgress = (items: WorkItem[], mode: 'weighted' | 'simple'): number => {
    if (items.length === 0) return 0;
    const weight = mode === 'weighted' ? items.reduce((s, i) => s + i.total, 0) : 0;
    if (weight > 0) return Math.round((items.reduce((s, i) => s + i.progress * i.total, 0) / weight) * 100) / 100;
    return Math.round((items.reduce((s, i) => s + i.progress, 0) / items.length) * 100) / 100;
};

export interface PlacementEmployee {
    id: number;
    employee_id: number;
    peran: string;
    employee: { id: number; full_name: string; nik: string };
}

export interface Placement {
    id: number;
    nomor_surat: string | null;
    tgl_surat: string | null;
    tgl_mulai: string;
    tgl_selesai: string;
    lokasi: string | null;
    catatan: string | null;
    penandatangan_nama: string | null;
    penandatangan_jabatan: string | null;
    status: 'draft' | 'diterbitkan' | 'dibatalkan' | 'selesai';
    override_reason: string | null;
    revision_of: number | null;
    employees: PlacementEmployee[];
    revisions: { id: number; revision_of: number }[];
}

export interface EmployeeOption {
    id: number;
    full_name: string;
    nik: string;
    current_branch?: { id: number; branch_name: string } | null;
}

export interface Milestone {
    label: string;
    date: string | null;
}

export const placementStatusLabels: Record<string, string> = {
    draft: 'Draft',
    diterbitkan: 'Diterbitkan',
    dibatalkan: 'Dibatalkan',
    selesai: 'Selesai',
};

export const placementStatusClass: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-700',
    diterbitkan: 'bg-emerald-100 text-emerald-700',
    dibatalkan: 'bg-red-100 text-red-700',
    selesai: 'bg-sky-100 text-sky-700',
};

export const placementRoles = ['Penanggung Jawab', 'Pengawas', 'Tukang', 'Helper'];
