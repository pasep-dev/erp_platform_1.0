<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, router, Head } from "@inertiajs/vue3";
import { Plus, Search, Building2 } from "lucide-vue-next";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import CrudActions from "@/components/crud/CrudActions.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import DeleteConfirm from "@/components/crud/DeleteConfirm.vue";
import { useCrud } from "@/composables/useCrud";
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";
import Header from "@/components/content/Header.vue";

defineOptions({
    layout: AppLayout,
});

interface Company {
    id: string;
    code: string;
    name: string;
}

interface Department {
    id: string;
    company_id: string;
    code: string;
    name: string;
    is_active: boolean;
    created_at: string;
    company?: Company;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedDepartments {
    data: Department[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    departments: PaginatedDepartments;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search);

const hasDepartments = computed(() => props.departments.data.length > 0);

const columns = [
    {
        key: "company",
        label: "Company",
    },
    {
        key: "code",
        label: "Code",
    },
    {
        key: "name",
        label: "Name",
    },
    {
        key: "is_active",
        label: "Status",
    },
];

const showDeleteConfirm = ref(false);

const selectedDepartment = ref<Department | null>(null);

const { processing, destroy } = useCrud({
    indexRoute: route("master.organization.departments.index"),

    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedDepartment.value = null;
    },
});

function submitSearch(): void {
    router.get(
        route("master.organization.departments.index"),
        {
            search: search.value || undefined,
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
    submitSearch();
}

function deleteDepartment(department: Department): void {
    selectedDepartment.value = department;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) {
        return;
    }

    showDeleteConfirm.value = false;
    selectedDepartment.value = null;
}

function confirmDelete(): void {
    if (!selectedDepartment.value) {
        return;
    }

    destroy(
        route(
            "master.organization.departments.destroy",
            selectedDepartment.value.id,
        ),
    );
}
</script>

<template>
    <div class="space-y-6">
        <Header
            title="Master Departments"
            :action-url="route('master.organization.departments.create')"
            action-label="Add Department"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <SearchFilterBar
                    v-model="search"
                    :total="departments.total"
                    placeholder="Cari data..."
                    @search="submitSearch"
                    @clear="clearSearch"
                />
            </div>

            <CrudTable
                :columns="columns"
                :rows="departments.data"
                empty-message="Tidak ada data department."
            >
                <template #cell-company="{ row }">
                    <div>
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ row.company?.name || "-" }}
                        </div>

                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ row.company?.code || "-" }}
                        </div>
                    </div>
                </template>

                <template #cell-code="{ row }">
                    <span class="font-medium text-gray-900 dark:text-white">
                        {{ row.code }}
                    </span>
                </template>

                <template #cell-name="{ row }">
                    {{ row.name }}
                </template>

                <template #cell-is_active="{ row }">
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            row.is_active
                                ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400'
                        "
                    >
                        {{ row.is_active ? "Active" : "Inactive" }}
                    </span>
                </template>

                <template #actions="{ row }">
                    <CrudActions
                        :processing="processing"
                        @edit="
                            router.visit(
                                route(
                                    'master.organization.departments.edit',
                                    row.id,
                                ),
                            )
                        "
                        @delete="deleteDepartment(row)"
                    />
                </template>
            </CrudTable>

            <div
                v-if="departments.last_page > 1"
                class="flex flex-col gap-3 border-t border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ departments.from }}–{{ departments.to }} dari
                    {{ departments.total }} data
                </p>

                <div class="flex flex-wrap gap-1">
                    <template
                        v-for="link in departments.links"
                        :key="link.label"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="rounded-lg px-3 py-2 text-xs font-medium"
                            :class="
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/[0.05]'
                            "
                            v-html="link.label"
                        />

                        <span
                            v-else
                            class="rounded-lg px-3 py-2 text-xs text-gray-300 dark:text-gray-600"
                            v-html="link.label"
                        ></span>
                    </template>
                </div>
            </div>
        </div>

        <DeleteConfirm
            :show="showDeleteConfirm"
            :processing="processing"
            title="Hapus Department"
            :message="
                selectedDepartment
                    ? `Apakah Anda yakin ingin menghapus department '${selectedDepartment.name}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
