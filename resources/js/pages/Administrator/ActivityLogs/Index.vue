<script setup lang="ts">
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import AppPagination from "@/components/common/AppPagination.vue";
import AppSelect from "@/components/common/AppSelect.vue";
import Modal from "@/components/common/Modal.vue";
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";
import Header from "@/components/content/Header.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import AppLayout from "@/layout/AppLayout.vue";

defineOptions({ layout: AppLayout });

interface ActivityLogUser {
    id: number;
    name: string;
    email: string;
}

interface ActivityLog {
    id: string;
    action: string;
    module: string;
    subject_type: string | null;
    subject_id: string | null;
    description: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    request_id: string | null;
    created_at: string;
    user: ActivityLogUser | null;
}

interface ActivityLogChange {
    field: string;
    oldValue: unknown;
    newValue: unknown;
}

interface PaginatedLogs {
    data: ActivityLog[];
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
    logs: PaginatedLogs;
    filters: {
        search: string;
        module: string;
        action: string;
        start_date: string | null;
        end_date: string | null;
        per_page: number;
    };
    options: {
        modules?: string[];
        actions?: string[];
    };
}>();

const allFilterValue = "ALL";
const search = ref(props.filters.search);
const selectedModule = ref(props.filters.module || allFilterValue);
const selectedAction = ref(props.filters.action || allFilterValue);
const startDate = ref(props.filters.start_date || "");
const endDate = ref(props.filters.end_date || "");
const perPage = ref(props.filters.per_page);
const selectedLog = ref<ActivityLog | null>(null);

const columns = [
    { key: "created_at", label: "Date & Time" },
    { key: "user", label: "User" },
    { key: "module", label: "Module" },
    { key: "action", label: "Action" },
    { key: "description", label: "Description" },
    { key: "subject", label: "Record" },
    { key: "ip_address", label: "IP Address" },
];

// Menggunakan optional chaining (?.) dan fallback ([]) agar aman jika options masih undefined
const moduleOptions = [
    { label: "All Modules", value: allFilterValue },
    ...(props.options?.modules ?? []).map((module) => ({
        label: module,
        value: module,
    })),
];

const actionOptions = [
    { label: "All Actions", value: allFilterValue },
    ...(props.options?.actions ?? []).map((action) => ({
        label: action,
        value: action,
    })),
];

function submitSearch(): void {
    router.get(
        route("administrator.activity-logs.index"),
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
            start_date: startDate.value || undefined,
            end_date: endDate.value || undefined,
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
    startDate.value = "";
    endDate.value = "";
    perPage.value = 15;
    submitSearch();
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}

function formatSubject(log: ActivityLog): string {
    if (!log.subject_id) {
        return "—";
    }

    const type = log.subject_type?.split("\\").pop();

    return type ? `${type} #${log.subject_id}` : log.subject_id;
}

function formatValues(values: Record<string, unknown> | null): string {
    return values ? JSON.stringify(values, null, 2) : "—";
}

function formatChanges(log: ActivityLog): ActivityLogChange[] {
    const oldValues = log.old_values ?? {};
    const newValues = log.new_values ?? {};

    return Object.keys(newValues)
        .filter((field) => Object.prototype.hasOwnProperty.call(oldValues, field))
        .map((field) => ({
            field,
            oldValue: oldValues[field],
            newValue: newValues[field],
        }));
}

function formatChangeValue(value: unknown): string {
    if (value === null || value === undefined || value === "") {
        return "—";
    }

    return typeof value === "object" ? JSON.stringify(value) : String(value);
}
</script>

<template>
    <div class="space-y-6">
        <Header
            title="Activity Logs"
            description="Review recorded user activity across authentication, administration, master data, approvals, and transactions."
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-800"
            >
                <div class="flex flex-1 flex-wrap items-center gap-3">
                    <SearchFilterBar
                        v-model="search"
                        placeholder="Search user, description, or record..."
                        class="w-full sm:w-64"
                        @search="submitSearch"
                        @clear="clearSearch"
                    />
                    <AppSelect
                        v-model="selectedModule"
                        :options="moduleOptions"
                        class="w-full sm:w-48"
                        @change="submitSearch"
                    />
                    <AppSelect
                        v-model="selectedAction"
                        :options="actionOptions"
                        class="w-full sm:w-40"
                        @change="submitSearch"
                    />

                    <!-- Input Filter Rentang Tanggal -->
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <input
                            type="date"
                            v-model="startDate"
                            @change="submitSearch"
                            class="w-full sm:w-auto rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        />
                        <span class="text-xs text-gray-500">s/d</span>
                        <input
                            type="date"
                        v-model="endDate"
                            @change="submitSearch"
                            class="w-full sm:w-auto rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        />
                    </div>
                </div>

                <div
                    class="whitespace-nowrap rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-500 dark:bg-gray-900 dark:text-gray-400"
                >
                    Total {{ logs.total }} Activities
                </div>
            </div>

            <CrudTable
                :columns="columns"
                :rows="logs.data"
                empty-message="No activity logs found."
            >
                <template #cell-created_at="{ row }">
                    <span class="whitespace-nowrap text-xs">
                        {{ formatDate(row.created_at) }}
                    </span>
                </template>

                <template #cell-user="{ row }">
                    <div class="flex min-w-36 flex-col">
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ row.user?.name ?? "System / Deleted User" }}
                        </span>
                        <span
                            v-if="row.user?.email"
                            class="text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ row.user.email }}
                        </span>
                    </div>
                </template>

                <template #cell-module="{ row }">
                    <span
                        class="inline-flex whitespace-nowrap rounded-full bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700 dark:bg-purple-500/10 dark:text-purple-300"
                    >
                        {{ row.module }}
                    </span>
                </template>

                <template #cell-action="{ row }">
                    <span
                        class="inline-flex whitespace-nowrap rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{ row.action }}
                    </span>
                </template>

                <template #cell-description="{ row }">
                    <div class="min-w-48 max-w-md">
                        <span class="block text-sm">
                            {{ row.description ?? "—" }}
                        </span>
                        <span
                            v-if="formatChanges(row).length"
                            class="mt-1 block text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{
                                formatChanges(row)
                                    .map(
                                        (change) =>
                                            `${change.field}: ${formatChangeValue(change.oldValue)} → ${formatChangeValue(change.newValue)}`,
                                    )
                                    .join("; ")
                            }}
                        </span>
                    </div>
                </template>

                <template #cell-subject="{ row }">
                    <span class="whitespace-nowrap text-xs">
                        {{ formatSubject(row) }}
                    </span>
                </template>

                <template #cell-ip_address="{ row }">
                    <span class="whitespace-nowrap text-xs">
                        {{ row.ip_address ?? "—" }}
                    </span>
                </template>

                <template #actions="{ row }">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-500/10"
                        @click="selectedLog = row"
                    >
                        Details
                    </button>
                </template>
            </CrudTable>

            <AppPagination
                v-model:per-page="perPage"
                :meta="logs"
                @change="submitSearch"
            />
        </div>

        <Modal
            :show="selectedLog !== null"
            title="Activity Log Details"
            max-width="3xl"
            @close="selectedLog = null"
        >
            <div v-if="selectedLog" class="space-y-5">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Action</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ selectedLog.action }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Module</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ selectedLog.module }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Record</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ formatSubject(selectedLog) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Request ID</dt>
                        <dd class="mt-1 break-all text-sm text-gray-900 dark:text-white">
                            {{ selectedLog.request_id ?? "—" }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-gray-500">Description</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ selectedLog.description ?? "—" }}
                        </dd>
                    </div>
                    <div v-if="formatChanges(selectedLog).length" class="sm:col-span-2">
                        <dt class="text-xs font-medium text-gray-500">Changes</dt>
                        <dd class="mt-1 space-y-1 text-sm text-gray-900 dark:text-white">
                            <div
                                v-for="change in formatChanges(selectedLog)"
                                :key="change.field"
                            >
                                <span class="font-medium">{{ change.field }}:</span>
                                {{ formatChangeValue(change.oldValue) }}
                                <span aria-hidden="true">→</span>
                                {{ formatChangeValue(change.newValue) }}
                            </div>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-gray-500">User Agent</dt>
                        <dd class="mt-1 break-all text-xs text-gray-700 dark:text-gray-300">
                            {{ selectedLog.user_agent ?? "—" }}
                        </dd>
                    </div>
                </dl>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">
                            Previous Values
                        </h3>
                        <pre class="max-h-72 overflow-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700 dark:bg-gray-950 dark:text-gray-300">{{ formatValues(selectedLog.old_values) }}</pre>
                    </div>
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">
                            New Values
                        </h3>
                        <pre class="max-h-72 overflow-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700 dark:bg-gray-950 dark:text-gray-300">{{ formatValues(selectedLog.new_values) }}</pre>
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>