<script setup lang="ts">
import { computed, reactive } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import Button from "primevue/button";
import InputNumber from "primevue/inputnumber";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Tag from "primevue/tag";
import Textarea from "primevue/textarea";
import AppLayout from "@/layout/AppLayout.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface RequestItem {
    id: string;
    product_id: string;
    requested_quantity: string;
    fulfilled_quantity: string;
    remarks: string | null;
    product: { id: string; code: string; name: string; inventory_tracking_mode: string };
}

interface FulfillmentItem {
    inventory_request_item_id: string;
    product_id: string;
    quantity: number;
    serial_text: string;
    party_type: "USER" | "ASSET" | "LOCATION";
    party_user_id: string;
    party_asset_id: string;
    party_location_id: string;
    specification_text: string;
}

const props = defineProps<{
    inventoryRequest: {
        id: string;
        number: string;
        status: string;
        requested_at: string;
        required_at: string | null;
        submitted_at: string | null;
        remarks: string | null;
        requested_by: number;
        department: { id: string; code: string; name: string };
        branch: { id: string; code: string; name: string };
        requester: { id: number; name: string };
        items: RequestItem[];
        approval_request?: {
            id: string;
            status: string;
            current_level: number | null;
            levels: Array<{ id: string; level: number; status: string; approver?: { id: number; name: string }; remarks: string | null }>;
            actions: Array<{ id: string; action: string; remarks: string | null; user?: { name: string }; created_at: string }>;
        } | null;
    };
    warehouses: Array<{ id: string; code: string; name: string }>;
    users: Array<{ id: number; name: string }>;
    assets: Array<{ id: string; number: string }>;
    locations: Array<{ id: string; code: string; name: string }>;
    canSubmit: boolean;
    canFulfill: boolean;
}>();

const statusText: Record<string, string> = {
    DRAFT: "Draft",
    PENDING_APPROVAL: "Menunggu Persetujuan",
    APPROVED: "Disetujui · Menunggu Pengeluaran",
    PARTIALLY_FULFILLED: "Dipenuhi Sebagian",
    FULFILLED: "Terpenuhi",
    REJECTED: "Ditolak",
    CANCELLED: "Dibatalkan",
};

const approvalPending = computed(() => props.inventoryRequest.status === "PENDING_APPROVAL");
const destinationOptions = [
    { label: "Pengguna", value: "USER" },
    { label: "Aset", value: "ASSET" },
    { label: "Lokasi", value: "LOCATION" },
];

function newFulfillmentItem(item: RequestItem): FulfillmentItem {
    return {
        inventory_request_item_id: item.id,
        product_id: item.product_id,
        quantity: 0,
        serial_text: "",
        party_type: "USER",
        party_user_id: String(props.inventoryRequest.requested_by),
        party_asset_id: "",
        party_location_id: "",
        specification_text: "",
    };
}

const fulfillmentForm = useForm({
    source_location_id: "",
    occurred_at: new Date().toISOString().slice(0, 10),
    remarks: "",
    items: props.inventoryRequest.items.map(newFulfillmentItem),
});

const fulfillmentRows = computed(() => props.inventoryRequest.items
    .map((requestItem, index) => ({ requestItem, form: fulfillmentForm.items[index] }))
    .filter(({ requestItem }) => Number(requestItem.fulfilled_quantity) < Number(requestItem.requested_quantity)));

function remaining(item: RequestItem): number {
    return Math.max(0, Number(item.requested_quantity) - Number(item.fulfilled_quantity));
}

function submitRequest(): void {
    router.post(route("transaction.inventory.requests.submit", props.inventoryRequest.id));
}

function parseSpecifications(value: string): Record<string, string> {
    return Object.fromEntries(value.split(/\r?\n/)
        .map((line) => line.split("="))
        .filter((parts) => parts.length >= 2)
        .map(([key, ...values]) => [key.trim(), values.join("=").trim()])
        .filter(([key, itemValue]) => key !== "" && itemValue !== ""));
}

function submitFulfillment(): void {
    fulfillmentForm.transform((data) => ({
        ...data,
        items: data.items.filter((item) => Number(item.quantity) > 0).map((item) => ({
            inventory_request_item_id: item.inventory_request_item_id,
            product_id: item.product_id,
            quantity: item.quantity,
            serial_numbers: item.serial_text.split(/\r?\n/).map((serial) => serial.trim()).filter(Boolean),
            party_type: item.party_type,
            party_user_id: item.party_type === "USER" ? item.party_user_id || null : null,
            party_asset_id: item.party_type === "ASSET" ? item.party_asset_id || null : null,
            party_location_id: item.party_type === "LOCATION" ? item.party_location_id || null : null,
            specification_changes: parseSpecifications(item.specification_text),
        })),
    })).post(route("transaction.inventory.requests.fulfill", props.inventoryRequest.id), { preserveScroll: true });
}

function levelStatusLabel(status: string): string {
    return status === "WAITING" ? "Menunggu" : status === "PENDING" ? "Perlu diproses" : status === "APPROVED" ? "Disetujui" : "Ditolak";
}
</script>

<template>
    <Head :title="inventoryRequest.number" />

    <div class="procurement-ui space-y-6">
        <InventoryPageHeader :title="inventoryRequest.number" section="Permintaan Barang" :description="`${inventoryRequest.branch.code} · ${inventoryRequest.branch.name} / ${inventoryRequest.department.code} · ${inventoryRequest.department.name} / Pemohon: ${inventoryRequest.requester.name}`">
            <template #badge>
                <Tag :value="statusText[inventoryRequest.status] ?? inventoryRequest.status" :severity="inventoryRequest.status === 'REJECTED' ? 'danger' : inventoryRequest.status === 'FULFILLED' ? 'success' : approvalPending ? 'warn' : 'info'" />
            </template>
            <template #actions>
                <Link :href="route('transaction.inventory.requests.index')" class="inline-flex min-h-10 items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Kembali</Link>
                <Button v-if="canSubmit" label="Ajukan Persetujuan" :disabled="!inventoryRequest.items.length" @click="submitRequest" />
                <Link v-if="approvalPending" :href="route('approval.inbox.index')" class="inline-flex min-h-10 items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Lihat Approval Inbox</Link>
            </template>
        </InventoryPageHeader>

        <p v-if="$page.props.errors?.approval" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $page.props.errors.approval }}</p>

        <section class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
            <div class="space-y-5">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Daftar Barang</h2></div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[650px] text-left text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th class="px-5 py-3">Produk</th><th class="px-5 py-3">Metode</th><th class="px-5 py-3 text-right">Diminta</th><th class="px-5 py-3 text-right">Dipenuhi</th><th class="px-5 py-3 text-right">Sisa</th></tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800"><tr v-for="item in inventoryRequest.items" :key="item.id"><td class="px-5 py-4"><p class="font-medium text-gray-900 dark:text-white">{{ item.product.code }} · {{ item.product.name }}</p><p v-if="item.remarks" class="mt-1 text-xs text-gray-500">{{ item.remarks }}</p></td><td class="px-5 py-4 text-gray-600 dark:text-gray-400">{{ item.product.inventory_tracking_mode === "SERIAL" ? "Nomor seri" : "Kuantitas" }}</td><td class="px-5 py-4 text-right">{{ item.requested_quantity }}</td><td class="px-5 py-4 text-right">{{ item.fulfilled_quantity }}</td><td class="px-5 py-4 text-right font-semibold">{{ remaining(item) }}</td></tr></tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-200 px-5 py-4 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-300"><p><span class="font-medium">Tanggal diminta:</span> {{ inventoryRequest.requested_at }} <span v-if="inventoryRequest.required_at" class="ml-3"><span class="font-medium">Dibutuhkan:</span> {{ inventoryRequest.required_at }}</span></p><p v-if="inventoryRequest.remarks" class="mt-2"><span class="font-medium">Keterangan:</span> {{ inventoryRequest.remarks }}</p></div>
                </div>

                <section v-if="canFulfill" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div><h2 class="font-semibold text-gray-900 dark:text-white">Pengeluaran / Pemenuhan</h2><p class="mt-1 text-sm text-gray-500">Pilih gudang sumber dan kuantitas yang akan dikeluarkan. Sisa permintaan tetap terbuka untuk pemenuhan berikutnya.</p></div>
                    <div v-if="Object.keys(fulfillmentForm.errors).length" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ Object.values(fulfillmentForm.errors)[0] }}</div>
                    <form class="space-y-4" @submit.prevent="submitFulfillment">
                        <div class="grid gap-3 md:grid-cols-3"><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Gudang Sumber<Select v-model="fulfillmentForm.source_location_id" :options="warehouses.map((warehouse) => ({ label: `${warehouse.code} · ${warehouse.name}`, value: warehouse.id }))" option-label="label" option-value="value" placeholder="Pilih gudang" required fluid class="mt-1.5" /></label><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal pengeluaran<InputText v-model="fulfillmentForm.occurred_at" type="date" required fluid class="mt-1.5" /></label><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Catatan dokumen<Textarea v-model="fulfillmentForm.remarks" rows="1" fluid class="mt-1.5" /></label></div>
                        <article v-for="row in fulfillmentRows" :key="row.requestItem.id" class="space-y-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex flex-wrap items-center justify-between gap-2"><div><p class="font-medium text-gray-900 dark:text-white">{{ row.requestItem.product.code }} · {{ row.requestItem.product.name }}</p><p class="text-xs text-gray-500">Sisa {{ remaining(row.requestItem) }} {{ row.requestItem.product.inventory_tracking_mode === "SERIAL" ? "unit berseri" : "unit" }}</p></div><label class="w-36 text-sm font-medium text-gray-700 dark:text-gray-300">Dikeluarkan<InputNumber v-model="row.form.quantity" :min="0" :max="remaining(row.requestItem)" :max-fraction-digits="3" fluid class="mt-1.5" /></label></div>
                            <div v-if="row.form.quantity" class="grid gap-3 md:grid-cols-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tujuan<Select v-model="row.form.party_type" :options="destinationOptions" option-label="label" option-value="value" fluid class="mt-1.5" /></label>
                                <label v-if="row.form.party_type === 'USER'" class="text-sm font-medium text-gray-700 dark:text-gray-300">Pengguna tujuan<Select v-model="row.form.party_user_id" :options="users.map((user) => ({ label: user.name, value: String(user.id) }))" option-label="label" option-value="value" filter fluid class="mt-1.5" /></label>
                                <label v-else-if="row.form.party_type === 'ASSET'" class="text-sm font-medium text-gray-700 dark:text-gray-300">Aset tujuan<Select v-model="row.form.party_asset_id" :options="assets.map((asset) => ({ label: asset.number, value: asset.id }))" option-label="label" option-value="value" filter fluid class="mt-1.5" /></label>
                                <label v-else class="text-sm font-medium text-gray-700 dark:text-gray-300">Lokasi tujuan<Select v-model="row.form.party_location_id" :options="locations.map((location) => ({ label: `${location.code} · ${location.name}`, value: location.id }))" option-label="label" option-value="value" filter fluid class="mt-1.5" /></label>
                                <label v-if="row.requestItem.product.inventory_tracking_mode === 'SERIAL'" class="text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">Nomor seri, satu per baris<Textarea v-model="row.form.serial_text" rows="2" fluid class="mt-1.5" /></label>
                                <label v-if="row.form.party_type === 'ASSET'" class="text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">Spesifikasi yang dipasang (format RAM=16GB, satu per baris)<Textarea v-model="row.form.specification_text" rows="2" fluid class="mt-1.5" /></label>
                            </div>
                        </article>
                        <Button type="submit" label="Posting Pemenuhan" :loading="fulfillmentForm.processing" :disabled="fulfillmentForm.items.every((item) => Number(item.quantity) <= 0)" />
                    </form>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"><h2 class="font-semibold text-gray-900 dark:text-white">Persetujuan</h2><p v-if="!inventoryRequest.approval_request" class="mt-3 text-sm text-gray-500">Belum diajukan ke workflow approval.</p><template v-else><p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Status: {{ inventoryRequest.approval_request.status }}</p><ol class="mt-4 space-y-3"><li v-for="level in inventoryRequest.approval_request.levels" :key="level.id" class="flex items-start justify-between gap-3 border-l-2 border-violet-300 pl-3 dark:border-violet-800"><div><p class="text-sm font-medium text-gray-800 dark:text-gray-200">Level {{ level.level }} · {{ level.approver?.name }}</p><p v-if="level.remarks" class="mt-1 text-xs text-gray-500">{{ level.remarks }}</p></div><Tag :value="levelStatusLabel(level.status)" :severity="level.status === 'APPROVED' ? 'success' : level.status === 'REJECTED' ? 'danger' : 'warn'" /></li></ol></template></section>
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"><h2 class="font-semibold text-gray-900 dark:text-white">Riwayat Pengeluaran</h2><div v-if="inventoryRequest.items.some((item) => Number(item.fulfilled_quantity) > 0)" class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300"><p>Barang yang sudah dikeluarkan tercatat di Riwayat Operasi Inventory dan kartu stok.</p></div><p v-else class="mt-3 text-sm text-gray-500">Belum ada pengeluaran.</p></section>
            </aside>
        </section>
    </div>
</template>
