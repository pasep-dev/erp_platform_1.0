<script setup lang="ts">
import { ref } from "vue";
import { Link, router, Head } from "@inertiajs/vue3";
import { Plus, Search, WalletCards } from "lucide-vue-next";
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

interface Company {
    id: string;
    code: string;
    name: string;
}

interface CostCenter {
    id: string;
    company_id: string;
    code: string;
    name: string;
    is_active: boolean;
    created_at: string;
    company?: Company;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedCostCenters {
    data: CostCenter[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    costCenters: PaginatedCostCenters;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search);

const columns = [
    {
        key: "company",
        label: "Company",
    },
    {
        key: "code",
        label: "Code",
    },
    {
        key: "name",
        label: "Name",
    },
    {
        key: "is_active",
        label: "Status",
    },
];

const showDeleteConfirm = ref(false);

const selectedCostCenter = ref<CostCenter | null>(null);

const { processing, destroy } = useCrud({
    indexRoute: route("master.organization.cost-centers.index"),

    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedCostCenter.value = null;
    },
});

function submitSearch(): void {
    router.get(
        route("master.organization.cost-centers.index"),
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

function deleteCostCenter(costCenter: CostCenter): void {
    selectedCostCenter.value = costCenter;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) {
        return;
    }

    showDeleteConfirm.value = false;
    selectedCostCenter.value = null;
}

function confirmDelete(): void {
    if (!selectedCostCenter.value) {
        return;
    }

    destroy(
        route(
            "master.organization.cost-centers.destroy",
            selectedCostCenter.value.id,
        ),
    );
}
</script>

<template>
    <div class="space-y-6">
        <Header
            title="Master Cost Centers"
            :action-url="route('master.organization.cost-centers.create')"
            action-label="Add Cost Centers"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <SearchFilterBar
                    v-model="search"
                    :total="costCenters.total"
                    placeholder="Cari data..."
                    @search="submitSearch"
                    @clear="clearSearch"
                />
            </div>

            <CrudTable
                :columns="columns"
                :rows="costCenters.data"
                empty-message="Tidak ada data cost center."
            >
                <template #cell-company="{ row }">
                    <div>
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ row.company?.name || "-" }}
                        </div>

                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ row.company?.code || "-" }}
                        </div>
                    </div>
                </template>

                <template #cell-code="{ row }">
                    <span class="font-medium text-gray-900 dark:text-white">
                        {{ row.code }}
                    </span>
                </template>

                <template #cell-name="{ row }">
                    {{ row.name }}
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
                                    'master.organization.cost-centers.edit',
                                    row.id,
                                ),
                            )
                        "
                        @delete="deleteCostCenter(row)"
                    />
                </template>
            </CrudTable>

            <div
                v-if="costCenters.last_page > 1"
                class="flex flex-col gap-3 border-t border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ costCenters.from }}–{{ costCenters.to }} dari
                    {{ costCenters.total }} data
                </p>

                <div class="flex flex-wrap gap-1">
                    <template
                        v-for="link in costCenters.links"
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
            title="Hapus Cost Center"
            :message="
                selectedCostCenter
                    ? `Apakah Anda yakin ingin menghapus cost center '${selectedCostCenter.name}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
