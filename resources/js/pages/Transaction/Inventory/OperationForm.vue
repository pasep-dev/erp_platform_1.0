<script setup lang="ts">
import { computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface Option {
    id: string;
    code?: string;
    name?: string;
    number?: string;
    email?: string;
    inventory_tracking_mode?: string;
    product_id?: string;
    serial_number?: string | null;
}

interface OperationItem {
    product_id: string;
    quantity: number | string;
    serial_text: string;
    party_type: string;
    party_user_id: string;
    party_asset_id: string;
    party_location_id: string;
    specification_text: string;
    related_issue_item_id: string;
    reason: string;
}

const props = defineProps<{
    operationType: "ISSUE" | "TRANSFER" | "RETURN" | "ADJUSTMENT";
    departments: Array<{ id: string; code: string; name: string }>;
    departmentId: string | null;
    warehouses: Option[];
    products: Option[];
    users: Option[];
    assets: Option[];
    locations: Option[];
}>();

const titles = {
    ISSUE: "Pengeluaran Barang",
    TRANSFER: "Transfer Antar Gudang",
    RETURN: "Retur Barang",
    ADJUSTMENT: "Koreksi Stok",
} as const;

function newItem(): OperationItem {
    return {
        product_id: "",
        quantity: 1,
        serial_text: "",
        party_type: ["ISSUE", "RETURN"].includes(props.operationType) ? "USER" : "",
        party_user_id: "",
        party_asset_id: "",
        party_location_id: "",
        specification_text: "",
        related_issue_item_id: "",
        reason: "",
    };
}

const form = useForm({
    department_id: props.departmentId ?? "",
    source_location_id: "",
    target_location_id: "",
    location_id: "",
    occurred_at: new Date().toISOString().slice(0, 10),
    direction: "OUT",
    remarks: "",
    items: [newItem()],
});

const isBusy = computed(() => form.processing);
const endpoint = computed(() => {
    const names = {
        ISSUE: "transaction.inventory.issues.store",
        TRANSFER: "transaction.inventory.transfers.store",
        RETURN: "transaction.inventory.returns.store",
        ADJUSTMENT: "transaction.inventory.adjustments.store",
    } as const;
    return route(names[props.operationType]);
});
const transactionsUrl = computed(() => route("transaction.inventory.transactions.index", {
    department_id: props.departmentId ?? undefined,
    type: props.operationType,
}));

function addItem(): void {
    form.items.push(newItem());
}

function removeItem(index: number): void {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
}

function parseSpecifications(value: string): Record<string, string> {
    return Object.fromEntries(
        value.split(/\r?\n/)
            .map((line) => line.split("="))
            .filter((parts) => parts.length >= 2)
            .map(([key, ...values]) => [key.trim(), values.join("=").trim()])
            .filter(([key, itemValue]) => key !== "" && itemValue !== ""),
    );
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        items: data.items.map((item) => ({
            product_id: item.product_id,
            quantity: item.quantity,
            serial_numbers: item.serial_text.split(/\r?\n/).map((serial) => serial.trim()).filter(Boolean),
            party_type: item.party_type || null,
            party_user_id: item.party_user_id || null,
            party_asset_id: item.party_asset_id || null,
            party_location_id: item.party_location_id || null,
            specification_changes: parseSpecifications(item.specification_text),
            related_issue_item_id: item.related_issue_item_id || null,
            reason: item.reason || null,
        })),
    })).post(endpoint.value);
}

function itemProduct(item: OperationItem): Option | undefined {
    return props.products.find((product) => product.id === item.product_id);
}
</script>

<template>
    <Head :title="titles[operationType]" />

    <div class="procurement-ui space-y-6">
        <InventoryPageHeader :title="titles[operationType]" section="Transaksi Stok" description="Perubahan stok dicatat sebagai pergerakan baru dan tidak mengubah catatan sebelumnya.">
            <template #actions>
                <Link :href="transactionsUrl" class="inline-flex min-h-10 items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Kembali ke Transaksi Stok</Link>
            </template>
        </InventoryPageHeader>

        <form class="space-y-5" @submit.prevent="submit">
            <div v-if="Object.keys(form.errors).length" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                Periksa kembali data transaksi. {{ Object.values(form.errors)[0] }}
            </div>
            <section class="grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-3">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Departemen
                    <p class="mt-1.5 flex h-10 items-center rounded-lg bg-gray-50 px-3 font-normal dark:bg-gray-800 dark:text-gray-200">{{ departments.find((department) => department.id === departmentId)?.code }} · {{ departments.find((department) => department.id === departmentId)?.name }}</p>
                </div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal transaksi
                    <input v-model="form.occurred_at" type="date" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                </label>

                <template v-if="operationType === 'ISSUE'">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gudang asal
                        <select v-model="form.source_location_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            <option value="" disabled>Pilih gudang</option>
                            <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.code }} · {{ warehouse.name }}</option>
                        </select>
                    </label>
                </template>
                <template v-else-if="operationType === 'TRANSFER'">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gudang asal
                        <select v-model="form.source_location_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            <option value="" disabled>Pilih gudang asal</option>
                            <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.code }} · {{ warehouse.name }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gudang tujuan
                        <select v-model="form.target_location_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            <option value="" disabled>Pilih gudang tujuan</option>
                            <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.code }} · {{ warehouse.name }}</option>
                        </select>
                    </label>
                </template>
                <template v-else-if="operationType === 'RETURN'">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gudang penerima retur
                        <select v-model="form.target_location_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            <option value="" disabled>Pilih gudang</option>
                            <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.code }} · {{ warehouse.name }}</option>
                        </select>
                    </label>
                </template>
                <template v-else>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gudang
                        <select v-model="form.location_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            <option value="" disabled>Pilih gudang</option>
                            <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.code }} · {{ warehouse.name }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Arah koreksi
                        <select v-model="form.direction" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            <option value="OUT">Kurangi stok</option>
                            <option value="IN">Tambah stok</option>
                        </select>
                    </label>
                </template>

                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-3">Catatan dokumen
                    <textarea v-model="form.remarks" rows="2" class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"></textarea>
                </label>
            </section>

            <section class="space-y-4">
                <article v-for="(item, index) in form.items" :key="index" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-gray-900 dark:text-white">Baris {{ index + 1 }}</h2>
                        <button v-if="form.items.length > 1" type="button" class="text-sm font-medium text-red-600" @click="removeItem(index)">Hapus baris</button>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">Produk consumable
                            <select v-model="item.product_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                <option value="" disabled>Pilih produk</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">{{ product.code }} · {{ product.name }} · {{ product.inventory_tracking_mode === 'SERIAL' ? 'Nomor seri' : 'Kuantitas' }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kuantitas
                            <input v-model="item.quantity" type="number" min="0.001" step="0.001" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                        </label>
                    </div>

                    <div v-if="itemProduct(item)?.inventory_tracking_mode === 'SERIAL'" class="space-y-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nomor seri, satu per baris</label>
                        <textarea v-model="item.serial_text" rows="3" required class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="SN-001&#10;SN-002"></textarea>
                    </div>

                    <template v-if="operationType === 'ISSUE'">
                        <div class="grid gap-4 md:grid-cols-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tujuan barang
                                <select v-model="item.party_type" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                    <option value="USER">Pengguna</option><option value="ASSET">Aset</option><option value="LOCATION">Lokasi</option>
                                </select>
                            </label>
                            <label v-if="item.party_type === 'USER'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pengguna
                                <select v-model="item.party_user_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"><option value="" disabled>Pilih pengguna</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} · {{ user.email }}</option></select>
                            </label>
                            <label v-if="item.party_type === 'ASSET'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Aset
                                <select v-model="item.party_asset_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"><option value="" disabled>Pilih aset</option><option v-for="asset in assets" :key="asset.id" :value="asset.id">{{ asset.number }}{{ asset.serial_number ? ` · ${asset.serial_number}` : '' }}</option></select>
                            </label>
                            <label v-if="item.party_type === 'LOCATION'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lokasi
                                <select v-model="item.party_location_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"><option value="" disabled>Pilih lokasi</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.code }} · {{ location.name }}</option></select>
                            </label>
                        </div>
                        <label v-if="item.party_type === 'ASSET'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Perubahan spesifikasi (opsional, format Nama=Nilai per baris)
                            <textarea v-model="item.specification_text" rows="2" class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="RAM=32GB&#10;Storage=1TB SSD"></textarea>
                        </label>
                    </template>

                    <div v-else-if="operationType === 'RETURN'" class="grid gap-4 md:grid-cols-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Barang dikembalikan oleh
                            <select v-model="item.party_type" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                <option value="USER">Pengguna</option><option value="ASSET">Aset</option><option value="LOCATION">Lokasi</option>
                            </select>
                        </label>
                        <label v-if="item.party_type === 'USER'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pengguna
                            <select v-model="item.party_user_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"><option value="" disabled>Pilih pengguna</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} · {{ user.email }}</option></select>
                        </label>
                        <label v-if="item.party_type === 'ASSET'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Aset
                            <select v-model="item.party_asset_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"><option value="" disabled>Pilih aset</option><option v-for="asset in assets" :key="asset.id" :value="asset.id">{{ asset.number }}</option></select>
                        </label>
                        <label v-if="item.party_type === 'LOCATION'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lokasi
                            <select v-model="item.party_location_id" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"><option value="" disabled>Pilih lokasi</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.code }} · {{ location.name }}</option></select>
                        </label>
                    </div>

                    <label v-if="operationType === 'ADJUSTMENT' || operationType === 'RETURN'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ operationType === 'RETURN' ? 'Keterangan barang kembali' : 'Alasan koreksi' }}
                        <input v-model="item.reason" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                    </label>
                </article>
                <p v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</p>
                <p v-if="form.errors.source_location_id || form.errors.target_location_id || form.errors.location_id" class="text-sm text-red-600">{{ form.errors.source_location_id || form.errors.target_location_id || form.errors.location_id }}</p>
                <button type="button" class="rounded-lg border border-violet-300 px-4 py-2 text-sm font-medium text-violet-700 hover:bg-violet-50 dark:border-violet-800 dark:text-violet-300" @click="addItem">+ Tambah baris</button>
            </section>

            <div class="flex justify-end gap-3">
                <Link :href="transactionsUrl" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">Batal</Link>
                <button type="submit" :disabled="isBusy || !departmentId" class="rounded-lg bg-violet-700 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ isBusy ? 'Menyimpan…' : 'Posting Dokumen' }}</button>
            </div>
        </form>
    </div>
</template>
