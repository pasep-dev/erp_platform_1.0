<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import AppPagination from "@/components/common/AppPagination.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface Department { id: string; code: string; name: string; company: string | null }
interface Warehouse { id: string; code: string; name: string }
interface Category { id: string; name: string }
interface ProductWarehouse extends Warehouse { balance: number; minimum_quantity: number | null }
interface ProductRow {
    id: string;
    code: string;
    name: string;
    category: string | null;
    unit: string | null;
    inventory_tracking_mode: string;
    warehouses: ProductWarehouse[];
}

const props = defineProps<{
    departments: Department[];
    selectedDepartmentId: string | null;
    warehouseLocations: Warehouse[];
    categories: Category[];
    filters: { search: string; category_id: string; warehouse_id: string; per_page: number };
    products: {
        data: ProductRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const search = ref(props.filters.search);
const categoryId = ref(props.filters.category_id);
const warehouseId = ref(props.filters.warehouse_id);
const perPage = ref(props.products.per_page);
let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(() => props.filters.search, (value) => { search.value = value; });
watch(() => props.filters.category_id, (value) => { categoryId.value = value; });
watch(() => props.filters.warehouse_id, (value) => { warehouseId.value = value; });
watch(() => props.products.per_page, (value) => { perPage.value = value; });
onBeforeUnmount(() => clearTimeout(searchTimer));

const stockRows = computed(() => props.products.data.flatMap((product) =>
    product.warehouses.map((warehouse) => ({ product, warehouse })),
));

function applyFilters(): void {
    router.get(route("transaction.inventory.stocks.index"), {
        department_id: props.selectedDepartmentId ?? undefined,
        search: search.value || undefined,
        category_id: categoryId.value || undefined,
        warehouse_id: warehouseId.value || undefined,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function scheduleSearch(): void {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
}

function selectDepartment(event: Event): void {
    router.get(route("transaction.inventory.stocks.index"), {
        department_id: (event.target as HTMLSelectElement).value || undefined,
        search: search.value || undefined,
        category_id: categoryId.value || undefined,
        warehouse_id: warehouseId.value || undefined,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true });
}

function statusLabel(balance: number, minimum: number | null): string {
    if (balance <= 0) return "Habis";
    if (minimum !== null && balance < minimum) return "Menipis";
    return "Aman";
}

function statusClass(balance: number, minimum: number | null): string {
    if (balance <= 0) return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    if (minimum !== null && balance < minimum) return "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300";
    return "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300";
}
</script>

<template>
    <Head title="Stok Barang" />

    <div class="procurement-ui space-y-5">
        <InventoryPageHeader title="Stok Barang" section="Inventory Management" description="Lihat saldo fisik produk per gudang. Saldo berasal dari mutasi yang sudah diposting.">
            <template #actions>
                <Link :href="route('transaction.inventory.index', { department_id: selectedDepartmentId ?? undefined })" class="inline-flex min-h-10 items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">Ringkasan Stok</Link>
                <Link :href="route('transaction.inventory.transactions.index', { department_id: selectedDepartmentId ?? undefined })" class="inline-flex min-h-10 items-center rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800">Riwayat Mutasi</Link>
            </template>
        </InventoryPageHeader>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <div><h2 class="text-base font-semibold text-gray-900 dark:text-white">Saldo Produk</h2><p class="mt-0.5 text-xs text-gray-500">Permintaan yang belum dikeluarkan dan PO yang belum diterima belum mengurangi saldo ini.</p></div>
                <label class="text-xs text-gray-500">Departemen
                    <select :value="selectedDepartmentId ?? ''" :disabled="departments.length <= 1" class="ml-2 h-9 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200" @change="selectDepartment">
                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.code }} · {{ department.name }}{{ department.company ? ` (${department.company})` : '' }}</option>
                    </select>
                </label>
            </div>
            <div class="grid gap-2 border-b border-gray-200 p-4 md:grid-cols-[minmax(220px,1fr)_repeat(2,minmax(160px,220px))] dark:border-gray-800">
                <input v-model="search" type="search" placeholder="Cari kode atau nama produk..." class="h-10 rounded-lg border border-gray-300 px-3 text-sm outline-none transition hover:border-gray-400 focus:border-brand-700 focus:ring-2 focus:ring-brand-700/15 dark:border-gray-700 dark:bg-gray-950 dark:text-white" @input="scheduleSearch">
                <select v-model="warehouseId" class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" @change="applyFilters"><option value="">Semua gudang / lokasi</option><option v-for="warehouse in warehouseLocations" :key="warehouse.id" :value="warehouse.id">{{ warehouse.code }} · {{ warehouse.name }}</option></select>
                <select v-model="categoryId" class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" @change="applyFilters"><option value="">Semua kategori</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select>
            </div>
            <div class="flex justify-end px-4 pt-3 text-xs text-gray-500">Total: {{ products.total }} produk</div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left text-sm">
                    <thead class="bg-gray-50 text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-4 py-3">Kode Produk</th><th class="px-4 py-3">Produk</th><th class="px-4 py-3">Kategori</th><th class="px-4 py-3">Gudang / Lokasi</th><th class="px-4 py-3">Pelacakan</th><th class="px-4 py-3 text-right">Saldo</th><th class="px-4 py-3">Satuan</th><th class="px-4 py-3">Batas Minimum</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Kartu Stok</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="row in stockRows" :key="`${row.product.id}-${row.warehouse.id}`">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ row.product.code }}</td><td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ row.product.name }}</td><td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ row.product.category ?? '—' }}</td><td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ row.warehouse.name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ row.product.inventory_tracking_mode === 'SERIAL' ? 'Nomor seri' : 'Kuantitas' }}</td><td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900 dark:text-white">{{ row.warehouse.balance.toLocaleString('id-ID') }}</td><td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ row.product.unit ?? '—' }}</td><td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ row.warehouse.minimum_quantity?.toLocaleString('id-ID') ?? 'Nonaktif' }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(row.warehouse.balance, row.warehouse.minimum_quantity)">{{ statusLabel(row.warehouse.balance, row.warehouse.minimum_quantity) }}</span></td><td class="px-4 py-3 text-right"><Link :href="route('transaction.inventory.stock-card', { product: row.product.id, location: row.warehouse.id, department_id: selectedDepartmentId ?? undefined })" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Lihat</Link></td>
                        </tr>
                        <tr v-if="stockRows.length === 0"><td colspan="10" class="px-4 py-12 text-center"><p class="font-medium text-gray-900 dark:text-white">Tidak ada stok untuk filter ini</p><p class="mt-1 text-sm text-gray-500">Coba ubah departemen, gudang, kategori, atau kata pencarian.</p></td></tr>
                    </tbody>
                </table>
            </div>
            <AppPagination v-model:per-page="perPage" :meta="products" :per-page-options="[{ label: '5', value: 5 }, { label: '15', value: 15 }, { label: '20', value: 20 }, { label: '25', value: 25 }, { label: '50', value: 50 }, { label: '100', value: 100 }]" @change="applyFilters" />
        </section>
    </div>
</template>
