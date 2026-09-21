<script setup lang="ts">
import { ref } from "vue";
import { Link, router, Head } from "@inertiajs/vue3";
import { Search } from "lucide-vue-next";
import { route } from "ziggy-js";

import AppLayout from "@/layouts/AppLayout.vue";
import CrudActions from "@/components/crud/CrudActions.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import DeleteConfirm from "@/components/crud/DeleteConfirm.vue";
import { useCrud } from "@/composables/useCrud";
import Header from "@/components/content/Header.vue";

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
    <Head title="Master - Tax" />

    <div class="space-y-6">
        <Header
            title="Tax"
            :action-url="route('master.procurement.taxes.create')"
            action-label="Add New"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <form
                    class="flex w-full flex-col gap-2 sm:max-w-md sm:flex-row"
                    @submit.prevent="submitSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                        />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Cari kode, nama, atau description..."
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent pl-9 pr-3 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                        />
                    </div>

                    <button
                        type="submit"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.05]"
                    >
                        Cari
                    </button>

                    <button
                        v-if="search"
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.05]"
                        @click="clearSearch"
                    >
                        Reset
                    </button>
                </form>

                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Total: {{ taxes.total }}
                </div>
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
