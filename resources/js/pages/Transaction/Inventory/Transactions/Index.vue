<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import AppPagination from "@/components/common/AppPagination.vue";
import AppSelect from "@/components/common/AppSelect.vue";
import DataTableToolbar from "@/components/common/DataTableToolbar.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface Department {
    id: string;
    code: string;
    name: string;
    company: string | null;
}

interface InventoryDocument {
    id: string;
    number: string;
    type: string;
    status: string;
    occurred_at: string;
    source: string | null;
    target: string | null;
    department: { id: string; code: string; name: string } | null;
    performed_by: string | null;
    items: Array<{ product_code: string | null; product_name: string | null; quantity: number }>;
}

const props = defineProps<{
    departments: Department[];
    selectedDepartmentId: string | null;
    canManage: boolean;
    documents: {
        data: InventoryDocument[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search: string; type: string; per_page: number };
}>();

const search = ref(props.filters.search);
const type = ref(props.filters.type || "ALL");
const perPage = ref(props.documents.per_page);
const typeOptions = [
    { label: "Semua jenis", value: "ALL" },
    { label: "Pengeluaran", value: "ISSUE" },
    { label: "Transfer", value: "TRANSFER" },
    { label: "Retur", value: "RETURN" },
    { label: "Koreksi stok", value: "ADJUSTMENT" },
    { label: "Saldo awal", value: "OPENING_BALANCE" },
];

const operationActions = [
    { label: "Pengeluaran Barang", route: "transaction.inventory.issues.create" },
    { label: "Transfer Gudang", route: "transaction.inventory.transfers.create" },
    { label: "Retur Barang", route: "transaction.inventory.returns.create" },
    { label: "Koreksi Stok", route: "transaction.inventory.adjustments.create" },
] as const;

function filterTransactions(): void {
    router.get(route("transaction.inventory.transactions.index"), {
        department_id: props.selectedDepartmentId ?? undefined,
        search: search.value || undefined,
        type: type.value === "ALL" ? undefined : type.value,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function selectDepartment(event: Event): void {
    router.get(route("transaction.inventory.transactions.index"), {
        department_id: (event.target as HTMLSelectElement).value || undefined,
        search: search.value || undefined,
        type: type.value === "ALL" ? undefined : type.value,
        per_page: perPage.value,
    }, { preserveState: true });
}

function documentTypeLabel(documentType: string): string {
    const labels: Record<string, string> = {
        ISSUE: "Pengeluaran",
        TRANSFER: "Transfer",
        RETURN: "Retur",
        ADJUSTMENT: "Koreksi",
        OPENING_BALANCE: "Saldo awal",
    };

    return labels[documentType] ?? documentType.replaceAll("_", " ");
}
</script>

<template>
    <Head title="Transaksi Stok" />

    <div class="procurement-ui space-y-6">
        <InventoryPageHeader title="Transaksi Stok" section="Transactions" description="Telusuri riwayat pergerakan stok dan mulai operasi inventory dari satu halaman.">
            <template #actions>
                <Link v-for="action in operationActions" :key="action.route" :href="route(action.route, { department_id: selectedDepartmentId ?? undefined })" class="inline-flex min-h-10 items-center rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 transition hover:border-brand-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ action.label }}</Link>
            </template>
        </InventoryPageHeader>

        <section class="grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-[minmax(15rem,1fr)_minmax(12rem,0.7fr)]">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Departemen
                <select :value="selectedDepartmentId ?? ''" class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" @change="selectDepartment">
                    <option value="">Pilih departemen</option>
                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.code }} · {{ department.name }} ({{ department.company ?? '—' }})</option>
                </select>
            </label>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h2 class="font-semibold text-gray-900 dark:text-white">Riwayat Pergerakan Stok</h2>
            </div>
            <DataTableToolbar v-model="search" :total="documents.total" total-label="Transaksi" placeholder="Cari nomor dokumen, catatan, atau produk..." @search="filterTransactions">
                <AppSelect v-model="type" :options="typeOptions" class="w-full sm:w-48" aria-label="Filter jenis transaksi" @change="filterTransactions" />
            </DataTableToolbar>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-5 py-3">Nomor Dokumen</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Produk</th><th class="px-5 py-3">Gudang Asal / Tujuan</th><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Petugas</th><th class="px-5 py-3">Status</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="document in documents.data" :key="document.id">
                            <td class="whitespace-nowrap px-5 py-3 font-medium text-gray-900 dark:text-white">{{ document.number }}</td>
                            <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ documentTypeLabel(document.type) }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400"><span v-for="(item, index) in document.items" :key="`${document.id}-${index}`" class="block">{{ item.product_code }} · {{ item.product_name }} <span class="text-gray-400">({{ item.quantity.toLocaleString('id-ID') }})</span></span></td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ document.source ?? '—' }}<span v-if="document.target"> → {{ document.target }}</span></td>
                            <td class="whitespace-nowrap px-5 py-3 text-gray-600 dark:text-gray-400">{{ new Date(document.occurred_at).toLocaleDateString('id-ID') }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ document.performed_by ?? '—' }}</td>
                            <td class="px-5 py-3"><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">{{ document.status }}</span></td>
                        </tr>
                        <tr v-if="documents.data.length === 0"><td colspan="7" class="px-5 py-10 text-center text-gray-500">Belum ada transaksi stok yang cocok dengan filter ini.</td></tr>
                    </tbody>
                </table>
            </div>
            <AppPagination v-model:per-page="perPage" :meta="documents" :per-page-options="[{ label: '5', value: 5 }, { label: '15', value: 15 }, { label: '25', value: 25 }, { label: '50', value: 50 }, { label: '100', value: 100 }]" @change="filterTransactions" />
        </section>
    </div>
</template>
