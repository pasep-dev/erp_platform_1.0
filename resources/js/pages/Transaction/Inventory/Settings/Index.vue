<script setup lang="ts">
import { reactive } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface Department { id: string; code: string; name: string; company: string | null }
interface Warehouse { id: string; code: string; name: string }
interface StockSetting { id: string; name: string; minimum_quantity: number | null }
interface Product { id: string; code: string; name: string; warehouses: StockSetting[] }

const props = defineProps<{
    departments: Department[];
    selectedDepartmentId: string | null;
    warehouses: Warehouse[];
    products: Product[];
}>();

const minimums = reactive<Record<string, string>>({});
for (const product of props.products) {
    for (const warehouse of product.warehouses) {
        minimums[`${product.id}:${warehouse.id}`] = warehouse.minimum_quantity?.toString() ?? "";
    }
}

function selectDepartment(event: Event): void {
    router.get(route("transaction.inventory.settings"), { department_id: (event.target as HTMLSelectElement).value }, { preserveState: true });
}

function save(product: Product, warehouse: StockSetting): void {
    const value = minimums[`${product.id}:${warehouse.id}`];
    router.put(route("transaction.inventory.minimum-stock.update", {
        department: props.selectedDepartmentId,
        location: warehouse.id,
        product: product.id,
    }), { minimum_quantity: value === "" ? null : Number(value) }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Pengaturan Inventori" />
    <div class="procurement-ui space-y-5">
        <InventoryPageHeader title="Pengaturan Inventori" section="Settings" description="Atur batas minimum per produk dan gudang atau lokasi penyimpanan. Nilai kosong menonaktifkan peringatan stok minimum.">
            <template #actions><Link :href="route('transaction.inventory.index', { department_id: selectedDepartmentId ?? undefined })" class="inline-flex min-h-10 items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">Kembali ke Ringkasan</Link></template>
        </InventoryPageHeader>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <div><h2 class="text-base font-semibold text-gray-900 dark:text-white">Batas Minimum Stok</h2><p class="mt-1 text-xs text-gray-500">Nilai disimpan untuk kombinasi departemen, gudang/lokasi, dan produk.</p></div>
                <label class="text-xs text-gray-500">Departemen<select :value="selectedDepartmentId ?? ''" :disabled="departments.length <= 1" class="ml-2 h-9 rounded-lg border border-gray-300 bg-white px-3 text-sm disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white" @change="selectDepartment"><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.code }} · {{ department.name }}{{ department.company ? ` (${department.company})` : '' }}</option></select></label>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-4 py-3">Produk</th><th class="px-4 py-3">Gudang / Lokasi</th><th class="px-4 py-3">Batas Minimum</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template v-for="product in products" :key="product.id">
                            <tr v-for="warehouse in product.warehouses" :key="`${product.id}-${warehouse.id}`">
                                <td class="px-4 py-3"><span class="font-medium text-gray-900 dark:text-white">{{ product.code }}</span><span class="ml-2 text-gray-600 dark:text-gray-300">{{ product.name }}</span></td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ warehouse.name }}</td>
                                <td class="px-4 py-3"><input v-model="minimums[`${product.id}:${warehouse.id}`]" type="number" min="0" step="0.001" class="h-9 w-36 rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-700 focus:ring-2 focus:ring-brand-700/15 dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="Nonaktif"></td>
                                <td class="px-4 py-3 text-right"><button type="button" class="rounded-lg bg-brand-700 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-800" @click="save(product, warehouse)">Simpan</button></td>
                            </tr>
                        </template>
                        <tr v-if="warehouses.length === 0 || products.length === 0"><td colspan="4" class="px-4 py-12 text-center text-sm text-gray-500">Belum ada produk atau gudang yang terdaftar untuk departemen ini. Penerimaan GR pertama akan mendaftarkan gudangnya.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
