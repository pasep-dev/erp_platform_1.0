<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import AppLayout from "@/layout/AppLayout.vue";
import CrudActions from "@/components/crud/CrudActions.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import DeleteConfirm from "@/components/crud/DeleteConfirm.vue";
import { useCrud } from "@/composables/useCrud";
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";
import Header from "@/components/content/Header.vue";

defineOptions({
    layout: AppLayout,
});

interface VendorType {
    id: string;
    code: string;
    name: string;
}

interface Vendor {
    id: string;
    vendor_type_id: string | null;
    vendor_type: VendorType | null;
    code: string;
    name: string;
    tax_number: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    description: string | null;
    is_active: boolean;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedVendors {
    data: Vendor[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    vendors: PaginatedVendors;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search);

const columns = [
    {
        key: "code",
        label: "Code",
    },
    {
        key: "name",
        label: "Vendor Name",
    },
    {
        key: "vendor_type",
        label: "Vendor Type",
    },
    {
        key: "tax_number",
        label: "Tax Number",
    },
    {
        key: "contact",
        label: "Contact",
    },
    {
        key: "address",
        label: "Address",
    },
    {
        key: "is_active",
        label: "Status",
    },
];

const showDeleteConfirm = ref(false);
const selectedVendor = ref<Vendor | null>(null);

const { processing, destroy } = useCrud({
    indexRoute: route("master.procurement.vendors.index"),

    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedVendor.value = null;
    },
});

function submitSearch(): void {
    router.get(
        route("master.procurement.vendors.index"),
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

function deleteVendor(vendor: Vendor): void {
    selectedVendor.value = vendor;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) {
        return;
    }

    showDeleteConfirm.value = false;
    selectedVendor.value = null;
}

function confirmDelete(): void {
    if (!selectedVendor.value) {
        return;
    }

    destroy(
        route("master.procurement.vendors.destroy", selectedVendor.value.id),
    );
}
</script>

<template>
    <div class="space-y-6">
        <Header
            title="Master Vendors"
            :action-url="route('master.procurement.vendor.create')"
            action-label="Add Vendor"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <SearchFilterBar
                    v-model="search"
                    :total="vendors.total"
                    placeholder="Cari data..."
                    @search="submitSearch"
                    @clear="clearSearch"
                />
            </div>

            <CrudTable
                :columns="columns"
                :rows="vendors.data"
                :empty-message="
                    search
                        ? 'Tidak ada vendor yang sesuai dengan pencarian.'
                        : 'Belum ada data vendor.'
                "
            >
                <template #cell-code="{ row }">
                    <span class="font-medium text-gray-700 dark:text-gray-200">
                        {{ row.code }}
                    </span>
                </template>

                <template #cell-name="{ row }">
                    <span class="text-gray-700 dark:text-gray-300">
                        {{ row.name }}
                    </span>
                </template>

                <template #cell-vendor_type="{ row }">
                    <div v-if="row.vendor_type">
                        <div
                            class="font-medium text-gray-700 dark:text-gray-300"
                        >
                            {{ row.vendor_type.name }}
                        </div>

                        <div class="text-xs text-gray-400 dark:text-gray-500">
                            {{ row.vendor_type.code }}
                        </div>
                    </div>

                    <span v-else class="text-gray-400 dark:text-gray-500">
                        Belum diklasifikasikan
                    </span>
                </template>

                <template #cell-tax_number="{ row }">
                    <span class="text-gray-600 dark:text-gray-400">
                        {{ row.tax_number || "-" }}
                    </span>
                </template>

                <template #cell-contact="{ row }">
                    <div class="flex flex-col">
                        <span
                            v-if="row.email"
                            class="text-gray-700 dark:text-gray-300"
                        >
                            {{ row.email }}
                        </span>

                        <span
                            v-if="row.phone"
                            class="text-xs text-gray-400 dark:text-gray-500"
                        >
                            {{ row.phone }}
                        </span>

                        <span
                            v-if="!row.email && !row.phone"
                            class="text-gray-400 dark:text-gray-500"
                        >
                            -
                        </span>
                    </div>
                </template>

                <template #cell-address="{ row }">
                    <span
                        class="block max-w-md truncate text-gray-600 dark:text-gray-400"
                        :title="row.address || ''"
                    >
                        {{ row.address || "-" }}
                    </span>
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
                        :processing="processing"
                        @edit="
                            router.visit(
                                route(
                                    'master.procurement.vendors.edit',
                                    row.id,
                                ),
                            )
                        "
                        @delete="deleteVendor(row)"
                    />
                </template>
            </CrudTable>

            <div
                v-if="vendors.last_page > 1"
                class="flex flex-col gap-3 border-t border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ vendors.from }}–{{ vendors.to }} dari
                    {{ vendors.total }} data
                </p>

                <div class="flex flex-wrap gap-1">
                    <template v-for="link in vendors.links" :key="link.label">
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
                        ></span>
                    </template>
                </div>
            </div>
        </div>

        <DeleteConfirm
            :show="showDeleteConfirm"
            :processing="processing"
            title="Hapus Vendor"
            :message="
                selectedVendor
                    ? `Apakah Anda yakin ingin menghapus vendor '${selectedVendor.name}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
