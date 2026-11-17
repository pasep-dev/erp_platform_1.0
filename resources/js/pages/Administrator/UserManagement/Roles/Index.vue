<script setup lang="ts">
import { router, useForm, Link } from "@inertiajs/vue3";
import { KeyRound } from "lucide-vue-next";
import { ref } from "vue";
import { route } from "ziggy-js";

import AppPagination from "@/components/common/AppPagination.vue";
import Modal from "@/components/common/Modal.vue";
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";
import Header from "@/components/content/Header.vue";
import CrudActions from "@/components/crud/CrudActions.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import AppLayout from "@/layout/AppLayout.vue";

defineOptions({ layout: AppLayout });

interface UserRole {
    id: number;
    name: string;
    users_count: number;
    permissions_count: number;
    is_system: boolean;
}

interface PaginatedRoles {
    data: UserRole[];
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
    roles: PaginatedRoles;
    filters: {
        search: string;
        per_page: number;
    };
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
        manageAccess: boolean;
    };
}>();

const showForm = ref(false);
const selectedRole = ref<UserRole | null>(null);
const search = ref(props.filters.search);
const perPage = ref(props.filters.per_page);

const columns = [
    { key: "role", label: "Role" },
    { key: "users_count", label: "Users" },
    { key: "permissions_count", label: "Permissions" },
];

const form = useForm({
    name: "",
});

const inputClass =
    "h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-gray-700 dark:bg-gray-950 dark:text-white";
const labelClass =
    "mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300";
const errorClass = "mt-1.5 text-xs text-red-600 dark:text-red-400";

function openCreateForm(): void {
    selectedRole.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function openEditForm(role: UserRole): void {
    selectedRole.value = role;
    form.name = role.name;
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

    if (selectedRole.value) {
        form.put(
            route("administrator.roles.update", {
                role: selectedRole.value.id,
            }),
            options,
        );

        return;
    }

    form.post(route("administrator.roles.store"), options);
}

function submitSearch(): void {
    router.get(
        route("administrator.roles.index"),
        {
            search: search.value || undefined,
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
    perPage.value = 15;
    submitSearch();
}

function deleteRole(role: UserRole): void {
    if (!window.confirm(`Delete the "${role.name}" role?`)) {
        return;
    }

    router.delete(
        route("administrator.roles.destroy", { role: role.id }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="space-y-6">
        <Header
            title="Role"
            description="Create roles and review how accounts are assigned."
        >
            <template v-if="can.create" #actions>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                    @click="openCreateForm"
                >
                    Tambah Role
                </button>
            </template>
        </Header>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <SearchFilterBar
                    v-model="search"
                    placeholder="Search role..."
                    class="w-full sm:w-64"
                    @search="submitSearch"
                    @clear="clearSearch"
                />
                <div
                    class="whitespace-nowrap rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-500 dark:bg-gray-900 dark:text-gray-400"
                >
                    Total {{ roles.total }} Roles
                </div>
            </div>

            <CrudTable
                :columns="columns"
                :rows="roles.data"
                empty-message="Tidak ada role."
            >
                <template #cell-role="{ row }">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-gray-900 dark:text-white">
                            {{ row.name }}
                        </span>
                        <span
                            v-if="row.is_system"
                            class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                        >
                            System
                        </span>
                    </div>
                </template>

                <template #cell-users_count="{ row }">
                    {{ row.users_count }}
                </template>

                <template #cell-permissions_count="{ row }">
                    {{ row.permissions_count }}
                </template>

                <template #actions="{ row }">
                    <div class="flex items-center justify-end gap-2">
                        <Link
                            v-if="can.manageAccess && row.name !== 'Administrator'"
                            :href="
                                route('administrator.access-control.edit', {
                                    type: 'role',
                                    target: row.id,
                                })
                            "
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:text-gray-300 dark:hover:border-brand-700 dark:hover:bg-brand-500/10 dark:hover:text-brand-300"
                            title="Atur hak akses"
                            :aria-label="`Atur hak akses ${row.name}`"
                        >
                            <KeyRound class="h-4 w-4" aria-hidden="true" />
                        </Link>
                        <CrudActions
                            :show-edit="can.update && !row.is_system"
                            :show-delete="
                                can.delete && !row.is_system && row.users_count === 0
                            "
                            :processing="form.processing"
                            @edit="openEditForm(row)"
                            @delete="deleteRole(row)"
                        />
                    </div>
                </template>
            </CrudTable>

            <AppPagination
                v-model:per-page="perPage"
                :meta="roles"
                @change="submitSearch"
            />
        </div>

        <section
            class="rounded-xl border border-amber-100 bg-amber-50/70 p-5 dark:border-amber-900/60 dark:bg-amber-950/30"
        >
            <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                System roles
            </h2>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                Administrator, Manager, and User are protected system roles. Create
                a custom role to configure additional access.
            </p>
        </section>

        <Modal
            :show="showForm"
            :title="selectedRole ? 'Ubah Role' : 'Tambah Role'"
            max-width="lg"
            @close="closeForm"
        >
            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="role-name" :class="labelClass">Nama Role</label>
                    <input
                        id="role-name"
                        v-model="form.name"
                        type="text"
                        maxlength="125"
                        autocomplete="off"
                        :class="inputClass"
                    />
                    <p v-if="form.errors.name" :class="errorClass">
                        {{ form.errors.name }}
                    </p>
                </div>

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
                        :disabled="form.processing || !form.name.trim()"
                    >
                        {{ form.processing ? "Menyimpan..." : "Simpan" }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
