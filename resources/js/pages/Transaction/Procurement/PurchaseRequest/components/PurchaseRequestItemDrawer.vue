<script setup lang="ts">
import { computed, ref, watch } from "vue";

import Drawer from "primevue/drawer";

import Button from "primevue/button";

import InputText from "primevue/inputtext";

import Textarea from "primevue/textarea";

import Select from "primevue/select";

import InputNumber from "primevue/inputnumber";

import DatePicker from "primevue/datepicker";

import Tag from "primevue/tag";

interface Option {
    label: string;

    value: string;

    group?: string;

    requires_asset?: boolean;
    rate?: number;
    category?: "ADDITIVE" | "WITHHOLDING";
}

interface PurchaseRequestItem {
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

    requires_po: boolean;

    tax_included: boolean;
    tax_id: string | null;

    required_date: Date | null;

    delivery_branch_id: string | null;

    remarks: string;
}

const props = defineProps<{
    visible: boolean;

    item: PurchaseRequestItem | null;

    productOptions: Option[];

    uomOptions: Option[];

    serviceTypeOptions: Option[];

    assetOptions: Option[];

    vendorOptions: Option[];

    taxOptions: Option[];

    branchOptions: Option[];
}>();

const emit = defineEmits<{
    "update:visible": [value: boolean];

    save: [item: PurchaseRequestItem];

    cancel: [];
}>();

const localItem = ref<PurchaseRequestItem | null>(null);
const availableProductOptions = computed(() => props.productOptions);

const drawerVisible = computed({
    get: () => props.visible,

    set: (value: boolean) => {
        emit("update:visible", value);
    },
});

watch(
    () => props.item,

    (value) => {
        if (!value) {
            localItem.value = null;

            return;
        }

        localItem.value = {
            ...value,

            requires_po: value.requires_po ?? false,

            tax_included: value.tax_included ?? false,

            required_date: value.required_date
                ? new Date(value.required_date)
                : null,
        };
    },

    {
        immediate: true,
    },
);

const isService = computed(() => localItem.value?.item_type === "SERVICE");

const selectedService = computed(() =>
    props.serviceTypeOptions.find(
        (option) => option.value === localItem.value?.service_type_id,
    ),
);

const selectedTax = computed(() => props.taxOptions.find((option) => option.value === localItem.value?.tax_id));

const requiresAsset = computed(
    () => isService.value && selectedService.value?.requires_asset === true,
);

const estimatedAmount = computed(
    () =>
        Number(localItem.value?.quantity || 0) *
        Number(localItem.value?.estimated_unit_price || 0),
);

const title = computed(() =>
    props.item?.product_id || props.item?.service_type_id
        ? "Edit Item"
        : "Tambah Item",
);

const setType = (type: "PRODUCT" | "SERVICE") => {
    if (!localItem.value) {
        return;
    }

    localItem.value.item_type = type;

    if (type === "PRODUCT") {
        localItem.value.service_type_id = null;

        localItem.value.asset_id = null;
    } else {
        localItem.value.product_id = null;
    }
};

const onServiceChange = () => {
    if (!localItem.value) {
        return;
    }

    if (!requiresAsset.value) {
        localItem.value.asset_id = null;
    }
};

const onTaxChange = () => {
    if (selectedTax.value?.category === "WITHHOLDING" && localItem.value) {
        localItem.value.tax_included = false;
    }
};

const save = () => {
    if (!localItem.value) {
        return;
    }

    emit("save", {
        ...localItem.value,

        required_date: localItem.value.required_date
            ? new Date(localItem.value.required_date)
            : null,
    });
};

const close = () => {
    emit("cancel");

    emit("update:visible", false);
};

const formatCurrency = (value: number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",

        currency: "IDR",

        maximumFractionDigits: 0,
    }).format(value);
</script>

<template>
    <Drawer
        v-model:visible="drawerVisible"
        position="right"
        :modal="true"
        :dismissable="false"
        :show-close-icon="false"
        :style="{
            width: 'min(560px, 100vw)',
        }"
        class="!p-0"
    >
        <template #header>
            <div class="w-full">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2
                            class="text-base font-semibold text-gray-900 dark:text-white"
                        >
                            {{ title }}
                        </h2>

                        <p
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Lengkapi rincian barang atau jasa yang akan
                            diajukan.
                        </p>
                    </div>

                    <Button
                        icon="pi pi-times"
                        severity="secondary"
                        text
                        rounded
                        aria-label="Tutup"
                        @click="close"
                    />
                </div>
            </div>
        </template>

        <div v-if="localItem" class="flex h-full min-h-0 flex-col">
            <div class="flex-1 space-y-5 overflow-y-auto px-5 pb-5">
                <!-- TYPE -->

                <div
                    class="grid grid-cols-2 rounded-lg bg-gray-100 p-1 dark:bg-white/5"
                >
                    <button
                        type="button"
                        class="rounded-md px-3 py-2 text-xs font-medium transition"
                        :class="
                            !isService
                                ? 'bg-white text-brand-600 shadow-sm dark:bg-gray-800 dark:text-brand-400'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                        "
                        @click="setType('PRODUCT')"
                    >
                        <i class="pi pi-box mr-1.5" />

                        Barang
                    </button>

                    <button
                        type="button"
                        class="rounded-md px-3 py-2 text-xs font-medium transition"
                        :class="
                            isService
                                ? 'bg-white text-brand-600 shadow-sm dark:bg-gray-800 dark:text-brand-400'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                        "
                        @click="setType('SERVICE')"
                    >
                        <i class="pi pi-wrench mr-1.5" />

                        Jasa
                    </button>
                </div>

                <!-- PRODUCT -->

                <template v-if="!isService">
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                        >
                            Barang

                            <span class="text-red-500">*</span>
                        </label>

                        <Select
                            v-model="localItem.product_id"
                            :options="availableProductOptions"
                            option-label="label"
                            option-value="value"
                            filter
                            placeholder="Cari atau pilih barang"
                            fluid
                        />
                    </div>

                    <div v-if="localItem.product_id">
                        <label
                            class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                        >
                            Kategori Barang
                        </label>

                        <div
                            class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs dark:border-gray-700 dark:bg-white/[0.02]"
                        >
                            <Tag
                                :value="
                                    availableProductOptions.find(
                                        (option) =>
                                            option.value ===
                                            localItem?.product_id,
                                    )?.group ?? '-'
                                "
                                severity="secondary"
                            />
                        </div>
                    </div>
                </template>

                <!-- SERVICE -->

                <template v-else>
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                        >
                            Jenis Jasa

                            <span class="text-red-500">*</span>
                        </label>

                        <Select
                            v-model="localItem.service_type_id"
                            :options="serviceTypeOptions"
                            option-label="label"
                            option-value="value"
                            filter
                            placeholder="Cari atau pilih jenis jasa"
                            fluid
                            @change="onServiceChange"
                        />
                    </div>

                    <div v-if="requiresAsset">
                        <label
                            class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                        >
                            Asset Terkait

                            <span class="text-red-500">*</span>
                        </label>

                        <Select
                            v-model="localItem.asset_id"
                            :options="assetOptions"
                            option-label="label"
                            option-value="value"
                            filter
                            :disabled="assetOptions.length === 0"
                            placeholder="Data asset belum tersedia"
                            fluid
                        />

                        <p
                            class="mt-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[10px] leading-relaxed text-amber-800 dark:border-amber-900/50 dark:bg-amber-500/10 dark:text-amber-300"
                        >
                            Service ini memerlukan asset terdaftar. Modul
                            registrasi asset belum tersedia, jadi item jasa
                            dengan kebutuhan asset belum dapat dilengkapi di
                            sini.
                        </p>
                    </div>

                    <div
                        v-else-if="localItem.service_type_id"
                        class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2.5 text-[10px] leading-relaxed text-blue-700 dark:border-blue-900/50 dark:bg-blue-500/10 dark:text-blue-300"
                    >
                        <i class="pi pi-info-circle mr-1" />

                        Jasa ini tidak membutuhkan asset tertentu.
                    </div>
                </template>

                <!-- DESCRIPTION -->

                <div>
                    <label
                        class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                    >
                        Deskripsi / Spesifikasi
                    </label>

                    <Textarea
                        v-model="localItem.description"
                        rows="3"
                        auto-resize
                        placeholder="Tuliskan spesifikasi, scope pekerjaan, atau kebutuhan detail..."
                        fluid
                    />
                </div>

                <!-- QTY + UOM -->

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                        >
                            Jumlah

                            <span class="text-red-500">*</span>
                        </label>

                        <InputNumber
                            v-model="localItem.quantity"
                            :min="0.001"
                            :min-fraction-digits="0"
                            :max-fraction-digits="3"
                            fluid
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                        >
                            Satuan

                            <span class="text-red-500">*</span>
                        </label>

                        <Select
                            v-model="localItem.uom_id"
                            :options="uomOptions"
                            option-label="label"
                            option-value="value"
                            filter
                            placeholder="Pilih satuan"
                            fluid
                        />
                    </div>
                </div>

                <!-- PRICE -->

                <div>
                    <label
                        class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                    >
                        Estimasi Harga Satuan

                        <span class="text-red-500">*</span>
                    </label>

                    <InputNumber
                        v-model="localItem.estimated_unit_price"
                        mode="currency"
                        currency="IDR"
                        locale="id-ID"
                        :min="0"
                        fluid
                    />

                    <div
                        class="mt-2 flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-white/[0.03]"
                    >
                        <span class="text-gray-500"> Estimasi jumlah </span>

                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                        >
                            {{ formatCurrency(estimatedAmount) }}
                        </span>
                    </div>
                </div>

                <!-- PROCUREMENT -->

                <div class="border-t border-gray-200 pt-5 dark:border-gray-800">
                    <div class="mb-4">
                        <h3
                            class="text-xs font-semibold text-gray-900 dark:text-white"
                        >
                            Informasi Pengadaan
                        </h3>

                        <p class="mt-1 text-[10px] text-gray-500">
                            Informasi vendor dan kebutuhan pengadaan item.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <!-- VENDOR -->

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                            >
                                Vendor
                            </label>

                            <Select
                                v-model="localItem.vendor_id"
                                :options="vendorOptions"
                                option-label="label"
                                option-value="value"
                                filter
                                placeholder="Cari atau pilih vendor"
                            empty-message="Belum ada vendor di master data."
                            empty-filter-message="Vendor tidak ditemukan."
                                fluid
                            />
                        </div>

                        <!-- PIC -->

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Name / PIC
                                </label>

                                <InputText
                                    v-model="localItem.vendor_pic"
                                    placeholder="Nama PIC"
                                    fluid
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Kontak Vendor
                                </label>

                                <InputText
                                    v-model="localItem.vendor_contact"
                                    placeholder="No. telepon / email"
                                    fluid
                                />
                            </div>
                        </div>

                        <!-- URL -->

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                            >
                                URL Vendor
                            </label>

                            <InputText
                                v-model="localItem.vendor_url"
                                placeholder="https://..."
                                fluid
                            />
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">Jenis Pajak</label>
                            <Select v-model="localItem.tax_id" :options="taxOptions" option-label="label" option-value="value" show-clear placeholder="Tanpa pajak" fluid @change="onTaxChange" />
                            <p v-if="selectedTax" class="mt-1 text-xs text-gray-500">Tarif {{ selectedTax.rate }}% · {{ selectedTax.category === 'WITHHOLDING' ? 'dipotong dari pembayaran' : 'menambah nilai pengadaan' }}</p>
                        </div>

                        <!-- PROCUREMENT OPTIONS -->
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-3 py-3 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-white/[0.03]"
                            >
                                <input
                                    v-model="localItem.requires_po"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600"
                                />
                                <span
                                    class="text-xs font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Membutuhkan Purchase Order
                                </span>
                            </label>

                            <label
                                v-if="selectedTax?.category === 'ADDITIVE'"
                                class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-3 py-3 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-white/[0.03]"
                            >
                                <input
                                    v-model="localItem.tax_included"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600"
                                />
                                <span
                                    class="text-xs font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Harga sudah termasuk {{ selectedTax?.name ?? 'pajak' }}
                                </span>
                            </label>
                        </div>

                        <!-- REQUIRED DATE -->

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                            >
                                Tanggal Dibutuhkan

                                <span class="text-red-500">*</span>
                            </label>

                            <DatePicker
                                v-model="localItem.required_date"
                                date-format="dd MM yy"
                                show-icon
                                fluid
                            />
                        </div>

                        <!-- LOCATION -->

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                            >
                                Cabang Pengiriman / Service
                                <span class="text-red-500">*</span>
                            </label>

                            <Select
                                v-model="localItem.delivery_branch_id"
                                :options="branchOptions"
                                option-label="label"
                                option-value="value"
                                filter
                                show-clear
                                placeholder="Pilih cabang dari master data"
                                fluid
                                :invalid="!localItem.delivery_branch_id"
                            />
                        </div>

                        <!-- REMARKS -->

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                            >
                                Catatan Item
                            </label>

                            <Textarea
                                v-model="localItem.remarks"
                                rows="2"
                                auto-resize
                                placeholder="Catatan tambahan untuk item..."
                                fluid
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER -->

            <div
                class="shrink-0 border-t border-gray-200 bg-white px-5 py-3 dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-center justify-end gap-2">
                    <Button
                        type="button"
                        label="Batal"
                        severity="secondary"
                        outlined
                        size="small"
                        @click="close"
                    />

                    <Button
                        type="button"
                        :label="
                            title === 'Edit Item'
                                ? 'Simpan Perubahan'
                                : 'Tambahkan Item'
                        "
                        icon="pi pi-check"
                        size="small"
                        @click="save"
                    />
                </div>
            </div>
        </div>
    </Drawer>
</template>
