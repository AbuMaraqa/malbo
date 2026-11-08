export interface PermissionGroup {
    key: string;
    label: string;
    permissions: { id: number; label: string }[];
}

export interface RoleSummary {
    total: number;
    group_count: number;
    groups: { label: string; count: number; actions: string[] }[];
}

export interface RoleRow {
    id: number;
    name: string;
    label: string;
    users_count: number;
    summary: RoleSummary;
    permissions: number[];
    protected: boolean;
    editable: boolean;
}
