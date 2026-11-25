<script setup lang="ts">
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import AppLayout from "@/layout/AppLayout.vue";
import AppPagination from "@/components/common/AppPagination.vue";
import AppSelect from "@/components/common/AppSelect.vue";
import DataTableToolbar from "@/components/common/DataTableToolbar.vue";
import Header from "@/components/content/Header.vue";
import CrudActions from "@/components/crud/CrudActions.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import DeleteConfirm from "@/components/crud/DeleteConfirm.vue";
import Button from "primevue/button";
import { useCrud } from "@/composables/useCrud";

import Modal from "@/components/common/Modal.vue";
import ProductDetailContent from "./ProductDetail.vue";

defineOptions({
    layout: AppLayout,
});

interface Category {
    id: string;
    code: string;
    name: string;
}

interface Type {
    id: string;
    code: string;
    name: string;
}

interface Brand {
    id: string;
    code: string;
    name: string;
}

interface Model {
    id: string;
    code: string;
    name: string;
}

interface Product {
    id: string;
    code: string;
    name: string;
    description: string | null;
    group: string;
    category: Category | null;
    type: Type | null;
    brand: Brand | null;
    model: Model | null;
    is_active: boolean;
    created_at: string;
    departments: Array<{ id: string; name: string }>;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedProducts {
    data: Product[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    products: PaginatedProducts;
    filters: {
        search: string;
        group: string;
        status: string;
    };
}

const props = defineProps<Props>();
const search = ref(props.filters.search);
const allGroupValue = "ALL";
const allStatusValue = "ALL";
const selectedGroup = ref(props.filters.group || allGroupValue);
const selectedStatus = ref(props.filters.status || allStatusValue);
const perPage = ref(props.products.per_page);

const groupOptions = [
    { value: allGroupValue, label: "Semua Group" },
    { value: "ASSET", label: "ASSET" },
    { value: "CONSUMABLE", label: "CONSUMABLE" },
    { value: "LICENSE", label: "LICENSE" },
];

const statusOptions = [
    { value: allStatusValue, label: "Semua Status" },
    { value: "ACTIVE", label: "Active" },
    { value: "INACTIVE", label: "Inactive" },
];

const columns = [
    { key: "code", label: "Code" },
    { key: "name", label: "Product" },
    { key: "group", label: "Group" },
    { key: "departments", label: "Department" },
    { key: "category", label: "Category" },
    { key: "brand", label: "Brand" },
    { key: "model", label: "Model" },
    { key: "is_active", label: "Status" },
];

const showDetailModal = ref(false);
const detailProduct = ref<Product | null>(null);

function viewProduct(product: Product): void {
    detailProduct.value = product;
    showDetailModal.value = true;
}

function closeDetailModal(): void {
    showDetailModal.value = false;
    detailProduct.value = null;
}

const showDeleteConfirm = ref(false);
const selectedProduct = ref<Product | null>(null);

const { processing, destroy } = useCrud({
    indexRoute: route("master.product.products.index"),
    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedProduct.value = null;
    },
});

function submitSearch(): void {
    router.get(
        route("master.product.products.index"),
        {
            search: search.value || undefined,
            group: selectedGroup.value !== allGroupValue ? selectedGroup.value : undefined,
            status: selectedStatus.value !== allStatusValue ? selectedStatus.value : undefined,
            per_page: perPage.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function exportProducts(): void {
    const exportUrl = new URL(route("master.product.products.export"), window.location.origin);
    const params = {
        search: search.value.trim(),
        group: selectedGroup.value !== allGroupValue ? selectedGroup.value : "",
        status: selectedStatus.value !== allStatusValue ? selectedStatus.value : "",
    };

    for (const [key, value] of Object.entries(params)) {
        if (value) {
            exportUrl.searchParams.set(key, value);
        }
    }

    window.location.href = exportUrl.toString();
}

function onGroupChange(): void {
    submitSearch();
}

function deleteProduct(product: Product): void {
    selectedProduct.value = product;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) {
        return;
    }

    showDeleteConfirm.value = false;
    selectedProduct.value = null;
}

function confirmDelete(): void {
    if (!selectedProduct.value) {
        return;
    }

    destroy(route("master.product.products.destroy", selectedProduct.value.id));
}
</script>

<template>
    <div class="space-y-6">
        <Header
            title="Master Products"
            :action-url="route('master.product.products.create')"
            action-label="Add Product"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <DataTableToolbar
                v-model="search"
                :total="products.total"
                total-label="Products"
                placeholder="Cari kode, nama, atau grup..."
                @search="submitSearch"
            >
                <AppSelect
                    v-model="selectedGroup"
                    :options="groupOptions"
                    class="w-full sm:w-48"
                    aria-label="Filter grup produk"
                    @change="onGroupChange"
                />
                <AppSelect
                    v-model="selectedStatus"
                    :options="statusOptions"
                    class="w-full sm:w-40"
                    aria-label="Filter status produk"
                    @change="onGroupChange"
                />
                <Button
                    label="Export CSV"
                    icon="pi pi-download"
                    severity="secondary"
                    outlined
                    @click="exportProducts"
                />
            </DataTableToolbar>

            <CrudTable
                :columns="columns"
                :rows="products.data"
                :empty-message="
                    search || selectedGroup !== allGroupValue || selectedStatus !== allStatusValue
                        ? 'Tidak ada product yang sesuai dengan filter.'
                        : 'Belum ada data product master.'
                "
            >
                <template #cell-name="{ row }">
                    <div>
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ row.name }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ row.description || "-" }}
                        </div>
                    </div>
                </template>

                <template #cell-group="{ row }">
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            row.group === 'ASSET'
                                ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400'
                                : row.group === 'CONSUMABLE'
                                  ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400'
                                  : 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-400'
                        "
                    >
                        {{ row.group }}
                    </span>
                </template>

                <template #cell-departments="{ row }">
                    <div class="flex flex-wrap gap-1">
                        <span v-for="department in row.departments" :key="department.id" class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ department.name }}</span>
                        <span v-if="row.departments.length === 0" class="text-xs text-amber-700 dark:text-amber-400">Belum ditetapkan</span>
                    </div>
                </template>

                <template #cell-category="{ row }">
                    {{ row.category?.name || "-" }}
                </template>

                <template #cell-brand="{ row }">
                    {{ row.brand?.name || "-" }}
                </template>

                <template #cell-model="{ row }">
                    {{ row.model?.name || "-" }}
                </template>

                <template #cell-is_active="{ row }">
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            row.is_active
                                ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400'
                        "
                    >
                        {{ row.is_active ? "Active" : "Inactive" }}
                    </span>
                </template>

                <template #actions="{ row }">
                    <CrudActions
                        :show-view="true"
                        :show-edit="true"
                        :show-delete="true"
                        :processing="processing"
                        @view="viewProduct(row)"
                        @edit="
                            router.visit(
                                route('master.product.products.edit', row.id),
                            )
                        "
                        @delete="deleteProduct(row)"
                    />
                </template>
            </CrudTable>

            <AppPagination
                v-if="products.total > 0"
                v-model:per-page="perPage"
                :meta="products"
                @change="submitSearch"
            />
        </div>

        <Modal
            :show="showDetailModal"
            title="Product Detail"
            max-width="5xl"
            @close="closeDetailModal"
        >
            <ProductDetailContent
                v-if="detailProduct"
                :product="detailProduct"
            />

            <template #footer>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="closeDetailModal"
                >
                    Close
                </button>
            </template>
        </Modal>

        <DeleteConfirm
            :show="showDeleteConfirm"
            :processing="processing"
            title="Hapus Product"
            :message="
                selectedProduct
                    ? `Apakah Anda yakin ingin menghapus product '${selectedProduct.name}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
