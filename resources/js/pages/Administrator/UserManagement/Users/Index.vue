<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import { route } from "ziggy-js";

import AppPagination from "@/components/common/AppPagination.vue";
import AppSelect from "@/components/common/AppSelect.vue";
import Modal from "@/components/common/Modal.vue";
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";
import Header from "@/components/content/Header.vue";
import CrudActions from "@/components/crud/CrudActions.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import AppLayout from "@/layout/AppLayout.vue";

defineOptions({ layout: AppLayout });

interface RoleOption {
    id: number;
    name: string;
}

interface UserAccount {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    department_id: string | null;
    branch_id: string | null;
    last_login_at: string | null;
    roles: RoleOption[];
    company: { name: string } | null;
    branch: { name: string } | null;
    department: { name: string } | null;
    additional_departments: Array<{ id: string; name: string }>;
    additional_branches: Array<{ id: string; name: string }>;
    position: { name: string } | null;
}

interface PaginatedUsers {
    data: UserAccount[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

const props = defineProps<{
    users: PaginatedUsers;
    departments: Array<{ id: string; name: string; company_id: string }>;
    branches: Array<{ id: string; name: string; company_id: string }>;
    roles: RoleOption[];
    filters: {
        search: string;
        is_active: string;
        per_page: number;
    };
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
        assignAdministrator: boolean;
    };
    currentUserId: number;
}>();

const showForm = ref(false);
const selectedUser = ref<UserAccount | null>(null);
const search = ref(props.filters.search);
const allFilterValue = "ALL";
const selectedStatus = ref(props.filters.is_active);
const perPage = ref(props.filters.per_page);

const statusOptions = [
    { label: "All Statuses", value: allFilterValue },
    { label: "Active", value: "1" },
    { label: "Inactive", value: "0" },
];

const columns = [
    { key: "account", label: "Account" },
    { key: "roles", label: "Roles" },
    { key: "organization", label: "Organization" },
    { key: "is_active", label: "Status" },
    { key: "last_login_at", label: "Last Login" },
];

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    is_active: true,
    role_ids: [] as number[],
    department_id: "",
    additional_department_ids: [] as string[],
    branch_id: "",
    additional_branch_ids: [] as string[],
});

const inputClass =
    "h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-gray-700 dark:bg-gray-950 dark:text-white";
const labelClass =
    "mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300";
const errorClass = "mt-1.5 text-xs text-red-600 dark:text-red-400";

function openCreateForm(): void {
    selectedUser.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function openEditForm(user: UserAccount): void {
    selectedUser.value = user;
    form.name = user.name;
    form.email = user.email;
    form.password = "";
    form.password_confirmation = "";
    form.is_active = user.is_active;
    form.role_ids = user.roles.map((role) => role.id);
    form.department_id = user.department_id ?? "";
    form.additional_department_ids = user.additional_departments.map((department) => department.id);
    form.branch_id = user.branch_id ?? "";
    form.additional_branch_ids = user.additional_branches.map((branch) => branch.id);
    form.clearErrors();
    showForm.value = true;
}

function closeForm(): void {
    if (form.processing) {
        return;
    }

    showForm.value = false;
    form.clearErrors();
}

function submit(): void {
    const options = {
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    };

    if (selectedUser.value) {
        form.put(
            route("administrator.users.update", {
                user: selectedUser.value.id,
            }),
            options,
        );

        return;
    }

    form.post(route("administrator.users.store"), options);
}

function submitSearch(): void {
    router.get(
        route("administrator.users.index"),
        {
            search: search.value || undefined,
            is_active:
                selectedStatus.value === allFilterValue
                    ? undefined
                    : selectedStatus.value,
            per_page: perPage.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function clearSearch(): void {
    search.value = "";
    selectedStatus.value = allFilterValue;
    perPage.value = 15;
    submitSearch();
}

function deleteUser(user: UserAccount): void {
    if (!window.confirm(`Delete the account for ${user.name}?`)) {
        return;
    }

    router.delete(
        route("administrator.users.destroy", { user: user.id }),
        { preserveScroll: true },
    );
}

function formatLastLogin(value: string | null): string {
    return value ? new Date(value).toLocaleString() : "Never";
}
</script>

<template>
    <div class="space-y-6">
        <Header
            title="User Account"
            description="Create and maintain application accounts, roles, and active status."
        >
            <template v-if="can.create" #actions>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                    @click="openCreateForm"
                >
                    Tambah Akun
                </button>
            </template>
        </Header>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-800"
            >
                <div class="flex flex-1 flex-wrap items-center gap-3">
                    <SearchFilterBar
                        v-model="search"
                        placeholder="Search name or email..."
                        class="w-full sm:w-64"
                        @search="submitSearch"
                        @clear="clearSearch"
                    />
                    <AppSelect
                        v-model="selectedStatus"
                        :options="statusOptions"
                        class="w-full sm:w-40"
                        @change="submitSearch"
                    />
                </div>

                <div
                    class="whitespace-nowrap rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-500 dark:bg-gray-900 dark:text-gray-400"
                >
                    Total {{ users.total }} Accounts
                </div>
            </div>

            <CrudTable
                :columns="columns"
                :rows="users.data"
                empty-message="Tidak ada akun pengguna."
            >
                <template #cell-account="{ row }">
                    <div class="flex flex-col">
                        <span class="font-semibold text-gray-900 dark:text-white">
                            {{ row.name }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ row.email }}
                        </span>
                    </div>
                </template>

                <template #cell-roles="{ row }">
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="role in row.roles"
                            :key="role.id"
                            class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-800 dark:bg-brand-500/10 dark:text-brand-300"
                        >
                            {{ role.name }}
                        </span>
                        <span
                            v-if="row.roles.length === 0"
                            class="text-xs text-gray-400"
                        >
                            No role assigned
                        </span>
                    </div>
                </template>

                <template #cell-organization="{ row }">
                    <div class="flex flex-col text-xs">
                        <span class="font-medium text-gray-800 dark:text-gray-200">
                            {{ row.company?.name ?? "—" }}
                            <span v-if="row.branch?.name"> / {{ row.branch.name }}</span>
                        </span>
                        <span class="text-gray-500 dark:text-gray-400">
                            {{ row.department?.name ?? "No department" }}
                            <span v-if="row.position?.name"> · {{ row.position.name }}</span>
                        </span>
                        <span v-if="row.additional_branches.length" class="text-xs text-gray-400">Akses branch: {{ row.additional_branches.map((branch) => branch.name).join(", ") }}</span>
                    </div>
                </template>

                <template #cell-is_active="{ row }">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium"
                        :class="
                            row.is_active
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                        "
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="row.is_active ? 'bg-emerald-500' : 'bg-gray-400'"
                        ></span>
                        {{ row.is_active ? "Active" : "Inactive" }}
                    </span>
                </template>

                <template #cell-last_login_at="{ row }">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ formatLastLogin(row.last_login_at) }}
                    </span>
                </template>

                <template #actions="{ row }">
                    <CrudActions
                        :show-edit="can.update"
                        :show-delete="can.delete && row.id !== currentUserId"
                        :processing="form.processing"
                        @edit="openEditForm(row)"
                        @delete="deleteUser(row)"
                    />
                </template>
            </CrudTable>

            <AppPagination
                v-model:per-page="perPage"
                :meta="users"
                @change="submitSearch"
            />
        </div>

        <Modal
            :show="showForm"
            :title="selectedUser ? 'Ubah Akun Pengguna' : 'Tambah Akun Pengguna'"
            max-width="2xl"
            @close="closeForm"
        >
            <form class="space-y-5" @submit.prevent="submit">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="user-name" :class="labelClass">Nama</label>
                        <input
                            id="user-name"
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            maxlength="255"
                            :class="inputClass"
                        />
                        <p v-if="form.errors.name" :class="errorClass">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="user-email" :class="labelClass">Email</label>
                        <input
                            id="user-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            maxlength="255"
                            :class="inputClass"
                        />
                        <p v-if="form.errors.email" :class="errorClass">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label for="user-password" :class="labelClass">
                            Password
                            <span v-if="selectedUser" class="font-normal text-gray-500">
                                (kosongkan jika tidak diubah)
                            </span>
                        </label>
                        <input
                            id="user-password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            :required="!selectedUser"
                            :class="inputClass"
                        />
                        <p v-if="form.errors.password" :class="errorClass">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label for="user-password-confirmation" :class="labelClass">
                            Konfirmasi Password
                        </label>
                        <input
                            id="user-password-confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            :required="!selectedUser && Boolean(form.password)"
                            :class="inputClass"
                        />
                    </div>

                    <div>
                        <label for="user-branch" :class="labelClass">Branch utama</label>
                        <select id="user-branch" v-model="form.branch_id" :class="inputClass">
                            <option value="">{{ can.assignAdministrator ? "Tanpa branch utama" : "Pilih branch" }}</option>
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                {{ branch.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.branch_id" :class="errorClass">{{ form.errors.branch_id }}</p>
                    </div>

                    <div>
                        <label for="user-additional-branches" :class="labelClass">Akses branch tambahan</label>
                        <select id="user-additional-branches" v-model="form.additional_branch_ids" multiple :class="[inputClass, 'min-h-28']" :disabled="!can.assignAdministrator">
                            <option v-for="branch in branches.filter((item) => item.id !== form.branch_id)" :key="branch.id" :value="branch.id">
                                {{ branch.name }}
                            </option>
                        </select>
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Pilih jika user memerlukan akses ke branch lain. Gunakan Ctrl/Cmd untuk memilih beberapa.</p>
                        <p v-if="form.errors.additional_branch_ids" :class="errorClass">{{ form.errors.additional_branch_ids }}</p>
                    </div>

                    <div>
                        <label for="user-department" :class="labelClass">Department utama</label>
                        <select id="user-department" v-model="form.department_id" :class="inputClass">
                            <option value="">Pilih department</option>
                            <option v-for="department in departments" :key="department.id" :value="department.id">
                                {{ department.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.department_id" :class="errorClass">{{ form.errors.department_id }}</p>
                    </div>

                    <div>
                        <label for="user-additional-departments" :class="labelClass">Akses department tambahan</label>
                        <select id="user-additional-departments" v-model="form.additional_department_ids" multiple :class="[inputClass, 'min-h-28']" :disabled="!can.assignAdministrator">
                            <option v-for="department in departments.filter((item) => item.id !== form.department_id)" :key="department.id" :value="department.id">
                                {{ department.name }}
                            </option>
                        </select>
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Pilih hanya jika user memang perlu mengakses department lain. Gunakan Ctrl/Cmd untuk memilih beberapa.</p>
                        <p v-if="form.errors.additional_department_ids" :class="errorClass">{{ form.errors.additional_department_ids }}</p>
                    </div>
                </div>

                <div>
                    <label :class="labelClass">Role</label>
                    <div
                        class="grid gap-2 rounded-xl border border-gray-200 p-3 sm:grid-cols-2 dark:border-gray-700"
                    >
                        <label
                            v-for="role in roles"
                            :key="role.id"
                            class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                            :class="
                                ['Administrator', 'Owner'].includes(role.name) && !can.assignAdministrator
                                    ? 'cursor-not-allowed opacity-50'
                                    : 'cursor-pointer'
                            "
                        >
                            <input
                                v-model="form.role_ids"
                                type="checkbox"
                                :value="role.id"
                                :disabled="
                                    (['Administrator', 'Owner'].includes(role.name) &&
                                        !can.assignAdministrator) ||
                                    (['Administrator', 'Owner'].includes(role.name) &&
                                        selectedUser?.id === currentUserId)
                                "
                                class="rounded border-gray-300 text-brand-700 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900"
                            />
                            {{ role.name }}
                        </label>
                    </div>
                    <p v-if="form.errors.role_ids" :class="errorClass">
                        {{ form.errors.role_ids }}
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        :disabled="selectedUser?.id === currentUserId"
                        class="rounded border-gray-300 text-brand-700 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900"
                    />
                    Akun aktif
                </label>
                <p v-if="form.errors.is_active" :class="errorClass">
                    {{ form.errors.is_active }}
                </p>

                <div class="flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        :disabled="form.processing"
                        @click="closeForm"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing || form.role_ids.length === 0"
                    >
                        {{ form.processing ? "Menyimpan..." : "Simpan" }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
