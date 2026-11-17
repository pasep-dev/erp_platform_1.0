<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import Button from "primevue/button";
import DatePicker from "primevue/datepicker";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Tag from "primevue/tag";
import ProcurementPageHeader from "@/components/procurement/ProcurementPageHeader.vue";
import PurchaseRequestItemDrawer from "./components/PurchaseRequestItemDrawer.vue";

import AppLayout from "@/layout/AppLayout.vue";

defineOptions({
    layout: AppLayout,
});

interface Product {
    id: string;
    code: string;
    name: string;
    group: string;
    department_ids: string[];
    branch_ids: string[];
}

interface Uom {
    id: string;
    code: string;
    name: string;
}

interface ServiceType {
    id: string;
    code: string;
    name: string;
    requires_asset: boolean;
}

interface Asset {
    id: string;
    code: string;
    name: string;
}

interface Vendor {
    id: string;
    code: string;
    name: string;
    is_active: boolean;
}

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
}

interface Branch {
    id: string;
    company_id: string;
    code: string;
    name: string;
    address: string | null;
}

export interface PurchaseRequestItem {
    item_type: "PRODUCT" | "SERVICE";
    product_id: string | null;
    service_type_id: string | null;
    asset_id: string | null;
    description: string;
    quantity: number;
    uom_id: string | null;
    estimated_unit_price: number;
    vendor_id: string | null;
    vendor_pic: string;
    vendor_contact: string;
    vendor_url: string;
    required_date: Date | null;
    delivery_branch_id: string | null;
    remarks: string;
    requires_po: boolean;
    tax_included: boolean;
}

interface ExistingPurchaseRequestItem extends Omit<PurchaseRequestItem, "required_date"> {
    required_date: string | null;
}

interface ExistingPurchaseRequest {
    id: string;
    request_date: string;
    company_id: string;
    department_id: string;
    purpose: string;
    remarks: string | null;
    items: ExistingPurchaseRequestItem[];
}

interface MaintenanceSource {
    id: string;
    number: string;
    issue_description: string;
    asset: { number: string; product: { code: string; name: string } | null };
}

interface PrefillItem {
    product_id: string;
    quantity: number;
    uom_id: string | null;
}

const props = defineProps<{
    products: Product[];
    companies: Company[];
    departments: Department[];
    branches: Branch[];
    serviceTypes: ServiceType[];
    vendors: Vendor[];
    unitsOfMeasure: Uom[];
    requester: { id: number; name: string };
    company: { id: string; code: string; name: string } | null;
    department: { id: string; company_id: string; code: string; name: string } | null;
    maintenanceRecord?: MaintenanceSource | null;
    prefillItem?: PrefillItem | null;
    purchaseRequest?: ExistingPurchaseRequest;
}>();

// Belum ada tabel/master Asset yang terhubung ke backend.
// Daftar asset dikosongkan agar form tidak mengirim ID dummy ke database.
const assets: Asset[] = [];

/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

const requestDate = ref<Date>(
    props.purchaseRequest
        ? new Date(`${props.purchaseRequest.request_date}T00:00:00`)
        : new Date(),
);
const purpose = ref(
    props.purchaseRequest?.purpose ?? (props.maintenanceRecord
        ? `Sparepart untuk ${props.maintenanceRecord.number} - ${props.maintenanceRecord.asset.number}`
        : ""),
);
const remarks = ref(props.purchaseRequest?.remarks ?? "");
const selectedCompanyId = ref<string | null>(
    props.purchaseRequest?.company_id ?? props.company?.id ?? null,
);
const selectedDepartmentId = ref<string | null>(
    props.purchaseRequest?.department_id ??
        (props.department?.company_id === selectedCompanyId.value
            ? props.department.id
            : null),
);

/*
|--------------------------------------------------------------------------
| Items
|--------------------------------------------------------------------------
*/

const items = ref<PurchaseRequestItem[]>(
    (props.purchaseRequest?.items ?? []).map((item) => ({
        ...item,
        required_date: item.required_date ? new Date(item.required_date) : null,
    })).concat(props.purchaseRequest || !props.prefillItem ? [] : [{
        item_type: "PRODUCT",
        product_id: props.prefillItem.product_id,
        service_type_id: null,
        asset_id: null,
        description: "",
        quantity: props.prefillItem.quantity,
        uom_id: props.prefillItem.uom_id,
        estimated_unit_price: 0,
        vendor_id: null,
        vendor_pic: "",
        vendor_contact: "",
        vendor_url: "",
        required_date: null,
        delivery_branch_id: null,
        remarks: "Dibuat dari peringatan stok minimum",
        requires_po: true,
        tax_included: false,
    }]),
);

watch(selectedCompanyId, () => {
    selectedDepartmentId.value = null;
    items.value = items.value.map((item) => ({
        ...item,
        delivery_branch_id: null,
    }));
});

const drawerVisible = ref(false);
const editingIndex = ref<number | null>(null);
const draftItem = ref<PurchaseRequestItem | null>(null);

/*
|--------------------------------------------------------------------------
| Options
|--------------------------------------------------------------------------
*/

const productOptions = computed(() =>
    props.products
        .filter((product) => selectedDepartmentId.value !== null && product.department_ids.includes(selectedDepartmentId.value))
        .map((product) => ({
        label: `${product.code} - ${product.name}`,
        value: product.id,
        group: product.group,
        branch_ids: product.branch_ids,
        name: product.name,
        code: product.code,
        })),
);

watch(selectedDepartmentId, (departmentId, previousDepartmentId) => {
    if (previousDepartmentId === null || departmentId === previousDepartmentId) {
        return;
    }

    const allowedProductIds = new Set(productOptions.value.map((product) => product.value));
    items.value = items.value.map((item) => item.product_id !== null && !allowedProductIds.has(item.product_id)
        ? { ...item, product_id: null }
        : item);
});

const uomOptions = computed(() =>
    props.unitsOfMeasure.map((uom) => ({
        label: `${uom.code} - ${uom.name}`,
        value: uom.id,
        code: uom.code,
        name: uom.name,
    })),
);

const serviceTypeOptions = computed(() =>
    props.serviceTypes.map((service) => ({
        label: `${service.code} - ${service.name}`,
        value: service.id,
        requires_asset: service.requires_asset,
    })),
);

const assetOptions = computed(() =>
    assets.map((asset) => ({
        label: `${asset.code} - ${asset.name}`,
        value: asset.id,
    })),
);

const vendorOptions = computed(() =>
    props.vendors.map((vendor) => ({
        label: `${vendor.code} - ${vendor.name}${vendor.is_active ? "" : " (Nonaktif)"}`,
        value: vendor.id,
    })),
);

const companyOptions = computed(() =>
    props.companies.map((company) => ({
        label: `${company.code} - ${company.name}`,
        value: company.id,
    })),
);

const departmentOptions = computed(() =>
    props.departments
        .filter((department) => department.company_id === selectedCompanyId.value)
        .map((department) => ({
            label: `${department.code} - ${department.name}`,
            value: department.id,
        })),
);

const branchOptions = computed(() =>
    props.branches
        .filter((branch) => branch.company_id === selectedCompanyId.value)
        .map((branch) => ({
            label: `${branch.code} - ${branch.name}`,
            value: branch.id,
        })),
);

/*
|--------------------------------------------------------------------------
| Item helpers
|--------------------------------------------------------------------------
*/

const emptyItem = (): PurchaseRequestItem => ({
    item_type: "PRODUCT",
    product_id: null,
    service_type_id: null,
    asset_id: null,
    description: "",
    quantity: 1,
    uom_id: null,
    estimated_unit_price: 0,
    vendor_id: null,
    vendor_pic: "",
    vendor_contact: "",
    vendor_url: "",
    required_date: null,
    delivery_branch_id: null,
    remarks: "",
    requires_po: false,
    tax_included: false,
});

const openAddItem = () => {
    editingIndex.value = null;
    draftItem.value = emptyItem();
    drawerVisible.value = true;
};

const openEditItem = (index: number) => {
    editingIndex.value = index;
    const item = items.value[index];

    draftItem.value = {
        ...item,
        required_date: item.required_date ? new Date(item.required_date) : null,
    };
    drawerVisible.value = true;
};

const closeDrawer = () => {
    drawerVisible.value = false;
    editingIndex.value = null;
    draftItem.value = null;
};

const saveItem = (item: PurchaseRequestItem) => {
    if (editingIndex.value === null) {
        items.value.push({
            ...item,
            required_date: item.required_date
                ? new Date(item.required_date)
                : null,
        });
    } else {
        items.value[editingIndex.value] = {
            ...item,
            required_date: item.required_date
                ? new Date(item.required_date)
                : null,
        };
    }

    closeDrawer();
};

const removeItem = (index: number) => {
    items.value.splice(index, 1);
};

/*
|--------------------------------------------------------------------------
| Display helpers
|--------------------------------------------------------------------------
*/

const selectedProduct = (item: PurchaseRequestItem) =>
    productOptions.value.find((option) => option.value === item.product_id);

const selectedService = (item: PurchaseRequestItem) =>
    props.serviceTypes.find((service) => service.id === item.service_type_id);

const selectedAsset = (item: PurchaseRequestItem) =>
    assets.find((asset) => asset.id === item.asset_id);

const selectedUom = (item: PurchaseRequestItem) =>
    uomOptions.value.find((option) => option.value === item.uom_id);

const itemLabel = (item: PurchaseRequestItem) => {
    if (item.item_type === "SERVICE") {
        return selectedService(item)?.name ?? "Jasa";
    }

    return selectedProduct(item)?.name ?? "Barang";
};

const itemCategory = (item: PurchaseRequestItem) => {
    if (item.item_type === "SERVICE") {
        return selectedService(item)?.requires_asset
            ? "Service Asset"
            : "General Service";
    }

    return selectedProduct(item)?.group ?? "-";
};

const itemSecondaryText = (item: PurchaseRequestItem) => {
    if (item.item_type === "SERVICE") {
        const asset = selectedAsset(item);

        return asset ? asset.code : item.description || "Jasa";
    }

    return (selectedProduct(item)?.code ?? item.description) || "-";
};

/*
|--------------------------------------------------------------------------
| Calculation
|--------------------------------------------------------------------------
*/

const itemAmount = (item: PurchaseRequestItem) =>
    Number(item.quantity || 0) * Number(item.estimated_unit_price || 0);

const subtotal = computed(() =>
    items.value.reduce((total, item) => total + itemAmount(item), 0),
);

const taxableSubtotal = computed(() =>
    items.value.reduce(
        (total, item) => total + (item.tax_included ? 0 : itemAmount(item)),
        0,
    ),
);

const tax = computed(() => Math.round(taxableSubtotal.value * 0.11));

const grandTotal = computed(() => subtotal.value + tax.value);

/*
|--------------------------------------------------------------------------
| Completeness
|--------------------------------------------------------------------------
*/

const completeness = computed(() => {
    let completed = 0;

    if (purpose.value.trim()) completed++;
    if (items.value.length > 0) completed++;
    if (subtotal.value > 0) completed++;

    if (
        items.value.length > 0 &&
        items.value.every((item) => item.required_date)
    ) {
        completed++;
    }

    return completed;
});

const completenessPercent = computed(() =>
    Math.round((completeness.value / 4) * 100),
);

/*
|--------------------------------------------------------------------------
| Formatting
|--------------------------------------------------------------------------
*/

const formatCurrency = (value: number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(value);

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

const processing = ref(false);

const buildPayload = () => ({
    asset_maintenance_record_id: props.maintenanceRecord?.id ?? null,
    company_id: selectedCompanyId.value,
    department_id: selectedDepartmentId.value,
    request_date: requestDate.value
        ? `${requestDate.value.getFullYear()}-${String(requestDate.value.getMonth() + 1).padStart(2, "0")}-${String(requestDate.value.getDate()).padStart(2, "0")}`
        : new Date().toISOString().slice(0, 10),
    purpose: purpose.value.trim(),
    remarks: remarks.value.trim() || null,
    items: items.value.map((item) => ({
        ...item,
        quantity: Number(item.quantity),
        estimated_unit_price: Number(item.estimated_unit_price || 0),
        required_date: item.required_date
            ? `${item.required_date.getFullYear()}-${String(item.required_date.getMonth() + 1).padStart(2, "0")}-${String(item.required_date.getDate()).padStart(2, "0")}`
            : null,
    })),
});

const saveDraft = () => {
    if (
        processing.value ||
        !selectedCompanyId.value ||
        !selectedDepartmentId.value ||
        items.value.length === 0
    ) return;

    processing.value = true;
    const onFinish = () => {
        processing.value = false;
    };

    if (props.purchaseRequest) {
        router.put(
            route("transaction.procurement.purchase-requests.update", props.purchaseRequest.id),
            buildPayload(),
            { onFinish },
        );

        return;
    }

    router.post(
        route("transaction.procurement.purchase-requests.store"),
        buildPayload(),
        { onFinish },
    );
};

const submitSavedRequest = (requestId: string) => {
    router.post(
        route("transaction.procurement.purchase-requests.submit", requestId),
        {},
        {
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const submit = () => {
    if (
        processing.value ||
        !selectedCompanyId.value ||
        !selectedDepartmentId.value ||
        !purpose.value.trim() ||
        items.value.length === 0
    ) return;

    processing.value = true;

    if (props.purchaseRequest) {
        router.put(
            route("transaction.procurement.purchase-requests.update", props.purchaseRequest.id),
            buildPayload(),
            {
                onSuccess: () => submitSavedRequest(props.purchaseRequest!.id),
                onError: () => {
                    processing.value = false;
                },
            },
        );

        return;
    }

    router.post(route("transaction.procurement.purchase-requests.store"), buildPayload(), {
        onSuccess: (page) => {
            const savedRequest = (page.props as Record<string, any>).purchaseRequest;

            if (!savedRequest?.id) {
                processing.value = false;
                return;
            }

            submitSavedRequest(savedRequest.id);
        },
        onError: () => {
            processing.value = false;
        },
        onFinish: () => {
            // Keep processing active while the follow-up submit request starts.
        },
    });
};

const cancel = () => {
    if (props.purchaseRequest) {
        router.get(
            route("transaction.procurement.purchase-requests.show", props.purchaseRequest.id),
        );

        return;
    }

    router.get(route("transaction.procurement.purchase-requests.index"));
};
</script>

<template>
    <div class="procurement-ui min-h-full pb-24">
        <div class="mx-auto max-w-[1440px] space-y-5">
            <ProcurementPageHeader
                :title="props.purchaseRequest ? 'Edit Purchase Request' : 'Buat Purchase Request'"
                section="Purchase Request"
                description="Lengkapi informasi kebutuhan dan rincian item pengadaan."
            >
                <template #back>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-brand-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-brand-300"
                        aria-label="Kembali ke daftar Purchase Request"
                        @click="cancel"
                    >
                        <i class="pi pi-arrow-left text-sm" />
                    </button>
                </template>
                <template #badge>
                    <Tag value="Draft" severity="warn" />
                </template>
                <template #actions>
                    <div class="hidden items-center gap-2 text-xs text-gray-500 sm:flex dark:text-gray-400">
                        <span class="size-2 rounded-full bg-emerald-500" />
                        {{ props.purchaseRequest ? "Mengubah draft" : "Draft belum tersimpan" }}
                    </div>
                </template>
            </ProcurementPageHeader>

            <section v-if="maintenanceRecord" class="mx-auto max-w-[1440px] rounded-xl border border-purple-200 bg-purple-50 p-4 dark:border-purple-900 dark:bg-purple-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-purple-700 dark:text-purple-300">Purchase Request untuk Maintenance</p>
                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ maintenanceRecord.number }} · {{ maintenanceRecord.asset.number }} · {{ maintenanceRecord.asset.product?.name ?? "Aset" }}</p>
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ maintenanceRecord.issue_description }}</p>
                <p class="mt-2 text-xs text-purple-800 dark:text-purple-200">Setelah PR disetujui, sparepart diterima melalui Goods Receipt. Maintenance baru dapat dimulai setelah seluruh kuantitas diterima baik.</p>
            </section>

            <!-- MAIN GRID -->
            <div
                class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_280px]"
            >
                <!-- LEFT -->
                <div class="min-w-0 space-y-5">
                    <!-- INFORMASI PENGAJUAN -->
                    <section
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800"
                        >
                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
                            >
                                1
                            </span>

                            <div>
                                <h2
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    Informasi Pengajuan
                                </h2>

                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Informasi utama untuk mengidentifikasi
                                    pengajuan.
                                </p>
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2 xl:grid-cols-4"
                        >
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Tanggal Pengajuan
                                    <span class="text-red-500">*</span>
                                </label>

                                <DatePicker
                                    v-model="requestDate"
                                    date-format="dd MM yy"
                                    show-icon
                                    fluid
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Pemohon
                                </label>

                                <InputText
                                    :model-value="props.requester?.name ?? '-'"
                                    readonly
                                    fluid
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Perusahaan
                                    <span class="text-red-500">*</span>
                                </label>

                                <Select
                                    v-model="selectedCompanyId"
                                    :options="companyOptions"
                                    option-label="label"
                                    option-value="value"
                                    filter
                                    placeholder="Pilih perusahaan"
                                    fluid
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Departemen
                                    <span class="text-red-500">*</span>
                                </label>

                                <Select
                                    v-model="selectedDepartmentId"
                                    :options="departmentOptions"
                                    option-label="label"
                                    option-value="value"
                                    filter
                                    :disabled="!selectedCompanyId"
                                    placeholder="Pilih departemen"
                                    fluid
                                />
                            </div>

                            <div class="md:col-span-2 xl:col-span-2">
                                <label
                                    class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Tujuan Pengadaan
                                    <span class="text-red-500">*</span>
                                </label>

                                <InputText
                                    v-model="purpose"
                                    placeholder="Contoh: Pengadaan perangkat kerja karyawan baru"
                                    fluid
                                />
                            </div>

                            <div class="md:col-span-2 xl:col-span-2">
                                <label
                                    class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Catatan Pengajuan
                                </label>

                                <InputText
                                    v-model="remarks"
                                    placeholder="Tambahkan catatan bila diperlukan"
                                    fluid
                                />
                            </div>
                        </div>
                    </section>

                    <!-- DAFTAR ITEM -->
                    <section
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div
                            class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
                                >
                                    2
                                </span>

                                <div>
                                    <h2
                                        class="text-sm font-semibold text-gray-900 dark:text-white"
                                    >
                                        Daftar Item
                                    </h2>

                                    <p
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Tambahkan seluruh barang atau jasa dalam
                                        satu pengajuan.
                                    </p>
                                </div>
                            </div>

                            <Button
                                type="button"
                                label="Tambah Item"
                                icon="pi pi-plus"
                                size="small"
                                @click="openAddItem"
                            />
                        </div>

                        <div
                            v-if="items.length === 0"
                            class="px-5 py-12 text-center"
                        >
                            <div
                                class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-white/5"
                            >
                                <i class="pi pi-shopping-cart text-lg" />
                            </div>

                            <div
                                class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200"
                            >
                                Belum ada item
                            </div>

                            <p
                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                Klik "Tambah Item" untuk memasukkan barang atau
                                jasa.
                            </p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table
                                class="min-w-[760px] w-full text-left text-xs"
                            >
                                <thead
                                    class="bg-gray-50 text-[10px] uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400"
                                >
                                    <tr>
                                        <th class="w-10 px-4 py-3">No.</th>

                                        <th class="px-4 py-3">Barang / Jasa</th>

                                        <th class="px-4 py-3">Kategori</th>

                                        <th class="px-4 py-3 text-right">
                                            Qty
                                        </th>

                                        <th class="px-4 py-3 text-right">
                                            Harga Satuan
                                        </th>

                                        <th class="px-4 py-3 text-right">
                                            Jumlah
                                        </th>

                                        <th class="w-20 px-4 py-3 text-center">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>

                                <tbody
                                    class="divide-y divide-gray-100 dark:divide-gray-800"
                                >
                                    <tr
                                        v-for="(item, index) in items"
                                        :key="index"
                                        class="transition-colors hover:bg-gray-50/80 dark:hover:bg-white/[0.02]"
                                    >
                                        <td class="px-4 py-4 text-gray-500">
                                            {{ index + 1 }}
                                        </td>

                                        <td class="px-4 py-4">
                                            <div
                                                class="font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ itemLabel(item) }}
                                            </div>

                                            <div
                                                class="mt-0.5 text-[11px] text-gray-500"
                                            >
                                                {{ itemSecondaryText(item) }}
                                            </div>
                                        </td>

                                        <td class="px-4 py-4">
                                            <Tag
                                                :value="itemCategory(item)"
                                                severity="secondary"
                                                class="text-[10px]"
                                            />
                                        </td>

                                        <td
                                            class="whitespace-nowrap px-4 py-4 text-right"
                                        >
                                            {{ item.quantity }}
                                            {{ selectedUom(item)?.code ?? "" }}
                                        </td>

                                        <td
                                            class="whitespace-nowrap px-4 py-4 text-right"
                                        >
                                            {{
                                                formatCurrency(
                                                    item.estimated_unit_price,
                                                )
                                            }}
                                        </td>

                                        <td
                                            class="whitespace-nowrap px-4 py-4 text-right font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{
                                                formatCurrency(itemAmount(item))
                                            }}
                                        </td>

                                        <td class="px-4 py-4">
                                            <div
                                                class="flex items-center justify-center gap-1"
                                            >
                                                <Button
                                                    type="button"
                                                    icon="pi pi-pencil"
                                                    severity="secondary"
                                                    text
                                                    rounded
                                                    size="small"
                                                    aria-label="Edit item"
                                                    @click="openEditItem(index)"
                                                />

                                                <Button
                                                    type="button"
                                                    icon="pi pi-trash"
                                                    severity="danger"
                                                    text
                                                    rounded
                                                    size="small"
                                                    aria-label="Hapus item"
                                                    @click="removeItem(index)"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>

                                <tfoot
                                    class="border-t border-gray-200 dark:border-gray-800"
                                >
                                    <tr>
                                        <td
                                            colspan="5"
                                            class="px-4 py-3 text-right text-xs text-gray-500"
                                        >
                                            Subtotal
                                        </td>

                                        <td
                                            class="px-4 py-3 text-right font-semibold"
                                        >
                                            {{ formatCurrency(subtotal) }}
                                        </td>

                                        <td />
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div
                            v-if="items.length > 0"
                            class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500 dark:border-gray-800"
                        >
                            {{ items.length }} item dalam pengajuan
                        </div>
                    </section>

                </div>

                <!-- SIDEBAR -->
                <aside class="space-y-4 lg:sticky lg:top-5">
                    <!-- SUMMARY -->
                    <section
                        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                        <h2
                            class="text-sm font-semibold text-gray-900 dark:text-white"
                        >
                            Ringkasan Pengajuan
                        </h2>

                        <div class="mt-4 space-y-3 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500"> Jumlah item </span>

                                <span
                                    class="font-medium text-gray-800 dark:text-gray-200"
                                >
                                    {{ items.length }} item
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-gray-500"> Subtotal </span>

                                <span class="font-medium">
                                    {{ formatCurrency(subtotal) }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">
                                    Estimasi PPN 11%
                                </span>

                                <span class="font-medium">
                                    {{ formatCurrency(tax) }}
                                </span>
                            </div>

                            <div
                                class="border-t border-gray-200 pt-3 dark:border-gray-800"
                            >
                                <div
                                    class="text-[9px] font-semibold uppercase tracking-wider text-gray-400"
                                >
                                    Total Estimasi
                                </div>

                                <div
                                    class="mt-1 text-lg font-bold text-gray-900 dark:text-white"
                                >
                                    {{ formatCurrency(grandTotal) }}
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-4 rounded-lg bg-blue-50 px-3 py-2.5 text-[10px] leading-relaxed text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
                        >
                            <i class="pi pi-info-circle mr-1" />
                            Nilai akhir dapat berubah setelah proses negosiasi
                            dengan vendor.
                        </div>
                    </section>

                    <!-- COMPLETENESS -->
                    <section
                        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div class="flex items-center justify-between">
                            <h2
                                class="text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                Kelengkapan Data
                            </h2>

                            <span
                                class="text-xs font-semibold text-emerald-600"
                            >
                                {{ completenessPercent }}%
                            </span>
                        </div>

                        <div
                            class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                        >
                            <div
                                class="h-full rounded-full bg-emerald-500 transition-all"
                                :style="{
                                    width: `${completenessPercent}%`,
                                }"
                            />
                        </div>

                        <div class="mt-3 space-y-2 text-[10px]">
                            <div class="flex items-center gap-2">
                                <i
                                    :class="
                                        purpose
                                            ? 'pi pi-check-circle text-emerald-500'
                                            : 'pi pi-circle text-gray-300'
                                    "
                                />
                                <span>Informasi pengajuan</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <i
                                    :class="
                                        items.length
                                            ? 'pi pi-check-circle text-emerald-500'
                                            : 'pi pi-circle text-gray-300'
                                    "
                                />
                                <span>Daftar item</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <i
                                    :class="
                                        subtotal > 0
                                            ? 'pi pi-check-circle text-emerald-500'
                                            : 'pi pi-circle text-gray-300'
                                    "
                                />
                                <span>Estimasi biaya</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <i
                                    :class="
                                        items.length &&
                                        items.every(
                                            (item) => item.required_date,
                                        )
                                            ? 'pi pi-check-circle text-emerald-500'
                                            : 'pi pi-circle text-gray-300'
                                    "
                                />
                                <span>Tanggal kebutuhan</span>
                            </div>

                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </div>

    <!-- STICKY FOOTER -->
    <div
        class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-4px_16px_rgba(0,0,0,0.05)] backdrop-blur dark:border-gray-800 dark:bg-gray-900/95"
    >
        <div class="mx-auto flex max-w-[1440px] items-center justify-end gap-2">
            <Button
                type="button"
                label="Batal"
                severity="secondary"
                text
                @click="cancel"
            />

            <Button
                type="button"
                :label="props.purchaseRequest ? 'Simpan Perubahan' : 'Simpan Draft'"
                icon="pi pi-save"
                severity="secondary"
                outlined
                :disabled="processing || !selectedCompanyId || !selectedDepartmentId || items.length === 0"
                :loading="processing"
                @click="saveDraft"
            />

            <Button
                type="button"
                label="Ajukan"
                icon="pi pi-send"
                icon-pos="right"
                :disabled="processing || !selectedCompanyId || !selectedDepartmentId || !purpose.trim() || items.length === 0"
                :loading="processing"
                @click="submit"
            />
        </div>
    </div>

    <!-- ITEM DRAWER -->
    <PurchaseRequestItemDrawer
        :visible="drawerVisible"
        :item="draftItem"
        :product-options="productOptions"
        :uom-options="uomOptions"
        :service-type-options="serviceTypeOptions"
        :asset-options="assetOptions"
        :vendor-options="vendorOptions"
                :branch-options="branchOptions"
        @update:visible="drawerVisible = $event"
        @save="saveItem"
        @cancel="closeDrawer"
    />
</template>
