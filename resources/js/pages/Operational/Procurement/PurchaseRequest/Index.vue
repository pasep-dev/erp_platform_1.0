<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { FileText, Search } from "lucide-vue-next";
import { route } from "ziggy-js";

import AppLayout from "@/layouts/AppLayout.vue";
import Header from "@/components/content/Header.vue";
import CrudActions from "@/components/crud/CrudActions.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import DeleteConfirm from "@/components/crud/DeleteConfirm.vue";
import { useCrud } from "@/composables/useCrud";

defineOptions({
    layout: AppLayout,
});

interface User {
    id: number;
    name: string;
}

interface Company {
    id: string;
    code: string;
    name: string;
}

interface Branch {
    id: string;
    code: string;
    name: string;
}

interface Department {
    id: string;
    code: string;
    name: string;
}

interface CostCenter {
    id: string;
    code: string;
    name: string;
}

interface PurchaseRequest {
    id: string;
    number: string;
    request_date: string;
    required_date: string | null;
    purpose: string | null;
    description: string | null;
    document_status: string;
    approval_status: string;
    requested_by: User | null;
    company: Company | null;
    branch: Branch | null;
    department: Department | null;
    cost_center: CostCenter | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedPurchaseRequests {
    data: PurchaseRequest[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    purchaseRequests: PaginatedPurchaseRequests;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search);

const columns = [
    {
        key: "number",
        label: "PR Number",
    },
    {
        key: "request_date",
        label: "Request Date",
    },
    {
        key: "requested_by",
        label: "Requested By",
    },
    {
        key: "company",
        label: "Company",
    },
    {
        key: "purpose",
        label: "Purpose",
    },
    {
        key: "document_status",
        label: "Document Status",
    },
    {
        key: "approval_status",
        label: "Approval Status",
    },
];

const showDeleteConfirm = ref(false);
const selectedPurchaseRequest = ref<PurchaseRequest | null>(null);

const { processing, destroy } = useCrud({
    indexRoute: route("operational.procurement.purchase-request.index"),
    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedPurchaseRequest.value = null;
    },
});

function submitSearch(): void {
    router.get(
        route("operational.procurement.purchase-request.index"),
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

function deletePurchaseRequest(purchaseRequest: PurchaseRequest): void {
    selectedPurchaseRequest.value = purchaseRequest;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) {
        return;
    }

    showDeleteConfirm.value = false;
    selectedPurchaseRequest.value = null;
}

function confirmDelete(): void {
    if (!selectedPurchaseRequest.value) {
        return;
    }

    destroy(
        route(
            "procurement.purchase-request.destroy",
            selectedPurchaseRequest.value.id,
        ),
    );
}
</script>

<template>
    <Head title="Purchase Request" />

    <div class="space-y-6">
        <Header
            title="Purchase Request"
            :action-url="
                route('operational.procurement.purchase-request.create')
            "
            action-label="Add New"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <form
                    class="flex w-full flex-col gap-2 sm:max-w-md sm:flex-row"
                    @submit.prevent="submitSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                        />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Cari nomor PR, purpose, atau company..."
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent pl-9 pr-3 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                        />
                    </div>

                    <button
                        type="submit"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.05]"
                    >
                        Cari
                    </button>

                    <button
                        v-if="search"
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.05]"
                        @click="clearSearch"
                    >
                        Reset
                    </button>
                </form>

                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Total: {{ purchaseRequests.total }}
                </div>
            </div>

            <CrudTable
                :columns="columns"
                :rows="purchaseRequests.data"
                empty-message="Tidak ada data purchase request."
            >
                <template #cell-number="{ row }">
                    <span class="font-medium text-gray-900 dark:text-white">
                        {{ row.number }}
                    </span>
                </template>

                <template #cell-request_date="{ row }">
                    {{ row.request_date }}
                </template>

                <template #cell-requested_by="{ row }">
                    {{ row.requested_by?.name || "-" }}
                </template>

                <template #cell-company="{ row }">
                    <div>
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ row.company?.name || "-" }}
                        </div>

                        <div
                            v-if="row.company?.code"
                            class="text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ row.company.code }}
                        </div>
                    </div>
                </template>

                <template #cell-purpose="{ row }">
                    <span
                        class="block max-w-xs truncate text-gray-600 dark:text-gray-300"
                    >
                        {{ row.purpose || "-" }}
                    </span>
                </template>

                <template #cell-document_status="{ row }">
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            row.document_status === 'DRAFT'
                                ? 'bg-gray-100 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400'
                                : 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400'
                        "
                    >
                        {{ row.document_status }}
                    </span>
                </template>

                <template #cell-approval_status="{ row }">
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            row.approval_status === 'APPROVED'
                                ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400'
                                : row.approval_status === 'REJECTED'
                                  ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400'
                                  : 'bg-gray-100 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400'
                        "
                    >
                        {{ row.approval_status }}
                    </span>
                </template>

                <template #actions="{ row }">
                    <CrudActions
                        :processing="processing"
                        @edit="
                            router.visit(
                                route(
                                    'operational.procurement.purchase-request.edit',
                                    row.id,
                                ),
                            )
                        "
                        @delete="deletePurchaseRequest(row)"
                    />
                </template>
            </CrudTable>

            <div
                v-if="purchaseRequests.last_page > 1"
                class="flex flex-col gap-3 border-t border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ purchaseRequests.from }}–{{
                        purchaseRequests.to
                    }}
                    dari {{ purchaseRequests.total }} data
                </p>

                <div class="flex flex-wrap gap-1">
                    <template
                        v-for="link in purchaseRequests.links"
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
                        />
                    </template>
                </div>
            </div>
        </div>

        <DeleteConfirm
            :show="showDeleteConfirm"
            :processing="processing"
            title="Hapus Purchase Request"
            :message="
                selectedPurchaseRequest
                    ? `Apakah Anda yakin ingin menghapus purchase request '${selectedPurchaseRequest.number}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
