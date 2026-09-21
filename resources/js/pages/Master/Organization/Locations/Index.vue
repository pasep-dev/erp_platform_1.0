<script setup lang="ts">
import { ref } from "vue";
import { Link, router, Head } from "@inertiajs/vue3";
import { Plus, Search, MapPin } from "lucide-vue-next";
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

interface Company {
    id: string;
    code: string;
    name: string;
}

interface Branch {
    id: string;
    code: string;
    name: string;
}

interface ParentLocation {
    id: string;
    code: string;
    name: string;
}

interface Location {
    id: string;
    company_id: string;
    branch_id: string | null;
    code: string;
    name: string;
    parent_id: string | null;
    location_type: string | null;
    is_active: boolean;
    created_at: string;
    company?: Company;
    branch?: Branch | null;
    parent?: ParentLocation | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedLocations {
    data: Location[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    locations: PaginatedLocations;
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
        key: "branch",
        label: "Branch",
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
        key: "parent",
        label: "Parent",
    },
    {
        key: "location_type",
        label: "Type",
    },
    {
        key: "is_active",
        label: "Status",
    },
];

const showDeleteConfirm = ref(false);

const selectedLocation = ref<Location | null>(null);

const { processing, destroy } = useCrud({
    indexRoute: route("master.organization.locations.index"),

    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedLocation.value = null;
    },
});

function submitSearch(): void {
    router.get(
        route("master.organization.locations.index"),
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

function deleteLocation(location: Location): void {
    selectedLocation.value = location;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) {
        return;
    }

    showDeleteConfirm.value = false;
    selectedLocation.value = null;
}

function confirmDelete(): void {
    if (!selectedLocation.value) {
        return;
    }

    destroy(
        route(
            "master.organization.locations.destroy",
            selectedLocation.value.id,
        ),
    );
}
</script>

<template>
    <Head title="Master - Locations" />

    <div class="space-y-6">
        <Header
            title="Locations"
            :action-url="route('master.organization.locations.create')"
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
                            placeholder="Cari company, branch, kode atau nama location..."
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
                    Total: {{ locations.total }}
                </div>
            </div>

            <CrudTable
                :columns="columns"
                :rows="locations.data"
                empty-message="Tidak ada data location."
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

                <template #cell-branch="{ row }">
                    <div v-if="row.branch">
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ row.branch.name }}
                        </div>

                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ row.branch.code }}
                        </div>
                    </div>

                    <span v-else>-</span>
                </template>

                <template #cell-code="{ row }">
                    <span class="font-medium text-gray-900 dark:text-white">
                        {{ row.code }}
                    </span>
                </template>

                <template #cell-name="{ row }">
                    {{ row.name }}
                </template>

                <template #cell-parent="{ row }">
                    <div v-if="row.parent">
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ row.parent.name }}
                        </div>

                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ row.parent.code }}
                        </div>
                    </div>

                    <span v-else>-</span>
                </template>

                <template #cell-location_type="{ row }">
                    {{ row.location_type || "-" }}
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
                                    'master.organization.locations.edit',
                                    row.id,
                                ),
                            )
                        "
                        @delete="deleteLocation(row)"
                    />
                </template>
            </CrudTable>

            <div
                v-if="locations.last_page > 1"
                class="flex flex-col gap-3 border-t border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ locations.from }}–{{ locations.to }} dari
                    {{ locations.total }} data
                </p>

                <div class="flex flex-wrap gap-1">
                    <template v-for="link in locations.links" :key="link.label">
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
            title="Hapus Location"
            :message="
                selectedLocation
                    ? `Apakah Anda yakin ingin menghapus location '${selectedLocation.name}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
