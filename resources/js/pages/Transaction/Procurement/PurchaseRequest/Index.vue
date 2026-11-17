<script setup lang="ts">
import { computed, ref } from "vue";
import { router } from "@inertiajs/vue3";
import Button from "primevue/button";
import Tag from "primevue/tag";
import ConfirmDialog from "primevue/confirmdialog";
import { useConfirm } from "primevue/useconfirm";
import CrudTable from "@/components/crud/CrudTable.vue";
import AppPagination from "@/components/common/AppPagination.vue";
import ProcurementPageHeader from "@/components/procurement/ProcurementPageHeader.vue";
import AppSelect from "@/components/common/AppSelect.vue";
import DataTableToolbar from "@/components/common/DataTableToolbar.vue";

import AppLayout from "@/layout/AppLayout.vue";

defineOptions({
    layout: AppLayout,
});

interface PurchaseRequest {
    id: string;
    number: string;
    request_date: string;
    purpose: string;
    status: string;
    submitted_at?: string | null;
    can_edit: boolean;
    can_delete: boolean;
    can_print: boolean;
    can_cancel: boolean;
    is_locked: boolean;
    requester?: {
        id: number;
        name: string;
    };
    company?: {
        id: string;
        name: string;
    };
    department?: {
        id: string;
        name: string;
        code?: string;
    };
}

interface PaginatedPurchaseRequests {
    data: PurchaseRequest[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    purchaseRequests: PaginatedPurchaseRequests;
    filters: {
        search?: string;
        status?: string;
    };
}>();

const confirm = useConfirm();
const search = ref(props.filters.search ?? "");
const allStatusValue = "ALL";
const selectedStatus = ref(props.filters.status || allStatusValue);
const perPage = ref(props.purchaseRequests.per_page);

const statusOptions = [
    { label: "Semua Status", value: allStatusValue },
    { label: "Draft", value: "DRAFT" },
    { label: "Submitted", value: "SUBMITTED" },
    { label: "Approved", value: "APPROVED" },
    { label: "Rejected", value: "REJECTED" },
    { label: "Cancelled", value: "CANCELLED" },
];

const columns = [
    {
        key: "number",
        label: "PR Number",
        class: "w-[15%]",
    },
    {
        key: "request_date",
        label: "Tanggal",
        class: "w-[12%]",
    },
    {
        key: "requester",
        label: "Requester",
        class: "w-[14%]",
    },
    {
        key: "department",
        label: "Department",
        class: "w-[17%]",
    },
    {
        key: "purpose",
        label: "Keperluan",
        class: "w-[14%]",
    },
    {
        key: "status",
        label: "Status",
        class: "w-[12%]",
    },
];

const submitSearch = () => {
    router.get(
        route("transaction.procurement.purchase-requests.index"),
        {
            search: search.value || undefined,
            status: selectedStatus.value !== allStatusValue ? selectedStatus.value : undefined,
            per_page: perPage.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const resetSearch = () => {
    search.value = "";
    selectedStatus.value = allStatusValue;
    submitSearch();
};

const openCreate = () => {
    router.get(route("transaction.procurement.purchase-requests.create"));
};

const openShow = (row: PurchaseRequest) => {
    router.get(route("transaction.procurement.purchase-requests.show", row.id));
};

const openEdit = (row: PurchaseRequest) => {
    router.get(route("transaction.procurement.purchase-requests.edit", row.id));
};

const printPurchaseRequest = (row: PurchaseRequest) => {
    const printUrl = new URL(
        route("transaction.procurement.purchase-requests.show", row.id),
        window.location.origin,
    );
    printUrl.searchParams.set("print", "1");
    printUrl.searchParams.set("closeAfterPrint", "1");
    window.open(printUrl.toString(), "_blank", "noopener");
};

const confirmDelete = (row: PurchaseRequest) => {
    confirm.require({
        message: `Draft Purchase Request ${row.number} akan dihapus permanen. Lanjutkan?`,
        header: "Hapus Draft Purchase Request",
        icon: "pi pi-trash",
        rejectLabel: "Batal",
        acceptLabel: "Hapus",
        acceptClass: "p-button-danger",
        accept: () => {
            router.delete(
                route("transaction.procurement.purchase-requests.destroy", row.id),
                { preserveScroll: true },
            );
        },
    });
};

const confirmCancel = (row: PurchaseRequest) => {
    confirm.require({
        message: `Purchase Request ${row.number} akan dibatalkan. Lanjutkan?`,
        header: "Batalkan Purchase Request",
        icon: "pi pi-ban",
        rejectLabel: "Kembali",
        acceptLabel: "Batalkan PR",
        acceptClass: "p-button-danger",
        accept: () => {
            router.post(
                route("transaction.procurement.purchase-requests.cancel", row.id),
                {},
                { preserveScroll: true },
            );
        },
    });
};

const statusSeverity = computed(() => {
    return (status: string) => {
        switch (status) {
            case "APPROVED":
                return "success";
            case "REJECTED":
                return "danger";
            case "SUBMITTED":
                return "warn";
            case "CANCELLED":
                return "danger";
            default:
                return "secondary";
        }
    };
});

const formatDate = (value: string) => {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
    }).format(new Date(value));
};
</script>

<template>
    <div class="procurement-ui space-y-6">
        <ConfirmDialog />
        <ProcurementPageHeader
            title="Purchase Request"
            section="Transactions"
            description="Kelola pengajuan kebutuhan pembelian barang dan jasa."
        >
            <template #actions>
                <Button
                    label="Buat Purchase Request"
                    icon="pi pi-plus"
                    @click="openCreate"
                />
            </template>
        </ProcurementPageHeader>

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <DataTableToolbar
                v-model="search"
                :total="purchaseRequests.total"
                total-label="Purchase Request"
                placeholder="Cari nomor PR / requester / keperluan..."
                @search="submitSearch"
            >
                <AppSelect
                    v-model="selectedStatus"
                    :options="statusOptions"
                    class="w-full sm:w-48"
                    aria-label="Filter status Purchase Request"
                    @change="submitSearch"
                />
            </DataTableToolbar>

            <CrudTable
                :columns="columns"
                :rows="purchaseRequests.data"
                table-class="table-fixed min-w-[980px]"
                empty-message="Belum ada Purchase Request."
            >
                <template #cell-number="{ row }">
                    <button
                        type="button"
                        class="font-medium text-brand-600 hover:underline dark:text-brand-400"
                        @click="openShow(row)"
                    >
                        {{ row.number }}
                    </button>
                </template>

                <template #cell-request_date="{ row }">
                    {{ formatDate(row.request_date) }}
                </template>

                <template #cell-requester="{ row }">
                    {{ row.requester?.name ?? "-" }}
                </template>

                <template #cell-department="{ row }">
                    <div>
                        <div class="font-medium">
                            {{ row.department?.name ?? "-" }}
                        </div>

                        <div
                            v-if="row.department?.code"
                            class="text-xs text-gray-400"
                        >
                            {{ row.department.code }}
                        </div>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <Tag
                        :value="row.status"
                        :severity="statusSeverity(row.status)"
                    />
                </template>

                <template #actions="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <Button
                            icon="pi pi-eye"
                            severity="secondary"
                            text
                            rounded
                            aria-label="Lihat Purchase Request"
                            v-tooltip.top="'Lihat'"
                            @click="openShow(row)"
                        />
                        <Button
                            v-if="row.can_edit"
                            icon="pi pi-pencil"
                            severity="secondary"
                            text
                            rounded
                            aria-label="Edit Draft Purchase Request"
                            v-tooltip.top="'Edit'"
                            @click="openEdit(row)"
                        />
                        <Button
                            v-if="row.can_delete"
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            rounded
                            aria-label="Hapus Draft Purchase Request"
                            v-tooltip.top="'Hapus'"
                            @click="confirmDelete(row)"
                        />
                        <Button
                            v-if="row.status === 'SUBMITTED' && row.can_print"
                            icon="pi pi-print"
                            severity="secondary"
                            text
                            rounded
                            aria-label="Print Purchase Request"
                            v-tooltip.top="'Print'"
                            @click="printPurchaseRequest(row)"
                        />
                        <Button
                            v-if="row.can_cancel"
                            icon="pi pi-ban"
                            severity="danger"
                            text
                            rounded
                            aria-label="Batalkan Purchase Request"
                            v-tooltip.top="'Cancel'"
                            @click="confirmCancel(row)"
                        />
                        <span
                            v-if="row.is_locked"
                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                        >
                            <i class="pi pi-lock" aria-hidden="true" />
                            Locked
                        </span>
                    </div>
                </template>
            </CrudTable>

            <AppPagination
                v-if="purchaseRequests.total > 0"
                v-model:per-page="perPage"
                :meta="purchaseRequests"
                @change="submitSearch"
            />
        </div>
    </div>
</template>
