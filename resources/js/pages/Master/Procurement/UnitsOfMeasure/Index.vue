<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, router, Head } from "@inertiajs/vue3";
import { Plus, Search, Ruler } from "lucide-vue-next";
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

interface UnitOfMeasure {
    id: string;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUnitsOfMeasure {
    data: UnitOfMeasure[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    unitsOfMeasure: PaginatedUnitsOfMeasure;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search);

const hasUnitsOfMeasure = computed(() => props.unitsOfMeasure.data.length > 0);

const columns = [
    {
        key: "code",
        label: "Code",
    },
    {
        key: "name",
        label: "Unit of Measure",
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
const selectedUnitOfMeasure = ref<UnitOfMeasure | null>(null);

const { processing, destroy } = useCrud({
    indexRoute: route("master.procurement.units-of-measure.index"),

    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedUnitOfMeasure.value = null;
    },
});

function submitSearch(): void {
    router.get(
        route("master.procurement.units-of-measure.index"),
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

function deleteUnitOfMeasure(unitOfMeasure: UnitOfMeasure): void {
    selectedUnitOfMeasure.value = unitOfMeasure;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) {
        return;
    }

    showDeleteConfirm.value = false;
    selectedUnitOfMeasure.value = null;
}

function confirmDelete(): void {
    if (!selectedUnitOfMeasure.value) {
        return;
    }

    destroy(
        route(
            "master.procurement.units-of-measure.destroy",
            selectedUnitOfMeasure.value.id,
        ),
    );
}
</script>

<template>
    <Head title="Satuan Pengukuran" />
    <div class="procurement-ui space-y-6">
        <ProcurementPageHeader
            title="Satuan Pengukuran"
            section="Master Data"
            description="Atur satuan yang digunakan pada permintaan, pesanan, dan penerimaan barang."
            :action-url="route('master.procurement.units-of-measure.create')"
            action-label="Tambah Satuan"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <SearchFilterBar
                    v-model="search"
                    :total="unitsOfMeasure.total"
                    placeholder="Cari data..."
                    @search="submitSearch"
                    @clear="clearSearch"
                />
            </div>

            <CrudTable
                :columns="columns"
                :rows="unitsOfMeasure.data"
                :empty-message="
                    search
                        ? 'Tidak ada unit of measure yang sesuai dengan pencarian.'
                        : 'Belum ada data unit of measure.'
                "
            >
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
                                route(
                                    'master.procurement.units-of-measure.edit',
                                    row.id,
                                ),
                            )
                        "
                        @delete="deleteUnitOfMeasure(row)"
                    />
                </template>
            </CrudTable>

            <div
                v-if="unitsOfMeasure.last_page > 1"
                class="flex flex-col gap-3 border-t border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ unitsOfMeasure.from }}–{{
                        unitsOfMeasure.to
                    }}
                    dari {{ unitsOfMeasure.total }} data
                </p>

                <div class="flex flex-wrap gap-1">
                    <template
                        v-for="link in unitsOfMeasure.links"
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
                        ></span>
                    </template>
                </div>
            </div>
        </div>

        <DeleteConfirm
            :show="showDeleteConfirm"
            :processing="processing"
            title="Hapus Unit of Measure"
            :message="
                selectedUnitOfMeasure
                    ? `Apakah Anda yakin ingin menghapus unit of measure '${selectedUnitOfMeasure.name}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
