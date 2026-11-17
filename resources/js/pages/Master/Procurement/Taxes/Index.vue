<script setup lang="ts">
import { ref } from "vue";
import { Link, router, Head } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import AppLayout from "@/layout/AppLayout.vue";
import CrudActions from "@/components/crud/CrudActions.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import DeleteConfirm from "@/components/crud/DeleteConfirm.vue";
import { useCrud } from "@/composables/useCrud";
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";
import ProcurementPageHeader from "@/components/procurement/ProcurementPageHeader.vue";

defineOptions({
    layout: AppLayout,
});

interface Tax {
    id: string;
    code: string;
    name: string;
    rate: string | number;
    description: string | null;
    is_active: boolean;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTaxes {
    data: Tax[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    taxes: PaginatedTaxes;
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
        label: "Tax Name",
    },
    {
        key: "rate",
        label: "Rate",
    },
    {
        key: "description",
        label: "Description",
    },
    {
        key: "is_active",
        label: "Status",
    },
];

const showDeleteConfirm = ref(false);
const selectedTax = ref<Tax | null>(null);

const { processing, destroy } = useCrud({
    indexRoute: route("master.procurement.taxes.index"),

    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedTax.value = null;
    },
});

function submitSearch(): void {
    router.get(
        route("master.procurement.taxes.index"),
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

function deleteTax(tax: Tax): void {
    selectedTax.value = tax;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) {
        return;
    }

    showDeleteConfirm.value = false;
    selectedTax.value = null;
}

function confirmDelete(): void {
    if (!selectedTax.value) {
        return;
    }

    destroy(route("master.procurement.taxes.destroy", selectedTax.value.id));
}
</script>

<template>
    <Head title="Pajak" />
    <div class="procurement-ui space-y-6">
        <ProcurementPageHeader
            title="Pajak"
            section="Master Data"
            description="Kelola tarif pajak yang dapat diterapkan pada pengadaan."
            :action-url="route('master.procurement.taxes.create')"
            action-label="Tambah Pajak"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <SearchFilterBar
                    v-model="search"
                    :total="taxes.total"
                    placeholder="Cari data..."
                    @search="submitSearch"
                    @clear="clearSearch"
                />
            </div>

            <CrudTable
                :columns="columns"
                :rows="taxes.data"
                :empty-message="
                    search
                        ? 'Tidak ada tax yang sesuai dengan pencarian.'
                        : 'Belum ada data tax.'
                "
            >
                <template #cell-rate="{ row }">
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        {{ row.rate }}%
                    </span>
                </template>

                <template #cell-description="{ row }">
                    <span
                        class="block max-w-md truncate text-gray-600 dark:text-gray-400"
                        :title="row.description || ''"
                    >
                        {{ row.description || "-" }}
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
                        variant="procurement"
                        :processing="processing"
                        @edit="
                            router.visit(
                                route('master.procurement.taxes.edit', row.id),
                            )
                        "
                        @delete="deleteTax(row)"
                    />
                </template>
            </CrudTable>

            <div
                v-if="taxes.last_page > 1"
                class="flex flex-col gap-3 border-t border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ taxes.from }}–{{ taxes.to }} dari
                    {{ taxes.total }} data
                </p>

                <div class="flex flex-wrap gap-1">
                    <template v-for="link in taxes.links" :key="link.label">
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
            title="Hapus Tax"
            :message="
                selectedTax
                    ? `Apakah Anda yakin ingin menghapus tax '${selectedTax.name}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
