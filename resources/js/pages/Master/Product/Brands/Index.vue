<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, router, Head, useForm } from "@inertiajs/vue3";
import { Plus, Search, Award, Download, Upload } from "lucide-vue-next";
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
    description: string | null;
    is_active: boolean;
    created_at: string;
    categories: Array<{ id: string; code: string; name: string }>;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedBrands {
    data: Brand[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    brands: PaginatedBrands;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search);
const importForm = useForm({ file: null as File | null });
const importErrors = computed(() => Object.values(importForm.errors).filter(Boolean));
const fileInput = ref<HTMLInputElement | null>(null);

const hasBrands = computed(() => props.brands.data.length > 0);

const columns = [
    {
        key: "code",
        label: "Code",
    },
    {
        key: "name",
        label: "Brand Name",
    },
    {
        key: "categories",
        label: "Categories",
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
const selectedBrand = ref<Brand | null>(null);

const { processing, destroy } = useCrud({
    indexRoute: route("master.product.brands.index"),

    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedBrand.value = null;
    },
});

function submitSearch(): void {
    router.get(
        route("master.product.brands.index"),
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

function selectImportFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    importForm.clearErrors();
    importForm.file = input.files?.[0] ?? null;
}

function importBrands(): void {
    if (!importForm.file) {
        importForm.setError("file", "Pilih file Excel terlebih dahulu.");
        return;
    }

    importForm.post(route("master.product.brands.import"), {
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();
            if (fileInput.value) {
                fileInput.value.value = "";
            }
        },
    });
}

function deleteBrand(brand: Brand): void {
    selectedBrand.value = brand;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) {
        return;
    }

    showDeleteConfirm.value = false;
    selectedBrand.value = null;
}

function confirmDelete(): void {
    if (!selectedBrand.value) {
        return;
    }

    destroy(route("master.product.brands.destroy", selectedBrand.value.id));
}
</script>

<template>
    <div class="space-y-6">
        <Header
            title="Master Brands"
            :action-url="route('master.product.brands.create')"
            action-label="Add Brand"
        />

        <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Import Brand dari Excel</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Gunakan template untuk mengunggah beberapa brand sekaligus. Isi category_codes dengan kode pada sheet referensi, pisahkan beberapa kode memakai tanda |, lalu isi is_active dengan TRUE atau FALSE.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a :href="route('master.product.brands.template')" class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                        <Download class="size-4" /> Download Template
                    </a>
                    <label class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                        <Upload class="size-4" />
                        <span>{{ importForm.file?.name ?? 'Pilih file .xlsx' }}</span>
                        <input ref="fileInput" type="file" accept=".xlsx" class="sr-only" @change="selectImportFile" />
                    </label>
                    <button type="button" :disabled="importForm.processing" class="inline-flex h-10 items-center gap-2 rounded-lg bg-brand-700 px-4 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-60" @click="importBrands">
                        <Upload class="size-4" /> {{ importForm.processing ? 'Mengunggah...' : 'Upload Excel' }}
                    </button>
                </div>
            </div>
            <ul v-if="importErrors.length" class="mt-3 space-y-1 text-sm text-red-600 dark:text-red-400">
                <li v-for="(error, index) in importErrors" :key="index">{{ error }}</li>
            </ul>
        </section>

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <SearchFilterBar
                    v-model="search"
                    :total="brands.total"
                    placeholder="Cari data..."
                    @search="submitSearch"
                    @clear="clearSearch"
                />
            </div>

            <CrudTable
                :columns="columns"
                :rows="brands.data"
                :empty-message="
                    search
                        ? 'Tidak ada brand yang sesuai dengan pencarian.'
                        : 'Belum ada data asset brand.'
                "
            >
                <template #cell-categories="{ row }">
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="category in row.categories"
                            :key="category.id"
                            class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-950/40 dark:text-brand-300"
                        >
                            {{ category.name }}
                        </span>
                        <span v-if="row.categories.length === 0" class="text-gray-400">-</span>
                    </div>
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
                                route('master.product.brands.edit', row.id),
                            )
                        "
                        @delete="deleteBrand(row)"
                    />
                </template>
            </CrudTable>

            <div
                v-if="brands.last_page > 1"
                class="flex flex-col gap-3 border-t border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ brands.from }}–{{ brands.to }} dari
                    {{ brands.total }} data
                </p>

                <div class="flex flex-wrap gap-1">
                    <template v-for="link in brands.links" :key="link.label">
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
            title="Hapus Asset Brand"
            :message="
                selectedBrand
                    ? `Apakah Anda yakin ingin menghapus brand '${selectedBrand.name}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
