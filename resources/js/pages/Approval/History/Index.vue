<script setup lang="ts">
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import AppLayout from "@/layout/AppLayout.vue";
import ApprovalPageHeader from "@/components/approval/ApprovalPageHeader.vue";
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";
import AppSelect from "@/components/common/AppSelect.vue";
import AppPagination from "@/components/common/AppPagination.vue";
import CrudTable from "@/components/crud/CrudTable.vue";

import { Eye } from "lucide-vue-next";
import ApprovalDetailModal from "../components/ApprovalDetailModal.vue";

defineOptions({
    layout: AppLayout,
});

interface User {
    id: number;
    name: string;
    email?: string;
}

interface Company {
    id: string;
    name: string;
    code?: string;
}

interface Department {
    id: string;
    name: string;
}

interface Workflow {
    id: string;
    module: string;
    name: string;
    company?: Company;
    department?: Department;
}

interface ApprovalRequest {
    id: string;
    module: string;
    document_type: string;
    document_id: string;
    status: string;
    current_level: number | null;
    submitted_by: number;
    submitted_at: string | null;
    completed_at?: string | null;
    workflow?: Workflow;
    submitter?: User;
}

interface ApprovalRequestLevel {
    id: string;
    level: number;
    approver_user_id: number;
    status: string;
    acted_at?: string | null;
    remarks?: string | null;
    approver?: User;
}

interface ApprovalAction {
    id: string;
    approval_request_id: string;
    approval_request_level_id: string;
    user_id: number;
    action: "APPROVED" | "REJECTED";
    remarks?: string | null;
    created_at: string;
    request?: ApprovalRequest;
    requestLevel?: ApprovalRequestLevel;
    user?: User;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedHistories {
    data: ApprovalAction[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    histories: PaginatedHistories;
    filters: {
        search?: string;
        module?: string;
        action?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();

const allFilterValue = "ALL";

const search = ref(props.filters?.search ?? "");

const selectedModule = ref(props.filters?.module || allFilterValue);

const selectedAction = ref(props.filters?.action || allFilterValue);

const perPage = ref(props.filters?.per_page ?? 10);

/*
|--------------------------------------------------------------------------
| Module
|--------------------------------------------------------------------------
*/

const moduleOptions = [
    {
        label: "Purchase Request",
        value: "PURCHASE_REQUEST",
    },
    {
        label: "Inventory Request",
        value: "INVENTORY_REQUEST",
    },
    {
        label: "Asset Transfer",
        value: "ASSET_TRANSFER",
    },
    {
        label: "Asset Disposal",
        value: "ASSET_DISPOSAL",
    },
    {
        label: "Asset Adjustment",
        value: "ASSET_ADJUSTMENT",
    },
];

const moduleFilterOptions = [
    {
        label: "All Modules",
        value: allFilterValue,
    },
    ...moduleOptions,
];

/*
|--------------------------------------------------------------------------
| Action
|--------------------------------------------------------------------------
*/

const actionOptions = [
    {
        label: "All Actions",
        value: allFilterValue,
    },
    {
        label: "Approved",
        value: "APPROVED",
    },
    {
        label: "Rejected",
        value: "REJECTED",
    },
];

/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

const columns = [
    {
        key: "document",
        label: "Document",
    },
    {
        key: "module",
        label: "Module",
    },
    {
        key: "workflow",
        label: "Workflow",
    },
    {
        key: "level",
        label: "Level",
    },
    {
        key: "action",
        label: "Action",
    },
    {
        key: "acted_at",
        label: "Acted At",
    },
    {
        key: "remarks",
        label: "Remarks",
    },
];

/*
|--------------------------------------------------------------------------
| Detail
|--------------------------------------------------------------------------
*/

const selectedHistory = ref<ApprovalAction | null>(null);

const showDetailModal = ref(false);

function openDetailModal(history: ApprovalAction): void {
    selectedHistory.value = history;
    showDetailModal.value = true;
}

function closeDetailModal(): void {
    showDetailModal.value = false;
    selectedHistory.value = null;
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function moduleLabel(module?: string): string {
    if (!module) {
        return "-";
    }

    return moduleOptions.find((item) => item.value === module)?.label ?? module;
}

function formatDate(value?: string | null): string {
    if (!value) {
        return "-";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(date);
}

function actionLabel(action?: string): string {
    switch (action) {
        case "APPROVED":
            return "Approved";

        case "REJECTED":
            return "Rejected";

        default:
            return action ?? "-";
    }
}

/*
|--------------------------------------------------------------------------
| Search & Filter
|--------------------------------------------------------------------------
*/

function submitSearch(): void {
    router.get(
        route("approval.history.index"),
        {
            search: search.value || undefined,

            module:
                selectedModule.value === allFilterValue
                    ? undefined
                    : selectedModule.value,

            action:
                selectedAction.value === allFilterValue
                    ? undefined
                    : selectedAction.value,

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
    selectedAction.value = allFilterValue;
    perPage.value = 10;

    submitSearch();
}

</script>

<template>
    <div class="procurement-ui space-y-6">
        <ApprovalPageHeader title="Approval History" section="History" description="Lihat keputusan persetujuan yang pernah Anda proses." />

        <!-- Main Card -->
        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <!-- Search & Filter -->
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-800"
            >
                <div class="flex flex-1 flex-wrap items-center gap-3">
                    <SearchFilterBar
                        v-model="search"
                        placeholder="Search approval history..."
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
                        v-model="selectedAction"
                        :options="actionOptions"
                        class="w-full sm:w-40"
                        @change="submitSearch"
                    />
                </div>

                <div
                    class="whitespace-nowrap rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-500 dark:bg-gray-900 dark:text-gray-400"
                >
                    Total
                    {{ histories.total }}
                    History
                </div>
            </div>

            <!-- Table -->
            <CrudTable
                :columns="columns"
                :rows="histories.data"
                empty-message="Belum ada approval history."
            >
                <!-- Document -->
                <template #cell-document="{ row }">
                    <div class="flex flex-col">
                        <button
                            type="button"
                            class="text-left font-semibold text-gray-900 hover:text-purple-700 dark:text-white dark:hover:text-purple-400"
                            @click="openDetailModal(row)"
                        >
                            {{ row.request?.document_id ?? "-" }}
                        </button>

                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            {{ row.request?.document_type ?? "-" }}
                        </span>
                    </div>
                </template>

                <!-- Module -->
                <template #cell-module="{ row }">
                    <span
                        class="inline-flex items-center rounded-full bg-purple-50 px-3 py-1 text-xs font-medium text-purple-700 dark:bg-purple-500/10 dark:text-purple-400"
                    >
                        {{ moduleLabel(row.request?.module) }}
                    </span>
                </template>

                <!-- Workflow -->
                <template #cell-workflow="{ row }">
                    <div class="flex flex-col">
                        <span
                            class="font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{ row.request?.workflow?.name ?? "-" }}
                        </span>

                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            {{ row.request?.workflow?.company?.name ?? "-" }}
                        </span>
                    </div>
                </template>

                <!-- Level -->
                <template #cell-level="{ row }">
                    <span
                        class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        Level
                        {{ row.requestLevel?.level ?? "-" }}
                    </span>
                </template>

                <!-- Action -->
                <template #cell-action="{ row }">
                    <span
                        v-if="row.action === 'APPROVED'"
                        class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400"
                    >
                        Approved
                    </span>

                    <span
                        v-else
                        class="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700 dark:bg-red-500/10 dark:text-red-400"
                    >
                        Rejected
                    </span>
                </template>

                <!-- Acted At -->
                <template #cell-acted_at="{ row }">
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ formatDate(row.created_at) }}
                    </span>
                </template>

                <!-- Remarks -->
                <template #cell-remarks="{ row }">
                    <span
                        class="block max-w-xs truncate text-sm text-gray-600 dark:text-gray-400"
                        :title="row.remarks ?? undefined"
                    >
                        {{ row.remarks || "-" }}
                    </span>
                </template>

                <!-- Actions -->
                <template #actions="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <button
                            type="button"
                            title="View"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                            @click="openDetailModal(row)"
                        >
                            <Eye class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </CrudTable>

            <!-- Pagination -->
            <AppPagination
                v-if="histories.total > 0"
                v-model:per-page="perPage"
                :meta="histories"
                :per-page-options="[{ label: '5', value: 5 }, { label: '10', value: 10 }, { label: '15', value: 15 }, { label: '25', value: 25 }, { label: '50', value: 50 }]"
                @change="submitSearch"
            />
        </div>

        <ApprovalDetailModal
            v-model:visible="showDetailModal"
            :approval="selectedHistory"
            :show-actions="false"
        />
    </div>
</template>
