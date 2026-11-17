<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface Department { id: string; code: string; name: string; company: string | null }
interface Alert {
    id: string;
    product_id: string;
    product: { code: string; name: string } | null;
    location: { name: string } | null;
    balance: number;
    minimum_quantity: number;
    shortage_quantity: number;
}

const props = defineProps<{
    departments: Department[];
    selectedDepartmentId: string | null;
    stats: { products: number; below_minimum: number; out_of_stock: number; pending_requests: number };
    lowStockAlerts: Alert[];
    lowStockAlertCount: number;
}>();

const showAllAlerts = ref(false);
const visibleAlerts = computed(() => showAllAlerts.value ? props.lowStockAlerts : props.lowStockAlerts.slice(0, 6));

function selectDepartment(event: Event): void {
    router.get(route("transaction.inventory.index"), {
        department_id: (event.target as HTMLSelectElement).value || undefined,
    }, { preserveScroll: true });
}

function purchaseRequestUrl(alert: Alert): string {
    const query = new URLSearchParams({ product_id: alert.product_id, quantity: String(alert.shortage_quantity) });
    return `${route("transaction.procurement.purchase-requests.create")}?${query.toString()}`;
}
</script>

<template>
    <Head title="Ringkasan Stok" />

    <div class="procurement-ui space-y-5">
        <InventoryPageHeader title="Ringkasan Stok" section="Overview" description="Pantau jumlah produk, peringatan stok, dan saldo persediaan per gudang.">
            <template #actions>
                <Link :href="route('transaction.inventory.stocks.index', { department_id: selectedDepartmentId ?? undefined })" class="inline-flex min-h-10 items-center rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800">Stok Barang</Link>
                <Link :href="route('transaction.inventory.transactions.index', { department_id: selectedDepartmentId ?? undefined })" class="inline-flex min-h-10 items-center rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800">Riwayat Mutasi</Link>
                <Link :href="route('transaction.inventory.settings', { department_id: selectedDepartmentId ?? undefined })" class="inline-flex min-h-10 items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">Pengaturan Inventori</Link>
            </template>
        </InventoryPageHeader>

        <div class="flex justify-end rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <label class="text-xs text-gray-500">Departemen
                <select :value="selectedDepartmentId ?? ''" :disabled="departments.length <= 1" class="ml-2 h-9 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200" @change="selectDepartment">
                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.code }} · {{ department.name }}{{ department.company ? ` (${department.company})` : '' }}</option>
                </select>
            </label>
        </div>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"><p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Produk</p><p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.products }}</p></article>
            <article class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 shadow-sm dark:border-amber-900 dark:bg-amber-950/20"><p class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">Di Bawah Minimum</p><p class="mt-2 text-2xl font-semibold text-amber-900 dark:text-amber-100">{{ stats.below_minimum }}</p></article>
            <article class="rounded-xl border border-red-200 bg-red-50/70 p-4 shadow-sm dark:border-red-900 dark:bg-red-950/20"><p class="text-xs font-medium uppercase tracking-wide text-red-700 dark:text-red-300">Stok Habis</p><p class="mt-2 text-2xl font-semibold text-red-900 dark:text-red-100">{{ stats.out_of_stock }}</p></article>
            <Link :href="route('transaction.inventory.requests.index', { department_id: selectedDepartmentId ?? undefined, status: 'PENDING_APPROVAL' })" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-brand-300 dark:border-gray-800 dark:bg-gray-900"><p class="text-xs font-medium uppercase tracking-wide text-gray-500">Permintaan Menunggu</p><p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.pending_requests }}</p></Link>
        </section>

        <details class="rounded-xl border border-amber-200 bg-amber-50/70 shadow-sm dark:border-amber-900 dark:bg-amber-950/20">
            <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-4 py-3">
                <span><span class="text-sm font-semibold text-amber-950 dark:text-amber-100">Peringatan stok minimum</span><span class="ml-2 text-xs text-amber-800 dark:text-amber-200">{{ lowStockAlertCount }} produk</span></span>
                <span class="text-xs font-medium text-amber-800 dark:text-amber-200">Buka / tutup</span>
            </summary>
            <div class="border-t border-amber-200 px-4 py-3 dark:border-amber-900">
                <div v-if="visibleAlerts.length" class="space-y-2">
                    <article v-for="alert in visibleAlerts" :key="alert.id" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm dark:border-amber-900 dark:bg-gray-900">
                        <div><p class="font-medium text-gray-900 dark:text-white">{{ alert.product?.code }} · {{ alert.product?.name }}</p><p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">{{ alert.location?.name }} · saldo {{ alert.balance }} / minimum {{ alert.minimum_quantity }}</p></div>
                        <Link :href="purchaseRequestUrl(alert)" class="rounded-lg bg-brand-700 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-800">Buat Permintaan Pembelian · {{ alert.shortage_quantity }}</Link>
                    </article>
                    <button v-if="lowStockAlerts.length > 6" type="button" class="text-xs font-semibold text-brand-700 hover:underline dark:text-brand-300" @click="showAllAlerts = !showAllAlerts">{{ showAllAlerts ? 'Tampilkan lebih sedikit' : 'Lihat semua' }}</button>
                </div>
                <p v-else class="text-sm text-amber-800 dark:text-amber-200">Tidak ada produk yang berada di bawah batas minimum.</p>
            </div>
        </details>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h2 class="text-base font-semibold text-gray-900 dark:text-white">Saldo berdasarkan gudang</h2><p class="mt-1 text-sm text-gray-500">Lihat saldo per produk dengan filter gudang, kategori, dan pencarian.</p></div>
                <Link :href="route('transaction.inventory.stocks.index', { department_id: selectedDepartmentId ?? undefined })" class="inline-flex min-h-10 items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Buka Stok Barang</Link>
            </div>
        </section>
    </div>
</template>
