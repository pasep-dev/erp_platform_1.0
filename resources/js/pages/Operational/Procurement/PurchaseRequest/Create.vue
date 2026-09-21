<script setup lang="ts">
import { computed } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import AppLayout from "@/layouts/AppLayout.vue";
import CrudForm from "@/components/crud/CrudForm.vue";

defineOptions({
    layout: AppLayout,
});

interface Option {
    id: string;
    code: string;
    name: string;
}

interface PurchaseRequestItem {
    item_description: string;
    management_class: "ASSET" | "CONSUMABLE" | "LICENSE";
    quantity: number | string;
    uom_id: string;
    estimated_unit_price: number | string;
    specification: string;
    notes: string;
    estimated_total: number;
}

interface FormData {
    request_date: string;
    company_id: string;
    branch_id: string;
    department_id: string;
    cost_center_id: string;
    required_date: string;
    purpose: string;
    description: string;
    items: PurchaseRequestItem[];
}

interface Props {
    companies: Option[];
    branches: Option[];
    departments: Option[];
    costCenters: Option[];
    unitsOfMeasure: Option[];
}

const props = defineProps<Props>();

const form = useForm<FormData>({
    request_date: new Date().toISOString().slice(0, 10),
    company_id: "",
    branch_id: "",
    department_id: "",
    cost_center_id: "",
    required_date: "",
    purpose: "",
    description: "",
    items: [createItem()],
});

function createItem(): PurchaseRequestItem {
    return {
        item_description: "",
        management_class: "ASSET",
        quantity: 1,
        uom_id: "",
        estimated_unit_price: "",
        specification: "",
        notes: "",
        estimated_total: 0,
    };
}

function addItem(): void {
    form.items.push(createItem());
}

function removeItem(index: number): void {
    if (form.items.length <= 1) {
        return;
    }

    form.items.splice(index, 1);
}

function calculateItemTotal(item: PurchaseRequestItem): number {
    const quantity = Number(item.quantity) || 0;
    const unitPrice = Number(item.estimated_unit_price) || 0;

    return quantity * unitPrice;
}

function itemTotal(index: number): number {
    const item = form.items[index];

    if (!item) {
        return 0;
    }

    return calculateItemTotal(item);
}

const grandTotal = computed(() => {
    return form.items.reduce((total, item) => {
        return total + calculateItemTotal(item);
    }, 0);
});

function formatCurrency(value: number): string {
    return new Intl.NumberFormat("id-ID", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);
}

function submit(): void {
    form.items.forEach((item) => {
        item.estimated_total = calculateItemTotal(item);
    });

    form.post(route("operational.procurement.purchase-requests.store"));
}

function cancel(): void {
    window.location.href = route(
        "operational.procurement.purchase-request.index",
    );
}
</script>

<template>
    <Head title="Create Purchase Request" />

    <CrudForm
        title="Create Purchase Request"
        :form="form"
        submit-label="Save"
        :cancel-url="route('operational.procurement.purchase-request.index')"
        submit-processing-label="Saving..."
        :processing="form.processing"
        @submit="submit"
        @cancel="cancel"
    >
        <div class="space-y-6">
            <!-- Header -->
            <div>
                <h2
                    class="mb-4 text-base font-semibold text-gray-900 dark:text-white"
                >
                    Request Information
                </h2>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Request Date
                        </label>

                        <input
                            v-model="form.request_date"
                            type="date"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                        />

                        <p
                            v-if="form.errors.request_date"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.request_date }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Required Date
                        </label>

                        <input
                            v-model="form.required_date"
                            type="date"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                        />

                        <p
                            v-if="form.errors.required_date"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.required_date }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Company
                        </label>

                        <select
                            v-model="form.company_id"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">Select Company</option>

                            <option
                                v-for="company in props.companies"
                                :key="company.id"
                                :value="company.id"
                            >
                                {{ company.code }} - {{ company.name }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.company_id"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.company_id }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Branch
                        </label>

                        <select
                            v-model="form.branch_id"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">Select Branch</option>

                            <option
                                v-for="branch in props.branches"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.code }} - {{ branch.name }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.branch_id"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.branch_id }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Department
                        </label>

                        <select
                            v-model="form.department_id"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">Select Department</option>

                            <option
                                v-for="department in props.departments"
                                :key="department.id"
                                :value="department.id"
                            >
                                {{ department.code }} -
                                {{ department.name }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.department_id"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.department_id }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Cost Center
                        </label>

                        <select
                            v-model="form.cost_center_id"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">Select Cost Center</option>

                            <option
                                v-for="costCenter in props.costCenters"
                                :key="costCenter.id"
                                :value="costCenter.id"
                            >
                                {{ costCenter.code }} -
                                {{ costCenter.name }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.cost_center_id"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.cost_center_id }}
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Purpose
                    </label>

                    <textarea
                        v-model="form.purpose"
                        rows="3"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                        placeholder="Tujuan pengajuan purchase request..."
                    />

                    <p
                        v-if="form.errors.purpose"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.purpose }}
                    </p>
                </div>

                <div class="mt-5">
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Description
                    </label>

                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                        placeholder="Keterangan tambahan..."
                    />

                    <p
                        v-if="form.errors.description"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>
            </div>

            <!-- Items -->
            <div
                class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"
            >
                <div
                    class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2
                            class="text-base font-semibold text-gray-900 dark:text-white"
                        >
                            Request Items
                        </h2>

                        <p
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Tentukan management class yang diharapkan untuk
                            setiap item.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg border border-blue-600 px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/10"
                        @click="addItem"
                    >
                        + Add Item
                    </button>
                </div>

                <div class="space-y-5">
                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h3
                                class="text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                Item {{ index + 1 }}
                            </h3>

                            <button
                                v-if="form.items.length > 1"
                                type="button"
                                class="text-sm font-medium text-red-600 hover:text-red-700"
                                @click="removeItem(index)"
                            >
                                Remove
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Item Description
                                </label>

                                <input
                                    v-model="item.item_description"
                                    type="text"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                                    placeholder="Contoh: Laptop untuk staff Finance"
                                />

                                <p
                                    v-if="
                                        form.errors[
                                            `items.${index}.item_description`
                                        ]
                                    "
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{
                                        form.errors[
                                            `items.${index}.item_description`
                                        ]
                                    }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Management Class
                                </label>

                                <select
                                    v-model="item.management_class"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                >
                                    <option value="ASSET">Asset</option>
                                    <option value="CONSUMABLE">
                                        Consumable
                                    </option>
                                    <option value="LICENSE">License</option>
                                </select>

                                <p
                                    v-if="
                                        form.errors[
                                            `items.${index}.management_class`
                                        ]
                                    "
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{
                                        form.errors[
                                            `items.${index}.management_class`
                                        ]
                                    }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    UOM
                                </label>

                                <select
                                    v-model="item.uom_id"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                >
                                    <option value="">Select UOM</option>

                                    <option
                                        v-for="uom in props.unitsOfMeasure"
                                        :key="uom.id"
                                        :value="uom.id"
                                    >
                                        {{ uom.code }} - {{ uom.name }}
                                    </option>
                                </select>

                                <p
                                    v-if="form.errors[`items.${index}.uom_id`]"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{ form.errors[`items.${index}.uom_id`] }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Quantity
                                </label>

                                <input
                                    v-model="item.quantity"
                                    type="number"
                                    min="0.0001"
                                    step="0.0001"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                                />

                                <p
                                    v-if="
                                        form.errors[`items.${index}.quantity`]
                                    "
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{ form.errors[`items.${index}.quantity`] }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Estimated Unit Price
                                </label>

                                <input
                                    v-model="item.estimated_unit_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Estimated Total
                                </label>

                                <div
                                    class="flex h-11 items-center rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm font-medium text-gray-900 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white"
                                >
                                    Rp {{ formatCurrency(itemTotal(index)) }}
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Specification
                                </label>

                                <textarea
                                    v-model="item.specification"
                                    rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                                    placeholder="Spesifikasi item..."
                                />
                            </div>

                            <div class="md:col-span-2">
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Notes
                                </label>

                                <textarea
                                    v-model="item.notes"
                                    rows="2"
                                    class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                                    placeholder="Catatan..."
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-5 flex justify-end border-t border-gray-200 pt-4 dark:border-gray-700"
                >
                    <div class="text-right">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Grand Total
                        </div>

                        <div
                            class="mt-1 text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Rp {{ formatCurrency(grandTotal) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CrudForm>
</template>
