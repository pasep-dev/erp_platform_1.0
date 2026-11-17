<script setup lang="ts">
import { computed, ref } from "vue";

import Dialog from "primevue/dialog";
import Button from "primevue/button";
import Tag from "primevue/tag";
import { Link } from "@inertiajs/vue3";
import { route } from "ziggy-js";

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

interface ApprovalInboxItem {
    id: string;
    level: number;
    approver_user_id: number;
    status: string;
    acted_at?: string | null;
    remarks?: string | null;
    approver?: User;
    request?: ApprovalRequest;
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

type ApprovalSource = ApprovalInboxItem | ApprovalAction;

interface Props {
    approval: ApprovalSource | null;
    processing?: boolean;
    showActions?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    processing: false,
    showActions: false,
});

const visible = defineModel<boolean>("visible", {
    default: false,
});

const emit = defineEmits<{
    (e: "approve", approval: ApprovalSource): void;
    (e: "reject", approval: ApprovalSource, remarks: string): void;
}>();

const rejectVisible = ref(false);
const rejectRemarks = ref("");

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

const request = computed(() => {
    return props.approval?.request ?? null;
});

const moduleLabel = computed(() => {
    if (!request.value?.module) {
        return "-";
    }

    return (
        moduleOptions.find((item) => item.value === request.value?.module)
            ?.label ?? request.value.module
    );
});

const approvalLevels = computed(() => {
    if (!request.value?.levels?.length) {
        return [];
    }

    return [...request.value.levels].sort((a, b) => a.level - b.level);
});

const isInboxApproval = computed(() => {
    if (!props.approval) {
        return false;
    }

    return "approver_user_id" in props.approval;
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

function statusLabel(status?: string): string {
    switch (status) {
        case "WAITING":
            return "Waiting";

        case "PENDING":
            return "Pending";

        case "APPROVED":
            return "Approved";

        case "REJECTED":
            return "Rejected";

        default:
            return status ?? "-";
    }
}

function statusSeverity(
    status?: string,
): "success" | "warn" | "danger" | "secondary" {
    switch (status) {
        case "APPROVED":
            return "success";

        case "PENDING":
            return "warn";

        case "REJECTED":
            return "danger";

        case "WAITING":
        default:
            return "secondary";
    }
}

function levelDotClass(status?: string): string {
    switch (status) {
        case "APPROVED":
            return "bg-emerald-500 text-white";

        case "REJECTED":
            return "bg-red-500 text-white";

        case "PENDING":
            return "bg-amber-500 text-white";

        case "WAITING":
        default:
            return "bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-300";
    }
}

function levelLineClass(status?: string): string {
    switch (status) {
        case "APPROVED":
            return "bg-emerald-500";

        case "REJECTED":
            return "bg-red-500";

        default:
            return "bg-gray-200 dark:bg-gray-700";
    }
}

function handleApprove(): void {
    if (!props.approval || props.processing) {
        return;
    }

    emit("approve", props.approval);
}

function openReject(): void {
    if (!props.approval || props.processing) {
        return;
    }

    rejectRemarks.value = "";
    rejectVisible.value = true;
}

function closeReject(): void {
    if (props.processing) {
        return;
    }

    rejectVisible.value = false;
    rejectRemarks.value = "";
}

function handleReject(): void {
    if (!props.approval || props.processing) {
        return;
    }

    if (!rejectRemarks.value.trim()) {
        return;
    }

    emit("reject", props.approval, rejectRemarks.value.trim());
}

function closeDetail(): void {
    if (props.processing) {
        return;
    }

    visible.value = false;
}
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        header="Detail Approval"
        :style="{
            width: '95vw',
            maxWidth: '1100px',
        }"
        class="p-fluid"
    >
        <div v-if="approval && request" class="space-y-6 pt-2">
            <!-- ========================================================= -->
            <!-- Approval Information -->
            <!-- ========================================================= -->

            <div
                class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 dark:border-gray-800 dark:bg-gray-900/50"
            >
                <div
                    class="mb-5 flex flex-col gap-3 border-b border-gray-200 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
                >
                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Document
                        </p>

                        <h2
                            class="mt-1 text-xl font-bold text-gray-900 dark:text-white"
                        >
                            {{ request.document_id }}
                        </h2>

                        <p
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ request.document_type }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            v-if="request.module === 'INVENTORY_REQUEST'"
                            :href="route('transaction.inventory.requests.show', request.document_id)"
                            class="rounded-lg border border-violet-300 px-3 py-2 text-sm font-medium text-violet-700 hover:bg-violet-50 dark:border-violet-800 dark:text-violet-300 dark:hover:bg-violet-950/30"
                        >
                            Buka Permintaan
                        </Link>
                        <Tag
                            :value="statusLabel(request.status)"
                            :severity="statusSeverity(request.status)"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-x-8 gap-y-4 md:grid-cols-2">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Module
                        </p>

                        <p
                            class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                        >
                            {{ moduleLabel }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Workflow
                        </p>

                        <p
                            class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                        >
                            {{ request.workflow?.name ?? "-" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Company
                        </p>

                        <p
                            class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{ request.workflow?.company?.name ?? "-" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Department
                        </p>

                        <p
                            class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{ request.workflow?.department?.name ?? "-" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Submitted By
                        </p>

                        <p
                            class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{ request.submitter?.name ?? "-" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Submitted At
                        </p>

                        <p
                            class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{ formatDate(request.submitted_at) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- Approval Tracking -->
            <!-- ========================================================= -->

            <div
                class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900/30"
            >
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h3
                            class="text-base font-semibold text-gray-900 dark:text-white"
                        >
                            Approval Tracking
                        </h3>

                        <p
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Perjalanan approval dokumen berdasarkan approval
                            level.
                        </p>
                    </div>

                    <span
                        class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{ approvalLevels.length }} Level
                    </span>
                </div>

                <div v-if="approvalLevels.length" class="overflow-x-auto pb-2">
                    <div class="flex min-w-[720px] items-start">
                        <template
                            v-for="(level, index) in approvalLevels"
                            :key="level.id"
                        >
                            <!-- Level -->
                            <div
                                class="flex w-56 shrink-0 flex-col items-center text-center"
                            >
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-full text-sm font-bold shadow-sm ring-4 ring-white dark:ring-gray-900"
                                    :class="levelDotClass(level.status)"
                                >
                                    <span v-if="level.status === 'APPROVED'">
                                        ✓
                                    </span>

                                    <span
                                        v-else-if="level.status === 'REJECTED'"
                                    >
                                        ×
                                    </span>

                                    <span v-else>
                                        {{ level.level }}
                                    </span>
                                </div>

                                <div class="mt-3">
                                    <p
                                        class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Level {{ level.level }}
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                                    >
                                        {{ level.approver?.name ?? "-" }}
                                    </p>

                                    <p
                                        v-if="level.approver?.email"
                                        class="mt-0.5 truncate text-xs text-gray-400 dark:text-gray-500"
                                    >
                                        {{ level.approver.email }}
                                    </p>

                                    <div class="mt-2">
                                        <Tag
                                            :value="statusLabel(level.status)"
                                            :severity="
                                                statusSeverity(level.status)
                                            "
                                        />
                                    </div>

                                    <p
                                        v-if="level.acted_at"
                                        class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ formatDate(level.acted_at) }}
                                    </p>

                                    <div
                                        v-if="level.remarks"
                                        class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-left dark:border-gray-800 dark:bg-gray-800/50"
                                    >
                                        <p
                                            class="text-[11px] font-semibold uppercase tracking-wide text-gray-400"
                                        >
                                            Remarks
                                        </p>

                                        <p
                                            class="mt-1 whitespace-pre-wrap text-xs text-gray-700 dark:text-gray-300"
                                        >
                                            {{ level.remarks }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Connector -->
                            <div
                                v-if="index < approvalLevels.length - 1"
                                class="mt-6 h-1 min-w-16 flex-1 rounded-full"
                                :class="levelLineClass(level.status)"
                            />
                        </template>
                    </div>
                </div>

                <div
                    v-else
                    class="py-10 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    Belum ada approval level.
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button
                    label="Tutup"
                    severity="secondary"
                    outlined
                    :disabled="processing"
                    @click="closeDetail"
                />

                <template v-if="showActions && isInboxApproval">
                    <Button
                        label="Reject"
                        icon="pi pi-times"
                        severity="danger"
                        :loading="processing"
                        :disabled="processing"
                        @click="openReject"
                    />

                    <Button
                        label="Approve"
                        icon="pi pi-check"
                        severity="success"
                        :loading="processing"
                        :disabled="processing"
                        @click="handleApprove"
                    />
                </template>
            </div>
        </template>
    </Dialog>

    <!-- Reject Confirmation -->
    <Dialog
        v-model:visible="rejectVisible"
        modal
        header="Reject Approval"
        :style="{
            width: '90vw',
            maxWidth: '500px',
        }"
        class="p-fluid"
    >
        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Berikan alasan penolakan dokumen.
            </p>

            <div>
                <label
                    for="approval-reject-remarks"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Remarks
                </label>

                <textarea
                    id="approval-reject-remarks"
                    v-model="rejectRemarks"
                    rows="5"
                    maxlength="1000"
                    placeholder="Masukkan alasan penolakan..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                />
            </div>
        </div>

        <template #footer>
            <Button
                label="Batal"
                severity="secondary"
                outlined
                :disabled="processing"
                @click="closeReject"
            />

            <Button
                label="Reject"
                icon="pi pi-times"
                severity="danger"
                :loading="processing"
                :disabled="processing || !rejectRemarks.trim()"
                @click="handleReject"
            />
        </template>
    </Dialog>
</template>
