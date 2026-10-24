export type Role = {
    id: number;
    name: 'admin_pusat' | 'staff_cabang' | 'kepala_toko';
    display_name: string;
    description?: string;
};

export type Branch = {
    id: number;
    branch_code: string;
    branch_name: string;
    address?: string;
    latitude: number;
    longitude: number;
    radius_meters: number;
    is_active: boolean;
};

export type Employee = {
    id: number;
    user_id: number;
    nik: string;
    full_name: string;
    phone_number?: string;
    current_branch_id: number;
    status: 'active' | 'inactive' | 'probation';
    current_branch?: Branch;
};

export type User = {
    id: number;
    role_id?: number | null;
    name: string;
    username?: string | null;
    email: string;
    avatar?: string;
    role?: Role;
    employee?: Employee;
    email_verified_at: string | null;
    is_active?: boolean;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
