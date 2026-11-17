<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, router, Head } from "@inertiajs/vue3";
import { Plus, Search, Award } from "lucide-vue-next";
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

interface Brand {
    id: string;
    code: string;
    name: string;
}

interface Category {
    id: string;
    code: string;
    name: string;
}

interface Type {
    id: string;
    category_id: string;
    code: string;
    name: string;
    category?: Category | null;
}

interface AssetModel {
    id: string;
    brand_id: string;
    type_id: string;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
    created_at: string;
    brand?: Brand | null;
    type?: Type | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedModels {
    data: AssetModel[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    models: PaginatedModels;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search);

const hasModels = computed(() => props.models.data.length > 0);

const columns = [
    {
        key: "code",
        label: "Code",
    },
    {
        key: "name",
        label: "Model Name",
    },
    {
        key: "brand",
        label: "Brand",
    },
    {
        key: "type",
        label: "Type",
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
const selectedModel = ref<AssetModel | null>(null);

const { processing, destroy } = useCrud({
    indexRoute: route("master.product.models.index"),

    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedModel.value = null;
    },
});

function submitSearch(): void {
    router.get(
        route("master.product.models.index"),
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

function deleteModel(model: AssetModel): void {
    selectedModel.value = model;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) {
        return;
    }

    showDeleteConfirm.value = false;
    selectedModel.value = null;
}

function confirmDelete(): void {
    if (!selectedModel.value) {
        return;
    }

    destroy(route("master.product.models.destroy", selectedModel.value.id));
}
</script>

<template>
    <div class="space-y-6">
        <Header
            title="Master Models"
            :action-url="route('master.product.models.create')"
            action-label="Add Model"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <SearchFilterBar
                    v-model="search"
                    :total="models.total"
                    placeholder="Cari data..."
                    @search="submitSearch"
                    @clear="clearSearch"
                />
            </div>

            <CrudTable
                :columns="columns"
                :rows="models.data"
                :empty-message="
                    search
                        ? 'Tidak ada model yang sesuai dengan pencarian.'
                        : 'Belum ada data asset model.'
                "
            >
                <template #cell-brand="{ row }">
                    <div v-if="row.brand">
                        <div
                            class="font-medium text-gray-700 dark:text-gray-300"
                        >
                            {{ row.brand.name }}
                        </div>

                        <div class="text-xs text-gray-400 dark:text-gray-500">
                            {{ row.brand.code }}
                        </div>
                    </div>

                    <span v-else class="text-gray-400 dark:text-gray-500">
                        -
                    </span>
                </template>

                <template #cell-type="{ row }">
                    <div v-if="row.type">
                        <div
                            class="font-medium text-gray-700 dark:text-gray-300"
                        >
                            {{ row.type.name }}
                        </div>

                        <div class="text-xs text-gray-400 dark:text-gray-500">
                            {{ row.type.category?.name ?? "-" }}
                        </div>
                    </div>

                    <span v-else class="text-gray-400 dark:text-gray-500">
                        -
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
                        :processing="processing"
                        @edit="
                            router.visit(
                                route('master.product.models.edit', row.id),
                            )
                        "
                        @delete="deleteModel(row)"
                    />
                </template>
            </CrudTable>

            <div
                v-if="models.last_page > 1"
                class="flex flex-col gap-3 border-t border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ models.from }}–{{ models.to }} dari
                    {{ models.total }} data
                </p>

                <div class="flex flex-wrap gap-1">
                    <template v-for="link in models.links" :key="link.label">
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
            title="Hapus Asset Model"
            :message="
                selectedModel
                    ? `Apakah Anda yakin ingin menghapus model '${selectedModel.name}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
