<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, router, Head } from "@inertiajs/vue3";
import { Plus, Search, Tags } from "lucide-vue-next";
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
    description: string | null;
    is_active: boolean;
    created_at: string;
    category?: Category | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTypes {
    data: Type[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    types: PaginatedTypes;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search);

const hasTypes = computed(() => props.types.data.length > 0);

const columns = [
    {
        key: "code",
        label: "Code",
    },
    {
        key: "name",
        label: "Type Name",
    },
    {
        key: "category",
        label: "Category",
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
const selectedType = ref<Type | null>(null);

const { processing, destroy } = useCrud({
    indexRoute: route("master.product.types.index"),

    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedType.value = null;
    },
});

function submitSearch(): void {
    router.get(
        route("master.product.types.index"),
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

function deleteType(type: Type): void {
    selectedType.value = type;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) {
        return;
    }

    showDeleteConfirm.value = false;
    selectedType.value = null;
}

function confirmDelete(): void {
    if (!selectedType.value) {
        return;
    }

    destroy(route("master.product.types.destroy", selectedType.value.id));
}
</script>

<template>
    <div class="space-y-6">
        <Header
            title="Master Types"
            :action-url="route('master.product.types.create')"
            action-label="Add Type"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <SearchFilterBar
                    v-model="search"
                    :total="types.total"
                    placeholder="Cari data..."
                    @search="submitSearch"
                    @clear="clearSearch"
                />
            </div>

            <CrudTable
                :columns="columns"
                :rows="types.data"
                :empty-message="
                    search
                        ? 'Tidak ada type yang sesuai dengan pencarian.'
                        : 'Belum ada data asset type.'
                "
            >
                <template #cell-category="{ row }">
                    <div v-if="row.category">
                        <div
                            class="font-medium text-gray-700 dark:text-gray-300"
                        >
                            {{ row.category.name }}
                        </div>

                        <div class="text-xs text-gray-400 dark:text-gray-500">
                            {{ row.category.code }}
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
                                route('master.product.types.edit', row.id),
                            )
                        "
                        @delete="deleteType(row)"
                    />
                </template>
            </CrudTable>

            <div
                v-if="types.last_page > 1"
                class="flex flex-col gap-3 border-t border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ types.from }}–{{ types.to }} dari
                    {{ types.total }} data
                </p>

                <div class="flex flex-wrap gap-1">
                    <template v-for="link in types.links" :key="link.label">
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
            title="Hapus Asset Type"
            :message="
                selectedType
                    ? `Apakah Anda yakin ingin menghapus type '${selectedType.name}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
