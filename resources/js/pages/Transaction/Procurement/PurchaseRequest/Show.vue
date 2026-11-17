<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import Tag from "primevue/tag";
import ProcurementPageHeader from "@/components/procurement/ProcurementPageHeader.vue";
import Textarea from "primevue/textarea";
import ConfirmDialog from "primevue/confirmdialog";
import { useConfirm } from "primevue/useconfirm";
import { route } from "ziggy-js";

import AppLayout from "@/layout/AppLayout.vue";

defineOptions({
    layout: AppLayout,
});

interface PurchaseRequestItem {
    id: string;
    item_type: "PRODUCT" | "SERVICE";
    description?: string | null;
    quantity: string | number;
    estimated_unit_price: string | number | null;
    delivery_address?: string | null;
    required_date?: string | null;
    remarks?: string | null;
    tax_included: boolean;
    requires_po: boolean;
    product?: {
        id: string;
        code: string;
        name: string;
    };
    service_type?: {
        id: string;
        code: string;
        name: string;
    };
    uom?: {
        id: string;
        code: string;
        name: string;
    };
    vendor?: {
        id: string;
        code: string;
        name: string;
    };
    vendor_pic?: string | null;
    vendor_contact?: string | null;
    vendor_url?: string | null;
}

interface PurchaseRequest {
    id: string;
    number: string;
    request_date: string;
    purpose: string;
    remarks?: string | null;
    status: string;
    submitted_at?: string | null;
    approved_at?: string | null;
    rejected_at?: string | null;
    requester?: {
        id: number;
        name: string;
        email?: string;
        position?: { name: string } | null;
    };
    company?: {
        id: string;
        name: string;
        code?: string;
    };
    department?: {
        id: string;
        name: string;
        code?: string;
    };
    items: PurchaseRequestItem[];
}

interface ApprovalLevel {
    level: number;
    status: "WAITING" | "PENDING" | "APPROVED" | "REJECTED" | "CANCELLED";
    approver?: {
        id: number;
        name: string;
        position?: { name: string } | null;
    } | null;
    acted_at?: string | null;
    remarks?: string | null;
}

interface PurchaseRequestApproval {
    workflow_name?: string | null;
    status: string;
    current_level: number | null;
    levels: ApprovalLevel[];
    manual_approval_remarks?: string | null;
    manual_approved_at?: string | null;
    manual_approver?: { id: number; name: string } | null;
    has_manual_approval_document: boolean;
}

const props = defineProps<{
    purchaseRequest: PurchaseRequest;
    canMarkApproval: boolean;
    approval: PurchaseRequestApproval | null;
}>();

const page = usePage();
const confirm = useConfirm();
const approvalDialogVisible = ref(false);
const approvalForm = useForm({
    remarks: "",
    signed_document: null as File | null,
});

const canSubmit = computed(
    () =>
        props.purchaseRequest.status === "DRAFT" &&
        props.purchaseRequest.requester?.id === page.props.auth?.user?.id,
);
const canPrint = computed(() => props.purchaseRequest.status === "SUBMITTED");

const statusSeverity = computed(() => {
    switch (props.purchaseRequest.status) {
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
});

const statusLabel = computed(() => {
    switch (props.purchaseRequest.status) {
        case "SUBMITTED":
            return "Menunggu Approval Manual";
        case "APPROVED":
            return "Disetujui";
        case "REJECTED":
            return "Ditolak";
        case "CANCELLED":
            return "Dibatalkan";
        default:
            return "Draft";
    }
});

let closeTabAfterPrint = false;

const closePrintTab = () => {
    if (!closeTabAfterPrint) {
        return;
    }

    window.close();

    window.setTimeout(() => {
        if (!window.closed) {
            window.location.replace("about:blank");
        }
    }, 100);
};

onMounted(() => {
    const printParameters = new URLSearchParams(window.location.search);
    closeTabAfterPrint = printParameters.get("closeAfterPrint") === "1";

    if (canPrint.value && printParameters.get("print") === "1") {
        if (closeTabAfterPrint) {
            window.addEventListener("afterprint", closePrintTab, {
                once: true,
            });
        }

        window.setTimeout(() => window.print(), 250);
    }
});

const itemCount = computed(() => props.purchaseRequest.items.length);

const totalQuantity = computed(() =>
    props.purchaseRequest.items.reduce(
        (total, item) => total + Number(item.quantity || 0),
        0,
    ),
);

const hasMissingPrices = computed(() =>
    props.purchaseRequest.items.some(
        (item) => Number(item.estimated_unit_price || 0) <= 0,
    ),
);

const subtotal = computed(() =>
    props.purchaseRequest.items.reduce(
        (total, item) =>
            total +
            Number(item.quantity || 0) *
                Number(item.estimated_unit_price || 0),
        0,
    ),
);

const taxableSubtotal = computed(() =>
    props.purchaseRequest.items.reduce(
        (total, item) =>
            total +
            (item.tax_included
                ? 0
                : Number(item.quantity || 0) *
                    Number(item.estimated_unit_price || 0)),
        0,
    ),
);

const estimatedTax = computed(() =>
    Math.round(taxableSubtotal.value * 0.11),
);

const grandTotal = computed(() => subtotal.value + estimatedTax.value);

const formatPrintAmount = (value: number) =>
    new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 0,
    }).format(value);

const hasBeenSubmitted = computed(
    () => props.purchaseRequest.status !== "DRAFT",
);

const isApproved = computed(
    () => props.purchaseRequest.status === "APPROVED",
);

const isRejected = computed(
    () => props.purchaseRequest.status === "REJECTED",
);

const formatDate = (value?: string | null) => {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
    }).format(new Date(value));
};

const formatCurrency = (value: number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(value);

const formatQuantity = (value: string | number) =>
    new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 3,
    }).format(Number(value));

const itemName = (item: PurchaseRequestItem) =>
    item.item_type === "SERVICE"
        ? item.service_type?.name ?? "Jasa"
        : item.product?.name ?? "-";

const itemCode = (item: PurchaseRequestItem, index: number) =>
    item.item_type === "SERVICE"
        ? item.service_type?.code ?? `Jasa ${index + 1}`
        : item.product?.code ?? `Item ${index + 1}`;

const itemEstimatedTotal = (item: PurchaseRequestItem) =>
    Number(item.quantity || 0) * Number(item.estimated_unit_price || 0);

const itemHasPrice = (item: PurchaseRequestItem) =>
    Number(item.estimated_unit_price || 0) > 0;

const submitPurchaseRequest = () => {
    confirm.require({
        message:
            "Purchase Request akan dikirim ke alur persetujuan. Lanjutkan?",
        header: "Ajukan Purchase Request",
        icon: "pi pi-send",
        rejectLabel: "Kembali",
        acceptLabel: "Ajukan",
        accept: () => {
            router.post(
                route(
                    "transaction.procurement.purchase-requests.submit",
                    props.purchaseRequest.id,
                ),
            );
        },
    });
};

const printPurchaseRequest = () => {
    window.print();
};

const setSignedDocument = (event: Event) => {
    const input = event.target as HTMLInputElement;
    approvalForm.signed_document = input.files?.[0] ?? null;
};

const submitManualApproval = () => {
    approvalForm.post(
        route(
            "transaction.procurement.purchase-requests.mark-approved",
            props.purchaseRequest.id,
        ),
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                approvalDialogVisible.value = false;
                approvalForm.reset();
            },
        },
    );
};

const back = () => {
    router.get(route("transaction.procurement.purchase-requests.index"));
};

const editDraft = () => {
    router.get(
        route("transaction.procurement.purchase-requests.edit", props.purchaseRequest.id),
    );
};
</script>

<template>
    <div class="procurement-ui">
        <Head :title="`Purchase Request ${purchaseRequest.number}`" />
        <ConfirmDialog />

        <section class="purchase-request-print-sheet" aria-label="Dokumen cetak Purchase Request">
            <header class="print-heading">
                <h1>PURCHASE REQUEST</h1>
            </header>

            <table class="print-meta-table">
                <tbody>
                    <tr>
                        <th>Date</th>
                        <td>{{ formatDate(purchaseRequest.request_date) }}</td>
                        <th>PR No.</th>
                        <td>{{ purchaseRequest.number }}</td>
                    </tr>
                    <tr>
                        <th>Prepared by</th>
                        <td>{{ purchaseRequest.requester?.name ?? "-" }}</td>
                        <th>Purpose</th>
                        <td>{{ purchaseRequest.purpose }}</td>
                    </tr>
                    <tr>
                        <th>Requested by</th>
                        <td>
                            {{ purchaseRequest.requester?.name ?? "-" }}
                            <small>{{ purchaseRequest.requester?.position?.name ?? purchaseRequest.department?.name ?? "" }}</small>
                        </td>
                        <th>Status</th>
                        <td>
                            <span :class="purchaseRequest.status === 'SUBMITTED' || purchaseRequest.status === 'APPROVED' ? 'print-status-submitted' : 'print-status-draft'">
                                {{ purchaseRequest.status === "SUBMITTED" ? "SUBMITTED DIGITAL" : statusLabel.toUpperCase() }}
                            </span>
                            <small v-if="purchaseRequest.submitted_at">{{ formatDate(purchaseRequest.submitted_at) }}</small>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="print-items-table">
                <colgroup>
                    <col class="print-col-number" />
                    <col class="print-col-description" />
                    <col class="print-col-quantity" />
                    <col class="print-col-unit" />
                    <col class="print-col-price" />
                    <col class="print-col-amount" />
                </colgroup>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Unit Price<br />(IDR)</th>
                        <th>Amount<br />(IDR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in purchaseRequest.items" :key="item.id">
                        <td class="print-center">{{ index + 1 }}</td>
                        <td class="print-description">
                            <strong>{{ itemName(item) }}</strong>
                            <small>{{ itemCode(item, index) }}<span v-if="item.description"> · {{ item.description }}</span></small>
                            <small>Need to issue PO: {{ item.requires_po ? "Yes" : "No" }}</small>
                            <small>Vendor: {{ item.vendor?.name ?? "-" }}</small>
                            <small v-if="item.vendor_pic">Vendor PIC: {{ item.vendor_pic }}</small>
                            <small v-if="item.vendor_contact">Vendor Contact: {{ item.vendor_contact }}</small>
                            <small v-if="item.vendor_url">Vendor URL: {{ item.vendor_url }}</small>
                            <small v-if="item.required_date">Required Date: {{ formatDate(item.required_date) }}</small>
                            <small v-if="item.delivery_address">Delivery Address: {{ item.delivery_address }}</small>
                            <small v-if="item.remarks">Remarks: {{ item.remarks }}</small>
                        </td>
                        <td class="print-center">{{ formatQuantity(item.quantity) }}</td>
                        <td class="print-center">{{ item.uom?.code ?? item.uom?.name ?? "-" }}</td>
                        <td class="print-number">
                            {{ itemHasPrice(item) ? formatPrintAmount(Number(item.estimated_unit_price)) : "-" }}
                        </td>
                        <td class="print-number">
                            {{ itemHasPrice(item) ? formatPrintAmount(itemEstimatedTotal(item)) : "-" }}
                        </td>
                    </tr>
                    <tr v-if="purchaseRequest.items.length === 0">
                        <td colspan="6" class="print-center">No items</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5">TOTAL (IDR)</td>
                        <td class="print-number">
                            {{ hasMissingPrices ? "-" : formatPrintAmount(grandTotal) }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            <table class="print-approval-table">
                <thead>
                    <tr>
                        <th>ACKNOWLEDGE</th>
                        <th v-for="level in approval?.levels ?? []" :key="level.level">
                            APPROVAL {{ level.level }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <span class="print-signature-state">
                                {{ purchaseRequest.submitted_at ? "SUBMITTED DIGITAL" : "" }}
                            </span>
                            <strong>{{ purchaseRequest.requester?.name ?? "-" }}</strong>
                            <small>{{ purchaseRequest.requester?.position?.name ?? purchaseRequest.department?.name ?? "Requester" }}</small>
                        </td>
                        <td v-for="level in approval?.levels ?? []" :key="`approval-${level.level}`">
                            <span v-if="level.status === 'APPROVED'" class="print-signature-state">
                                APPROVED {{ level.acted_at ? formatDate(level.acted_at) : "" }}
                            </span>
                            <span v-else class="print-signature-space"></span>
                            <strong>{{ level.approver?.name ?? "-" }}</strong>
                            <small>{{ level.approver?.position?.name ?? `Approver ${level.level}` }}</small>
                        </td>
                    </tr>
                </tbody>
            </table>

            <footer class="print-footer">
                <strong>{{ page.props.app.name }}</strong>
                <small>Asset Management System</small>
            </footer>
        </section>

        <div class="purchase-request-print-area mx-auto w-full max-w-[1440px] space-y-5 px-4 py-5 sm:px-6">
        <ProcurementPageHeader
            class="screen-only"
            :title="purchaseRequest.number"
            section="Purchase Request"
            description="Detail pengajuan dan status persetujuan pembelian."
        >
            <template #back>
                <Button
                    label="Kembali ke daftar Purchase Request"
                    icon="pi pi-arrow-left"
                    severity="secondary"
                    text
                    size="small"
                    class="print:hidden"
                    @click="back"
                />
            </template>
            <template #badge>
                    <Tag :value="statusLabel" :severity="statusSeverity" />
            </template>
            <template #actions>
            <div class="flex flex-wrap gap-2 print:hidden">
                <Button
                    v-if="canPrint"
                    label="Cetak PR"
                    icon="pi pi-print"
                    severity="secondary"
                    outlined
                    @click="printPurchaseRequest"
                />
                <Button
                    v-if="canSubmit"
                    label="Edit Draft"
                    icon="pi pi-pencil"
                    severity="secondary"
                    outlined
                    @click="editDraft"
                />
                <Button
                    v-if="canSubmit"
                    label="Ajukan"
                    icon="pi pi-send"
                    @click="submitPurchaseRequest"
                />
                <Button
                    v-if="canMarkApproval"
                    label="Mark Approval"
                    icon="pi pi-check-circle"
                    @click="approvalDialogVisible = true"
                />
            </div>
            </template>
        </ProcurementPageHeader>

        <div class="grid items-start gap-5 2xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0 space-y-5">
                <section
                    class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                            Informasi Pengajuan
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Identitas dan tujuan permintaan pembelian.
                        </p>
                    </div>

                    <dl class="grid gap-x-8 gap-y-5 px-5 py-5 sm:grid-cols-2 sm:px-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tanggal Pengajuan</dt>
                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-white">
                                {{ formatDate(purchaseRequest.request_date) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Pemohon</dt>
                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-white">
                                {{ purchaseRequest.requester?.name ?? "-" }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Perusahaan</dt>
                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-white">
                                {{ purchaseRequest.company?.name ?? "-" }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Departemen</dt>
                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-white">
                                {{ purchaseRequest.department?.name ?? "-" }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tujuan Pengadaan</dt>
                            <dd class="mt-1 whitespace-pre-line text-base text-gray-800 dark:text-gray-200">
                                {{ purchaseRequest.purpose }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Catatan</dt>
                            <dd class="mt-1 whitespace-pre-line text-base text-gray-700 dark:text-gray-300">
                                {{ purchaseRequest.remarks || "Tidak ada catatan." }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                                Rincian Item
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Barang dan lokasi pengiriman dalam pengajuan ini.
                            </p>
                        </div>
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                            {{ itemCount }} item
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[920px] table-fixed text-left text-sm">
                            <colgroup>
                                <col class="w-[20%]" />
                                <col class="w-[25%]" />
                                <col class="w-[10%]" />
                                <col class="w-[14%]" />
                                <col class="w-[14%]" />
                                <col class="w-[17%]" />
                            </colgroup>
                            <thead class="bg-gray-50 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-300">
                                <tr>
                                    <th class="px-5 py-3 font-semibold">Barang / Jasa</th>
                                    <th class="px-5 py-3 font-semibold">Detail</th>
                                    <th class="px-5 py-3 text-right font-semibold">Kuantitas</th>
                                    <th class="px-5 py-3 text-right font-semibold">Harga Satuan</th>
                                    <th class="px-5 py-3 text-right font-semibold">Total Harga</th>
                                    <th class="px-5 py-3 font-semibold">Vendor & Pengiriman</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="(item, index) in purchaseRequest.items" :key="item.id">
                                    <td class="px-5 py-4 align-top">
                                        <div class="text-base font-semibold text-gray-900 dark:text-white">
                                            {{ itemName(item) }}
                                        </div>
                                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            {{ itemCode(item, index) }}
                                        </div>
                                    </td>
                                    <td class="max-w-xs whitespace-normal px-5 py-4 align-top text-sm text-gray-700 dark:text-gray-300">
                                        <div>{{ item.description || "Tidak ada deskripsi" }}</div>
                                        <div v-if="item.required_date" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                            Dibutuhkan {{ formatDate(item.required_date) }}
                                        </div>
                                        <div class="mt-1 flex flex-wrap gap-1.5">
                                            <span v-if="item.tax_included" class="rounded bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                                Termasuk PPN
                                            </span>
                                            <span v-if="item.requires_po" class="rounded bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">
                                                Perlu PO
                                            </span>
                                        </div>
                                        <div v-if="item.remarks" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                            {{ item.remarks }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right align-top text-base font-medium tabular-nums text-gray-900 dark:text-white">
                                        {{ formatQuantity(item.quantity) }}
                                        <div class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                                            {{ item.uom?.name ?? item.uom?.code ?? "-" }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right align-top tabular-nums">
                                        <span v-if="itemHasPrice(item)" class="text-sm text-gray-700 dark:text-gray-300">
                                            {{ formatCurrency(Number(item.estimated_unit_price)) }}
                                        </span>
                                        <span v-else class="text-sm font-medium text-amber-700 dark:text-amber-400">
                                            Harga belum tersedia
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right align-top text-base font-semibold tabular-nums text-gray-900 dark:text-white">
                                        {{ itemHasPrice(item) ? formatCurrency(itemEstimatedTotal(item)) : "Belum tersedia" }}
                                    </td>
                                    <td class="max-w-xs whitespace-normal px-5 py-4 align-top text-sm text-gray-700 dark:text-gray-300">
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ item.vendor?.name ?? "Vendor belum dipilih" }}
                                        </div>
                                        <div class="mt-1 text-gray-600 dark:text-gray-400">
                                            {{ item.delivery_address || "Lokasi belum dipilih" }}
                                        </div>
                                        <div v-if="item.vendor?.code" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            {{ item.vendor.code }}
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="purchaseRequest.items.length === 0">
                                    <td colspan="6" class="px-5 py-10 text-center text-base text-gray-500 dark:text-gray-400">
                                        Belum ada item pada pengajuan ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end border-t border-gray-200 px-5 py-3 text-sm dark:border-gray-800 sm:px-6">
                        <span class="text-sm text-gray-600 dark:text-gray-300">Total kuantitas</span>
                        <span class="ml-4 min-w-16 text-right text-base font-semibold tabular-nums text-gray-900 dark:text-white">
                            {{ totalQuantity }}
                        </span>
                    </div>
                </section>
            </div>

            <aside class="grid gap-5 sm:grid-cols-2 2xl:sticky 2xl:top-6 2xl:grid-cols-1">
                <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        Status Pengajuan
                    </h2>
                    <div class="mt-5 space-y-0">
                        <div class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                                    <i class="pi pi-check text-xs" />
                                </span>
                                <span class="my-1 h-8 w-px bg-gray-200 dark:bg-gray-700" />
                            </div>
                            <div class="pb-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Draft dibuat</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(purchaseRequest.request_date) }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-full"
                                    :class="hasBeenSubmitted ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800'"
                                >
                                    <i :class="hasBeenSubmitted ? 'pi pi-check text-xs' : 'pi pi-send text-xs'" />
                                </span>
                                <span class="my-1 h-8 w-px bg-gray-200 dark:bg-gray-700" />
                            </div>
                            <div class="pb-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Diajukan</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ purchaseRequest.submitted_at ? formatDate(purchaseRequest.submitted_at) : "Belum diajukan" }}
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <span
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                                :class="isApproved ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' : isRejected ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800'"
                            >
                                <i :class="isApproved ? 'pi pi-check text-xs' : isRejected ? 'pi pi-times text-xs' : 'pi pi-clock text-xs'" />
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ isRejected ? "Ditolak" : "Persetujuan" }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ purchaseRequest.approved_at ? formatDate(purchaseRequest.approved_at) : purchaseRequest.rejected_at ? formatDate(purchaseRequest.rejected_at) : "Menunggu proses" }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-if="approval"
                    class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                                Workflow Approval
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ approval.workflow_name ?? "Purchase Request" }}
                            </p>
                        </div>
                        <Tag
                            :value="approval.status"
                            :severity="approval.status === 'APPROVED' ? 'success' : 'warn'"
                        />
                    </div>

                    <ol class="mt-4 space-y-3">
                        <li
                            v-for="level in approval.levels"
                            :key="level.level"
                            class="flex items-start justify-between gap-3 rounded-md bg-gray-50 px-3 py-3 dark:bg-white/[0.03]"
                        >
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    Level {{ level.level }} · {{ level.approver?.name ?? "Approver" }}
                                </p>
                                <p v-if="level.remarks" class="mt-1 whitespace-pre-line text-xs text-gray-500 dark:text-gray-400">
                                    {{ level.remarks }}
                                </p>
                            </div>
                            <Tag
                                :value="level.status"
                            :severity="level.status === 'APPROVED' ? 'success' : level.status === 'REJECTED' ? 'danger' : level.status === 'CANCELLED' ? 'secondary' : 'warn'"
                            />
                        </li>
                    </ol>

                    <div
                        v-if="approval.has_manual_approval_document"
                        class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-800"
                    >
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            Approval manual oleh {{ approval.manual_approver?.name ?? "-" }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ approval.manual_approved_at ? formatDate(approval.manual_approved_at) : "" }}
                        </p>
                        <p v-if="approval.manual_approval_remarks" class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">
                            {{ approval.manual_approval_remarks }}
                        </p>
                        <a
                            :href="route('transaction.procurement.purchase-requests.approval-document', purchaseRequest.id)"
                            class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-brand-600 hover:underline dark:text-brand-400"
                        >
                            <i class="pi pi-download" />
                            Unduh dokumen PR yang ditandatangani
                        </a>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        Ringkasan
                    </h2>
                    <dl class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
                        <div class="flex items-center justify-between py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Jumlah item</dt>
                            <dd class="text-sm font-semibold text-gray-900 dark:text-white">{{ itemCount }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Total kuantitas</dt>
                            <dd class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ totalQuantity }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Subtotal estimasi</dt>
                            <dd class="text-right text-sm font-medium tabular-nums text-gray-900 dark:text-white">
                                {{ hasMissingPrices ? "Belum lengkap" : formatCurrency(subtotal) }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Estimasi PPN 11%</dt>
                            <dd class="text-right text-sm font-medium tabular-nums text-gray-900 dark:text-white">
                                {{ hasMissingPrices ? "-" : formatCurrency(estimatedTax) }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-gray-200 py-4 dark:border-gray-800">
                            <dt class="text-sm font-semibold text-gray-900 dark:text-white">Total Estimasi</dt>
                            <dd class="text-right text-base font-bold tabular-nums text-gray-900 dark:text-white">
                                {{ hasMissingPrices ? "Belum lengkap" : formatCurrency(grandTotal) }}
                            </dd>
                        </div>
                        <p v-if="hasMissingPrices" class="pb-2 text-xs leading-relaxed text-amber-700 dark:text-amber-400">
                            Total belum dapat dihitung karena ada harga item yang belum diisi.
                        </p>
                        <div class="flex items-center justify-between py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Departemen</dt>
                            <dd class="max-w-36 text-right text-sm font-medium text-gray-900 dark:text-white">{{ purchaseRequest.department?.name ?? "-" }}</dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>
        </div>
        <Dialog
            v-model:visible="approvalDialogVisible"
            modal
            header="Mark Approval Manual"
            :style="{ width: 'min(600px, 95vw)' }"
        >
            <form class="space-y-4" @submit.prevent="submitManualApproval">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Unggah dokumen PR yang telah ditandatangani. Semua level workflow akan ditandai approved berdasarkan dokumen ini.
            </p>

            <div>
                <label for="approval-remarks" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Komentar approval <span class="text-red-500">*</span>
                </label>
                <Textarea
                    id="approval-remarks"
                    v-model="approvalForm.remarks"
                    rows="4"
                    auto-resize
                    fluid
                    placeholder="Catatan hasil approval manual"
                />
                <p v-if="approvalForm.errors.remarks" class="mt-1 text-sm text-red-500">
                    {{ approvalForm.errors.remarks }}
                </p>
            </div>

            <div>
                <label for="signed-document" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Dokumen PR bertanda tangan <span class="text-red-500">*</span>
                </label>
                <input
                    id="signed-document"
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                    class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:file:bg-gray-800"
                    @change="setSignedDocument"
                />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    PDF, JPG, atau PNG; ukuran maksimal 20 MB.
                </p>
                <p v-if="approvalForm.errors.signed_document" class="mt-1 text-sm text-red-500">
                    {{ approvalForm.errors.signed_document }}
                </p>
            </div>

            <div v-if="approvalForm.progress" class="space-y-1">
                <div class="h-2 overflow-hidden rounded bg-gray-100 dark:bg-gray-800">
                    <div class="h-full bg-brand-600" :style="{ width: `${approvalForm.progress.percentage}%` }" />
                </div>
                <p class="text-xs text-gray-500">Mengunggah {{ approvalForm.progress.percentage }}%</p>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-800">
                <Button
                    type="button"
                    label="Batal"
                    severity="secondary"
                    outlined
                    :disabled="approvalForm.processing"
                    @click="approvalDialogVisible = false"
                />
                <Button
                    type="submit"
                    label="Selesaikan Approval"
                    icon="pi pi-check"
                    :loading="approvalForm.processing"
                    :disabled="approvalForm.processing || !approvalForm.remarks.trim() || !approvalForm.signed_document"
                />
            </div>
            </form>
        </Dialog>
    </div>
</template>

<style>
.purchase-request-print-sheet {
    display: none;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }

    html,
    body {
        width: auto !important;
        height: auto !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        background: #fff !important;
        color: #111 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .layout-wrapper,
    .layout-main-container,
    .layout-main {
        display: block !important;
        width: auto !important;
        height: auto !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }

    .layout-main-container {
        justify-content: initial !important;
    }

    body * {
        visibility: hidden !important;
    }

    .purchase-request-print-sheet,
    .purchase-request-print-sheet * {
        visibility: visible !important;
    }

    .purchase-request-print-area {
        display: none !important;
    }

    .purchase-request-print-sheet {
        position: relative !important;
        display: flex !important;
        min-height: 260mm;
        width: 100% !important;
        flex-direction: column;
        color: #111 !important;
        background: #fff !important;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 8pt;
    }

    .print-heading {
        margin-bottom: 5mm;
        text-align: center;
    }

    .print-heading h1 {
        margin: 0;
        font-size: 12pt;
        font-weight: 700;
        letter-spacing: 0.4px;
    }

    .print-meta-table,
    .print-items-table,
    .print-approval-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .print-meta-table {
        margin-bottom: 5mm;
        font-size: 7pt;
    }

    .print-meta-table th,
    .print-meta-table td,
    .print-items-table th,
    .print-items-table td,
    .print-approval-table th,
    .print-approval-table td {
        border: 0.25mm solid #111;
        padding: 1.2mm 1.5mm;
        vertical-align: top;
        overflow-wrap: anywhere;
    }

    .print-meta-table th {
        width: 13%;
        text-align: left;
        font-weight: 400;
    }

    .print-meta-table td {
        width: 37%;
        font-weight: 600;
    }

    .print-meta-table small,
    .print-description small,
    .print-approval-table small,
    .print-footer small {
        display: block;
        margin-top: 0.5mm;
        font-size: 6.2pt;
        font-weight: 400;
    }

    .print-status-submitted,
    .print-signature-state {
        color: #16803c;
        font-size: 6pt;
        font-weight: 700;
    }

    .print-status-draft {
        color: #555;
        font-size: 6pt;
        font-weight: 700;
    }

    .print-items-table {
        font-size: 6.4pt;
    }

    .print-items-table thead,
    .print-approval-table thead {
        background: var(--color-brand-700, #9e2d9e) !important;
        color: #fff !important;
    }

    .print-items-table th {
        text-align: center;
        vertical-align: middle;
        font-size: 6.1pt;
        font-weight: 700;
        text-transform: uppercase;
    }

    .print-items-table tr,
    .print-approval-table tr {
        break-inside: avoid;
    }

    .print-col-number {
        width: 5%;
    }

    .print-col-description {
        width: 54%;
    }

    .print-col-quantity {
        width: 7%;
    }

    .print-col-unit {
        width: 8%;
    }

    .print-col-price,
    .print-col-amount {
        width: 13%;
    }

    .print-description strong,
    .print-approval-table strong {
        display: block;
        font-size: 6.8pt;
        font-weight: 700;
    }

    .print-description small {
        line-height: 1.2;
    }

    .print-center {
        text-align: center;
    }

    .print-number {
        text-align: right;
        white-space: nowrap;
    }

    .print-items-table tfoot td {
        background: var(--color-brand-700, #9e2d9e) !important;
        color: #fff !important;
        font-weight: 700;
    }

    .print-items-table tfoot td:first-child {
        text-align: right;
    }

    .print-approval-table {
        margin-top: 5mm;
        break-inside: avoid;
        text-align: center;
        font-size: 6.5pt;
    }

    .print-approval-table thead th {
        font-weight: 700;
    }

    .print-approval-table tbody td {
        height: 24mm;
        vertical-align: bottom;
    }

    .print-approval-table strong {
        margin-top: 2mm;
    }

    .print-signature-state {
        display: block;
        min-height: 8mm;
    }

    .print-signature-space {
        display: block;
        height: 8mm;
    }

    .print-footer {
        margin-top: auto;
        border-top: 0.25mm solid #bbb;
        padding-top: 1.5mm;
        font-size: 6.5pt;
    }

    .print-footer strong {
        display: block;
        text-transform: uppercase;
    }

    .purchase-request-print-area {
        position: absolute !important;
        display: none !important;
    }
}
</style>
