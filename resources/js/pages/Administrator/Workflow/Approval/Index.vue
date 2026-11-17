<script setup lang="ts">
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import Header from "@/components/content/Header.vue";
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";
import AppSelect from "@/components/common/AppSelect.vue";
import AppPagination from "@/components/common/AppPagination.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import CrudActions from "@/components/crud/CrudActions.vue";
import DeleteConfirm from "@/components/crud/DeleteConfirm.vue";
import WorkflowDetailModal from "./components/WorkflowDetailModal.vue";
import { useCrud } from "@/composables/useCrud";
import { Info } from "lucide-vue-next";

defineOptions({
    layout: AppLayout,
});

interface Company {
    id: string;
    name: string;
    code?: string;
}

interface Department {
    id: string;
    name: string;
}

interface Position {
    id: string;
    name: string;
}

interface ApproverUser {
    id: string;
    name: string;
    email: string;
    position?: Position;
}

interface WorkflowLevel {
    id: string;
    level: number;
    approver_user_id: string;
    approver?: ApproverUser;
}

interface ApprovalWorkflow {
    id: string;
    module: string;
    name: string;
    is_active: boolean;
    company?: Company;
    department?: Department;
    levels?: WorkflowLevel[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedWorkflows {
    data: ApprovalWorkflow[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    workflows: PaginatedWorkflows;
    filters: {
        search?: string;
        module?: string;
        is_active?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();

const allFilterValue = "ALL";
const search = ref(props.filters?.search ?? "");
const selectedModule = ref(props.filters?.module || allFilterValue);
const selectedStatus = ref(
    props.filters?.is_active
        ? String(props.filters.is_active)
        : allFilterValue,
);

// State Per Page
const perPage = ref(props.filters?.per_page ?? 5);

// State Modal Detail
const showDetailModal = ref(false);
const selectedWorkflow = ref<ApprovalWorkflow | null>(null);

const moduleOptions = [
    { label: "Purchase Request", value: "PURCHASE_REQUEST" },
    { label: "Inventory Request", value: "INVENTORY_REQUEST" },
    { label: "Inventory Opening Balance", value: "INVENTORY_OPENING_BALANCE" },
    { label: "Asset Transfer", value: "ASSET_TRANSFER" },
    { label: "Asset Disposal", value: "ASSET_DISPOSAL" },
    { label: "Asset Adjustment", value: "ASSET_ADJUSTMENT" },
];

const moduleFilterOptions = [
    { label: "All Modules", value: allFilterValue },
    ...moduleOptions,
];

const statusOptions = [
    { label: "All Statuses", value: allFilterValue },
    { label: "Active", value: "1" },
    { label: "Inactive", value: "0" },
];

const columns = [
    { key: "name", label: "Module" },
    { key: "company", label: "Company" },
    { key: "department", label: "Department" },
    { key: "levels", label: "Levels" },
    { key: "is_active", label: "Status" },
];

const moduleLabel = (module: string) => {
    return moduleOptions.find((item) => item.value === module)?.label ?? module;
};

const getCompanyCode = (company?: Company) => {
    if (!company?.name) return "--";
    if (company.code) return company.code;

    const words = company.name.trim().split(" ");
    if (words.length === 1) return words[0].substring(0, 3).toUpperCase();
    return words
        .map((w) => w[0])
        .join("")
        .substring(0, 3)
        .toUpperCase();
};

// State & Composable Hapus
const showDeleteConfirm = ref(false);

const { processing, destroy } = useCrud({
    indexRoute: route("administrator.approval-workflows.index"),
    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedWorkflow.value = null;
    },
});

// Submit Request termasuk per_page
function submitSearch(): void {
    router.get(
        route("administrator.approval-workflows.index"),
        {
            search: search.value || undefined,
            module:
                selectedModule.value === allFilterValue
                    ? undefined
                    : selectedModule.value,
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
    selectedModule.value = allFilterValue;
    selectedStatus.value = allFilterValue;
    perPage.value = 5;
    submitSearch();
}

// Handler Actions
function openDetailModal(workflow: ApprovalWorkflow): void {
    selectedWorkflow.value = workflow;
    showDetailModal.value = true;
}

function editWorkflow(workflow: ApprovalWorkflow): void {
    router.get(route("administrator.approval-workflows.edit", workflow.id));
}

function deleteWorkflow(workflow: ApprovalWorkflow): void {
    selectedWorkflow.value = workflow;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) return;
    showDeleteConfirm.value = false;
    selectedWorkflow.value = null;
}

function confirmDelete(): void {
    if (!selectedWorkflow.value) return;
    destroy(
        route(
            "administrator.approval-workflows.destroy",
            selectedWorkflow.value.id,
        ),
    );
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <Header
            title="Approval Workflow"
            titlehead="Administrator - Approval Workflow"
            :action-url="route('administrator.approval-workflows.create')"
            action-label="Add New"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <!-- Search & Filter Bar -->
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-800"
            >
                <div class="flex flex-1 flex-wrap items-center gap-3">
                    <SearchFilterBar
                        v-model="search"
                        placeholder="Search workflow..."
                        class="w-full sm:w-64"
                        @search="submitSearch"
                        @clear="clearSearch"
                    />

                    <AppSelect
                        v-model="selectedModule"
                        :options="moduleFilterOptions"
                        class="w-full sm:w-48"
                        @change="submitSearch"
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
                    Total {{ workflows.total }} Workflows
                </div>
            </div>

            <!-- CrudTable -->
            <CrudTable
                :columns="columns"
                :rows="workflows.data"
                empty-message="Tidak ada approval workflow."
            >
                <!-- Column: Workflow/Module -->
                <template #cell-name="{ row }">
                    <div class="flex flex-col">
                        <button
                            type="button"
                            class="text-left font-semibold text-gray-900 hover:text-purple-700 dark:text-white dark:hover:text-purple-400"
                            @click="openDetailModal(row)"
                        >
                            {{ moduleLabel(row.module) }}
                        </button>
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            {{ row.name }}
                        </span>
                    </div>
                </template>

                <!-- Column: Company -->
                <template #cell-company="{ row }">
                    <div v-if="row.company" class="flex items-center gap-2.5">
                        <span
                            class="flex h-7 w-9 shrink-0 items-center justify-center rounded-md bg-purple-100 text-[11px] font-bold tracking-wider text-purple-800 dark:bg-purple-950/60 dark:text-purple-300"
                        >
                            {{ getCompanyCode(row.company) }}
                        </span>
                        <span
                            class="font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{ row.company.name }}
                        </span>
                    </div>
                    <span v-else class="text-gray-400">-</span>
                </template>

                <!-- Column: Department -->
                <template #cell-department="{ row }">
                    <span class="text-gray-700 dark:text-gray-300">
                        {{ row.department?.name ?? "-" }}
                    </span>
                </template>

                <!-- Column: Levels -->
                <template #cell-levels="{ row }">
                    <div class="flex flex-col">
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ row.levels?.length ?? 0 }} Level{{
                                (row.levels?.length ?? 0) > 1 ? "s" : ""
                            }}
                        </span>
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            {{ row.levels?.length ?? 0 }} approver{{
                                (row.levels?.length ?? 0) > 1 ? "s" : ""
                            }}
                        </span>
                    </div>
                </template>

                <!-- Column: Status Badge -->
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
                            :class="
                                row.is_active ? 'bg-emerald-500' : 'bg-gray-400'
                            "
                        ></span>
                        {{ row.is_active ? "Active" : "Inactive" }}
                    </span>
                </template>

                <!-- Actions Slot -->
                <template #actions="{ row }">
                    <CrudActions
                        :show-view="true"
                        :show-edit="true"
                        :show-delete="true"
                        :processing="processing"
                        @view="openDetailModal(row)"
                        @edit="editWorkflow(row)"
                        @delete="deleteWorkflow(row)"
                    />
                </template>
            </CrudTable>

            <!-- Pagination Component -->
            <AppPagination
                v-model:per-page="perPage"
                :meta="workflows"
                @change="submitSearch"
            />
        </div>

        <!-- Note Box -->
        <div
            class="flex items-start gap-3 rounded-xl border border-purple-100 bg-purple-50/50 p-4 text-xs text-purple-900 dark:border-purple-900/40 dark:bg-purple-950/20 dark:text-purple-300"
        >
            <Info
                class="mt-0.5 h-4 w-4 shrink-0 text-purple-600 dark:text-purple-400"
            />
            <p class="leading-relaxed">
                Perubahan pada workflow aktif akan diterapkan ke pengajuan baru.
                Pengajuan yang sedang berjalan tetap mengikuti jalur persetujuan
                sebelumnya.
            </p>
        </div>

        <!-- Extracted Workflow Detail Modal Component -->
        <WorkflowDetailModal
            v-model:visible="showDetailModal"
            :workflow="selectedWorkflow"
            :module-options="moduleOptions"
            @edit="editWorkflow"
        />

        <!-- Delete Confirm Component -->
        <DeleteConfirm
            :show="showDeleteConfirm"
            :processing="processing"
            title="Hapus Workflow"
            :message="
                selectedWorkflow
                    ? `Apakah Anda yakin ingin menghapus workflow '${selectedWorkflow.name}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
