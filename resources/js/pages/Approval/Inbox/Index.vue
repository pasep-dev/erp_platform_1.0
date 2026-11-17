<script setup lang="ts">
import { computed, ref } from "vue";
import { router } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import AppLayout from "@/layout/AppLayout.vue";
import AppPagination from "@/components/common/AppPagination.vue";
import ApprovalPageHeader from "@/components/approval/ApprovalPageHeader.vue";
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";
import AppSelect from "@/components/common/AppSelect.vue";
import CrudTable from "@/components/crud/CrudTable.vue";

import { Eye, Check, X } from "lucide-vue-next";
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

interface ApprovalRequestLevel {
    id: string;
    level: number;
    approver_user_id: number;
    status: string;
    acted_at?: string | null;
    remarks?: string | null;
    approver?: User;
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

    levels?: ApprovalRequestLevel[];
}

interface ApprovalRequestLevelInbox extends ApprovalRequestLevel {
    request?: ApprovalRequest;
}

interface Props {
    inbox: ApprovalRequestLevelInbox[];
}

const props = defineProps<Props>();

const allFilterValue = "ALL";

/*
|--------------------------------------------------------------------------
| Search & Filter
|--------------------------------------------------------------------------
*/

const search = ref("");
const selectedModule = ref(allFilterValue);
const perPage = ref(15);
const currentPage = ref(1);

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
        label: "Inventory Opening Balance",
        value: "INVENTORY_OPENING_BALANCE",
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
        key: "submitter",
        label: "Submitted By",
    },
    {
        key: "level",
        label: "Level",
    },
    {
        key: "submitted_at",
        label: "Submitted",
    },
];

/*
|--------------------------------------------------------------------------
| Modal & Action State
|--------------------------------------------------------------------------
*/

const selectedApproval = ref<ApprovalRequestLevelInbox | null>(null);

const showDetailModal = ref(false);
const showRejectModal = ref(false);

const remarks = ref("");
const processing = ref(false);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const moduleLabel = (module?: string): string => {
    if (!module) {
        return "-";
    }

    return moduleOptions.find((item) => item.value === module)?.label ?? module;
};

const filteredInbox = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    return props.inbox.filter((item) => {
        const request = item.request;

        if (!request) {
            return false;
        }

        const matchesModule =
            selectedModule.value === allFilterValue ||
            request.module === selectedModule.value;

        if (!matchesModule) {
            return false;
        }

        if (!keyword) {
            return true;
        }

        const searchableText = [
            request.document_type,
            request.document_id,
            request.module,
            request.workflow?.name,
            request.submitter?.name,
            request.submitter?.email,
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return searchableText.includes(keyword);
    });
});

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

function documentLabel(request?: ApprovalRequest): string {
    if (!request) {
        return "-";
    }

    return request.document_type || "-";
}

/*
|--------------------------------------------------------------------------
| Detail Modal
|--------------------------------------------------------------------------
*/

function openDetailModal(approval: ApprovalRequestLevelInbox): void {
    selectedApproval.value = approval;
    showDetailModal.value = true;
}

function closeDetailModal(): void {
    if (processing.value) {
        return;
    }

    showDetailModal.value = false;
    selectedApproval.value = null;
}

/*
|--------------------------------------------------------------------------
| Approve
|--------------------------------------------------------------------------
*/

function approve(approval: ApprovalRequestLevelInbox): void {
    if (!approval.request) {
        return;
    }

    processing.value = true;

    router.post(
        route("approval.inbox.approve", approval.request.id),
        {},
        {
            preserveScroll: true,

            onSuccess: () => {
                showDetailModal.value = false;
                selectedApproval.value = null;
            },

            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

/*
|--------------------------------------------------------------------------
| Reject
|--------------------------------------------------------------------------
*/

function reject(approval: ApprovalRequestLevel, remarks: string): void {
    if (!approval.request) {
        return;
    }

    processing.value = true;

    router.post(
        route("approval.inbox.reject", approval.request.id),
        {
            remarks,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                showDetailModal.value = false;
                selectedApproval.value = null;
            },
        },
    );
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

function submitSearch(): void {
    /*
     * Inbox saat ini menggunakan data yang sudah dikirim
     * oleh ApprovalService.
     *
     * Filtering dilakukan di client-side.
     */
    currentPage.value = 1;
}

function clearSearch(): void {
    search.value = "";
    selectedModule.value = allFilterValue;
    currentPage.value = 1;
}

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

const totalInbox = computed(() => filteredInbox.value.length);
const pageCount = computed(() => Math.ceil(totalInbox.value / perPage.value));
const visibleInbox = computed(() =>
    filteredInbox.value.slice((currentPage.value - 1) * perPage.value, currentPage.value * perPage.value),
);
const paginationMeta = computed(() => ({ total: totalInbox.value }));

function changePerPage(): void {
    currentPage.value = 1;
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <ApprovalPageHeader title="My Approval" section="Inbox" description="Tinjau dan proses dokumen yang menunggu persetujuan Anda." />

        <!-- Main Card -->
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
                        placeholder="Search approval..."
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
                </div>

                <div
                    class="whitespace-nowrap rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-500 dark:bg-gray-900 dark:text-gray-400"
                >
                    Total {{ totalInbox }} Approval
                </div>
            </div>

            <!-- Table -->
            <CrudTable
                :columns="columns"
                :rows="visibleInbox"
                empty-message="Tidak ada approval yang perlu diproses."
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
                            {{ documentLabel(row.request) }}
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
                            {{
                                row.request?.workflow?.module
                                    ? moduleLabel(row.request.workflow.module)
                                    : "-"
                            }}
                        </span>
                    </div>
                </template>

                <!-- Submitter -->
                <template #cell-submitter="{ row }">
                    <div v-if="row.request?.submitter" class="flex flex-col">
                        <span
                            class="font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{ row.request.submitter.name }}
                        </span>

                        <span
                            v-if="row.request.submitter.email"
                            class="text-xs text-gray-400 dark:text-gray-500"
                        >
                            {{ row.request.submitter.email }}
                        </span>
                    </div>

                    <span v-else class="text-gray-400"> - </span>
                </template>

                <!-- Level -->
                <template #cell-level="{ row }">
                    <span
                        class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-400"
                    >
                        Level {{ row.level }}
                    </span>
                </template>

                <!-- Submitted -->
                <template #cell-submitted_at="{ row }">
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ formatDate(row.request?.submitted_at) }}
                    </span>
                </template>

                <!-- Actions -->
                <template #actions="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <!-- View -->
                        <button
                            type="button"
                            title="View"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                            @click="openDetailModal(row)"
                        >
                            <Eye class="h-4 w-4" />
                        </button>

                        <!-- Approve -->
                        <button
                            type="button"
                            title="Approve"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-emerald-600 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10"
                            :disabled="processing"
                            @click="approve(row)"
                        >
                            <Check class="h-4 w-4" />
                        </button>

                        <!-- Reject -->
                        <button
                            type="button"
                            title="Reject"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-500/10"
                            :disabled="processing"
                            @click="openRejectModal(row)"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </CrudTable>
            <AppPagination
                v-if="totalInbox > 0"
                v-model:per-page="perPage"
                v-model:page="currentPage"
                :meta="paginationMeta"
                :client-page-count="pageCount"
                @change="changePerPage"
            />
        </div>

        <!-- Approval Detail Modal -->
        <ApprovalDetailModal
            v-model:visible="showDetailModal"
            :approval="selectedApproval"
            :processing="processing"
            :show-actions="true"
            @approve="approve"
            @reject="reject"
        />
    </div>
</template>
