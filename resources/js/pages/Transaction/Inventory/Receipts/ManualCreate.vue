<script setup lang="ts">
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface ProductOption {
    id: string;
    code: string;
    name: string;
    inventory_tracking_mode: string;
}

interface ReceiptLine {
    product_id: string;
    quantity: number | string;
    serial_text: string;
    reason: string;
}

const props = defineProps<{
    departments: Array<{ id: string; code: string; name: string; company: string | null }>;
    departmentId: string | null;
    warehouses: Array<{ id: string; code: string; name: string }>;
    products: ProductOption[];
}>();

const form = useForm({
    department_id: props.departmentId ?? "",
    location_id: "",
    occurred_at: new Date().toISOString().slice(0, 10),
    remarks: "",
    approval_mode: "WORKFLOW",
    items: [{ product_id: "", quantity: 1, serial_text: "", reason: "" }] as ReceiptLine[],
});

function selectDepartment(event: Event): void {
    form.location_id = "";
    router.get(route("transaction.inventory.receipts.manual.create"), {
        department_id: (event.target as HTMLSelectElement).value || undefined,
    }, { preserveState: true });
}

function addLine(): void {
    form.items.push({ product_id: "", quantity: 1, serial_text: "", reason: "" });
}

function removeLine(index: number): void {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
}

function trackingMode(productId: string): string | undefined {
    return props.products.find((product) => product.id === productId)?.inventory_tracking_mode;
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        items: data.items.map((item) => ({
            product_id: item.product_id,
            quantity: item.quantity,
            serial_numbers: item.serial_text.split(/\r?\n/).map((serial) => serial.trim()).filter(Boolean),
            reason: item.reason || null,
        })),
    })).post(route("transaction.inventory.receipts.manual.store"));
}
</script>

<template>
    <Head title="Penerimaan Manual" />

    <div class="procurement-ui space-y-6">
        <InventoryPageHeader title="Penerimaan Manual" section="Penerimaan Stok" description="Catat saldo barang existing. Stok baru berubah setelah persetujuan workflow atau Mark Approval manual.">
            <template #actions>
                <Link :href="route('transaction.inventory.receipts.index', { department_id: departmentId ?? undefined })" class="inline-flex min-h-10 items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Kembali ke Penerimaan</Link>
            </template>
        </InventoryPageHeader>

        <form class="space-y-5" @submit.prevent="submit">
            <div v-if="Object.keys(form.errors).length" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
            </div>
            <section class="grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Departemen
                    <select v-model="form.department_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" @change="selectDepartment">
                        <option value="" disabled>Pilih departemen</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.code }} · {{ department.name }} ({{ department.company ?? '—' }})</option>
                    </select>
                </label>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gudang tujuan
                    <select v-model="form.location_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                        <option value="" disabled>Pilih gudang terdaftar</option>
                        <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.code }} · {{ warehouse.name }}</option>
                    </select>
                    <small v-if="warehouses.length === 0" class="mt-1 block font-normal text-amber-700">Belum ada gudang yang dipetakan untuk departemen ini. Atur di Ringkasan Stok terlebih dahulu.</small>
                </label>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal cut-off saldo
                    <input v-model="form.occurred_at" type="date" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                </label>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan / referensi data existing
                    <input v-model="form.remarks" required maxlength="2000" class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="Contoh: saldo awal hasil stock opname 31 Desember" />
                </label>
                <fieldset class="space-y-2 md:col-span-2">
                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">Metode persetujuan</legend>
                    <label class="flex items-start gap-2 rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                        <input v-model="form.approval_mode" type="radio" value="WORKFLOW" class="mt-0.5 text-brand-700" />
                        <span><span class="font-medium text-gray-900 dark:text-white">Workflow approval</span><span class="mt-0.5 block text-gray-500">Dikirim ke approver sesuai workflow departemen. Persetujuan dilakukan melalui My Approval.</span></span>
                    </label>
                    <label class="flex items-start gap-2 rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                        <input v-model="form.approval_mode" type="radio" value="MANUAL" class="mt-0.5 text-brand-700" />
                        <span><span class="font-medium text-gray-900 dark:text-white">Mark Approval manual</span><span class="mt-0.5 block text-gray-500">Dipakai jika persetujuan sudah dilakukan di luar aplikasi. Bukti dan catatan persetujuan wajib diunggah sebelum stok diposting.</span></span>
                    </label>
                </fieldset>
            </section>

            <section class="space-y-4">
                <article v-for="(item, index) in form.items" :key="index" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-gray-900 dark:text-white">Produk {{ index + 1 }}</h2>
                        <button v-if="form.items.length > 1" type="button" class="text-sm font-medium text-red-600" @click="removeLine(index)">Hapus</button>
                    </div>
                    <div class="grid gap-4 md:grid-cols-[minmax(16rem,2fr)_minmax(9rem,1fr)]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Produk consumable
                            <select v-model="item.product_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                <option value="" disabled>Pilih produk</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">{{ product.code }} · {{ product.name }} · {{ product.inventory_tracking_mode === 'SERIAL' ? 'Nomor seri' : 'Kuantitas' }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kuantitas
                            <input v-model="item.quantity" type="number" min="0.001" step="0.001" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                        </label>
                    </div>
                    <label v-if="trackingMode(item.product_id) === 'SERIAL'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nomor seri, satu per baris
                        <textarea v-model="item.serial_text" rows="4" required class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="SN-001&#10;SN-002"></textarea>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan produk (opsional)
                        <input v-model="item.reason" maxlength="1000" class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                    </label>
                </article>
                <p v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</p>
                <button type="button" class="rounded-lg border border-brand-300 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:border-brand-800 dark:text-brand-300 dark:hover:bg-brand-900/20" @click="addLine">+ Tambah Produk</button>
            </section>

            <div class="flex justify-end gap-3">
                <Link :href="route('transaction.inventory.receipts.index', { department_id: departmentId ?? undefined })" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">Batal</Link>
                <button type="submit" :disabled="form.processing || !warehouses.length || !products.length" class="rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Mengirim…' : 'Ajukan Persetujuan' }}</button>
            </div>
        </form>
    </div>
</template>
