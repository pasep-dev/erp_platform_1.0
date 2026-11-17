<script setup lang="ts">
import { computed } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import Tag from "primevue/tag";

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

interface Props {
    workflow: ApprovalWorkflow | null;
    moduleOptions?: Array<{ label: string; value: string }>;
}

const props = withDefaults(defineProps<Props>(), {
    moduleOptions: () => [
        { label: "Purchase Request", value: "PURCHASE_REQUEST" },
        { label: "Inventory Request", value: "INVENTORY_REQUEST" },
        { label: "Inventory Opening Balance", value: "INVENTORY_OPENING_BALANCE" },
        { label: "Asset Transfer", value: "ASSET_TRANSFER" },
        { label: "Asset Disposal", value: "ASSET_DISPOSAL" },
        { label: "Asset Adjustment", value: "ASSET_ADJUSTMENT" },
    ],
});

const visible = defineModel<boolean>("visible", { default: false });

const emit = defineEmits<{
    (e: "edit", workflow: ApprovalWorkflow): void;
}>();

const moduleLabel = computed(() => {
    if (!props.workflow?.module) return "-";
    return (
        props.moduleOptions.find((m) => m.value === props.workflow?.module)
            ?.label ?? props.workflow.module
    );
});

function handleEdit() {
    if (props.workflow) {
        visible.value = false;
        emit("edit", props.workflow);
    }
}
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        header="Detail Approval Workflow"
        :style="{ width: '90vw', maxWidth: '750px' }"
        class="p-fluid"
    >
        <div v-if="workflow" class="space-y-6 pt-2">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Informasi Workflow -->
                <div
                    class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 dark:border-gray-800 dark:bg-gray-900/50"
                >
                    <h3
                        class="mb-4 text-base font-semibold text-gray-900 dark:text-white"
                    >
                        Workflow Information
                    </h3>
                    <dl class="space-y-3.5">
                        <div>
                            <dt
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                Workflow Name
                            </dt>
                            <dd
                                class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{ workflow.name }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                Module
                            </dt>
                            <dd
                                class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{ moduleLabel }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                Company
                            </dt>
                            <dd
                                class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{ workflow.company?.name ?? "-" }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                Department
                            </dt>
                            <dd
                                class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{ workflow.department?.name ?? "-" }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                Status
                            </dt>
                            <dd class="mt-1">
                                <Tag
                                    :value="
                                        workflow.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    "
                                    :severity="
                                        workflow.is_active
                                            ? 'success'
                                            : 'secondary'
                                    "
                                />
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Urutan Approval -->
                <div
                    class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 dark:border-gray-800 dark:bg-gray-900/50"
                >
                    <h3
                        class="mb-4 text-base font-semibold text-gray-900 dark:text-white"
                    >
                        Approval Sequence
                    </h3>
                    <div
                        v-if="workflow.levels?.length"
                        class="max-h-[280px] space-y-3 overflow-y-auto pr-1"
                    >
                        <div
                            v-for="level in workflow.levels"
                            :key="level.id"
                            class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-800/60"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-purple-50 text-sm font-semibold text-purple-700 dark:bg-purple-900/30 dark:text-purple-300"
                            >
                                {{ level.level }}
                            </div>
                            <div class="min-w-0">
                                <div
                                    class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{ level.approver?.name ?? "-" }}
                                </div>
                                <div
                                    class="truncate text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        level.approver?.position?.name ??
                                        "Position belum diatur"
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                    >
                        Belum ada approval level.
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button
                    label="Tutup"
                    severity="secondary"
                    outlined
                    @click="visible = false"
                />
                <Button
                    label="Edit Workflow"
                    icon="pi pi-pencil"
                    @click="handleEdit"
                />
            </div>
        </template>
    </Dialog>
</template>
