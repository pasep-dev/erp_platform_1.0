<script setup lang="ts">
import { reactive, ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import AppPagination from "@/components/common/AppPagination.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface Department {
    id: string;
    code: string;
    name: string;
    company: string | null;
}

interface ReceiptRecord {
    id: string;
    record_type: "GOODS_RECEIPT" | "MANUAL_RECEIPT";
    document_number: string;
    status: string;
    failure_message: string | null;
    product_code: string | null;
    product_name: string | null;
    warehouse: string | null;
    quantity: number | null;
    item_count: number;
    approval_mode: string | null;
    occurred_at: string;
}

const props = defineProps<{
    departments: Department[];
    selectedDepartmentId: string | null;
    canManage: boolean;
    receipts: {
        data: ReceiptRecord[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const serialForms = reactive<Record<string, { serials: string; processing: boolean; error: string }>>({});
const perPage = ref(props.receipts.per_page);

function selectDepartment(event: Event): void {
    router.get(route("transaction.inventory.receipts.index"), {
        department_id: (event.target as HTMLSelectElement).value || undefined,
        per_page: perPage.value,
    }, { preserveState: true });
}

function applyPagination(): void {
    router.get(route("transaction.inventory.receipts.index"), {
        department_id: props.selectedDepartmentId ?? undefined,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function serialForm(record: ReceiptRecord): { serials: string; processing: boolean; error: string } {
    if (!serialForms[record.id]) {
        serialForms[record.id] = { serials: "", processing: false, error: "" };
    }

    return serialForms[record.id];
}

function submitSerials(record: ReceiptRecord): void {
    const form = serialForm(record);
    const serialNumbers = form.serials.split(/\r?\n/).map((serial) => serial.trim()).filter(Boolean);
    form.processing = true;
    form.error = "";
    router.post(route("transaction.inventory.receipt-integrations.serials", record.id), { serial_numbers: serialNumbers }, {
        preserveScroll: true,
        onError: (errors) => { form.error = errors.serial_numbers ?? "Nomor seri tidak dapat disimpan."; },
        onFinish: () => { form.processing = false; },
    });
}

function retry(record: ReceiptRecord): void {
    router.post(route("transaction.inventory.receipt-integrations.retry", record.id), {}, { preserveScroll: true });
}

function statusLabel(record: ReceiptRecord): string {
    if (record.record_type === "MANUAL_RECEIPT") {
        if (record.status === "PENDING_APPROVAL") {
            return "Menunggu Approval";
        }

        return record.status === "POSTED" ? "Masuk Stok" : record.status.replaceAll("_", " ");
    }

    const labels: Record<string, string> = {
        PENDING_WAREHOUSE: "Menunggu Gudang",
        PENDING_SERIALS: "Menunggu Nomor Seri",
        FAILED: "Perlu Ditinjau",
        PROCESSED: "Masuk Stok",
    };

    return labels[record.status] ?? record.status.replaceAll("_", " ");
}

function statusClass(record: ReceiptRecord): string {
    if (record.status === "PROCESSED" || record.status === "POSTED") {
        return "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300";
    }

    if (record.status === "FAILED" || record.status === "REJECTED") {
        return "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300";
    }

    return "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300";
}
</script>

<template>
    <Head title="Penerimaan Barang" />

    <div class="procurement-ui space-y-6">
        <InventoryPageHeader title="Penerimaan Barang" section="Receiving" description="Penerimaan dari GR dan saldo awal tercatat dalam satu daftar dengan status proses masing-masing dokumen.">
            <template #actions>
                <Link v-if="canManage" :href="route('transaction.inventory.receipts.manual.create', { department_id: selectedDepartmentId ?? undefined })" class="inline-flex min-h-10 items-center rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800">Penerimaan Manual</Link>
                <Link :href="route('transaction.inventory.transactions.index', { department_id: selectedDepartmentId ?? undefined })" class="inline-flex min-h-10 items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Transaksi Stok</Link>
            </template>
        </InventoryPageHeader>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <label for="department" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Departemen</label>
            <select id="department" :value="selectedDepartmentId ?? ''" class="h-10 w-full max-w-lg rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" @change="selectDepartment">
                <option value="">Pilih departemen</option>
                <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.code }} · {{ department.name }} ({{ department.company ?? '—' }})</option>
            </select>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-900 dark:text-white">Daftar Penerimaan</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ receipts.total }} dokumen · GR yang berhasil diposting langsung tercatat sebagai stok; status tertunda atau gagal hanya memerlukan tindak lanjut jika muncul.</p>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1080px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3">Tanggal / Dokumen</th>
                            <th class="px-5 py-3">Jenis</th>
                            <th class="px-5 py-3">Produk / Isi</th>
                            <th class="px-5 py-3">Gudang</th>
                            <th class="px-5 py-3">Kuantitas / Baris</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Tindak Lanjut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="record in receipts.data" :key="`${record.record_type}-${record.id}`">
                            <td class="whitespace-nowrap px-5 py-3">
                                <span class="font-medium text-gray-900 dark:text-white">{{ record.document_number }}</span>
                                <span class="block text-xs text-gray-500">{{ new Date(record.occurred_at).toLocaleDateString('id-ID') }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-gray-700 dark:text-gray-300">{{ record.record_type === 'GOODS_RECEIPT' ? 'Dari GR' : 'Manual / Saldo Awal' }}</td>
                            <td class="px-5 py-3 text-gray-700 dark:text-gray-300">
                                <template v-if="record.record_type === 'GOODS_RECEIPT'">
                                    <span class="font-medium">{{ record.product_code }}</span>
                                    <span class="block text-xs text-gray-500">{{ record.product_name }}</span>
                                    <span v-if="record.failure_message" class="mt-1 block text-xs text-amber-700 dark:text-amber-300">{{ record.failure_message }}</span>
                                </template>
                                <span v-else>{{ record.item_count }} baris produk</span>
                            </td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ record.warehouse ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-gray-600 dark:text-gray-400">{{ record.record_type === 'GOODS_RECEIPT' ? record.quantity : `${record.item_count} baris` }}</td>
                            <td class="whitespace-nowrap px-5 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(record)">{{ statusLabel(record) }}</span></td>
                            <td class="min-w-64 px-5 py-3">
                                <Link v-if="record.record_type === 'MANUAL_RECEIPT'" :href="route('transaction.inventory.receipts.manual.show', record.id)" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Detail</Link>
                                <form v-else-if="record.status === 'PENDING_SERIALS' && canManage" class="min-w-56 space-y-2" @submit.prevent="submitSerials(record)">
                                    <label :for="`serials-${record.id}`" class="block text-xs text-gray-600 dark:text-gray-400">{{ record.quantity }} nomor, satu per baris</label>
                                    <textarea :id="`serials-${record.id}`" v-model="serialForm(record).serials" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="SN-001&#10;SN-002"></textarea>
                                    <p v-if="serialForm(record).error" class="text-xs text-red-600">{{ serialForm(record).error }}</p>
                                    <button type="submit" :disabled="serialForm(record).processing" class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50">Simpan Nomor Seri</button>
                                </form>
                                <button v-else-if="record.status === 'FAILED' && canManage" type="button" class="font-medium text-brand-700 hover:underline dark:text-brand-300" @click="retry(record)">Coba Sinkronkan Lagi</button>
                                <Link v-else-if="record.status === 'PENDING_WAREHOUSE'" :href="route('transaction.inventory.index', { department_id: selectedDepartmentId ?? undefined })" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Atur Gudang</Link>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                        </tr>
                        <tr v-if="receipts.data.length === 0">
                            <td colspan="7" class="px-5 py-12 text-center">
                                <p class="font-medium text-gray-900 dark:text-white">Belum ada penerimaan</p>
                                <p class="mt-1 text-sm text-gray-500">GR yang diposting dan penerimaan manual akan tampil di sini.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <AppPagination v-model:per-page="perPage" :meta="receipts" @change="applyPagination" />
        </section>
    </div>
</template>
