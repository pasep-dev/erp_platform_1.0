<script setup lang="ts">
import { computed, watch } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import Button from "primevue/button";
import InputNumber from "primevue/inputnumber";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Textarea from "primevue/textarea";
import AppLayout from "@/layout/AppLayout.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface DepartmentOption {
    id: string;
    code: string;
    name: string;
    company_id: string;
}

interface BranchOption { id: string; code: string; name: string; company_id: string }

interface ProductOption {
    id: string;
    code: string;
    name: string;
    inventory_tracking_mode: string;
    department_ids: string[];
}

interface RequestItemForm {
    product_id: string;
    requested_quantity: number;
    remarks: string;
}

const props = defineProps<{
    departments: DepartmentOption[];
    branches: BranchOption[];
    products: ProductOption[];
    defaultBranchId: string | null;
    defaultDepartmentId: string | null;
}>();

const productOptions = computed(() => props.products.filter((product) => product.department_ids.includes(form.department_id)).map((product) => ({
    label: `${product.code} · ${product.name}${product.inventory_tracking_mode === "SERIAL" ? " · Serial" : ""}`,
    value: product.id,
})));
const branchOptions = computed(() => props.branches.map((branch) => ({ label: `${branch.code} · ${branch.name}`, value: branch.id })));
const departmentOptions = computed(() => props.departments.filter((department) => !form.branch_id || props.branches.find((branch) => branch.id === form.branch_id)?.company_id === department.company_id).map((department) => ({ label: `${department.code} · ${department.name}`, value: department.id })));

function newItem(): RequestItemForm {
    return { product_id: "", requested_quantity: 1, remarks: "" };
}

const form = useForm({
    branch_id: props.defaultBranchId ?? "",
    department_id: props.defaultDepartmentId ?? "",
    requested_at: new Date().toISOString().slice(0, 10),
    required_at: "",
    remarks: "",
    items: [newItem()],
});

watch(() => form.branch_id, () => {
    if (!departmentOptions.value.some((department) => department.value === form.department_id)) {
        form.department_id = "";
    }
    const availableProducts = new Set(productOptions.value.map((product) => product.value));
    form.items = form.items.map((item) => availableProducts.has(item.product_id) ? item : { ...item, product_id: "" });
});

watch(() => form.department_id, (departmentId) => {
    const availableProducts = new Set(props.products.filter((product) => product.department_ids.includes(departmentId)).map((product) => product.id));
    form.items = form.items.map((item) => availableProducts.has(item.product_id) ? item : { ...item, product_id: "" });
});

function addItem(): void {
    form.items.push(newItem());
}

function removeItem(index: number): void {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
}

function itemError(index: number, field: "product_id" | "requested_quantity"): string | undefined {
    return form.errors[`items.${index}.${field}` as keyof typeof form.errors];
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        required_at: data.required_at || null,
        items: data.items.map((item) => ({
            product_id: item.product_id,
            requested_quantity: item.requested_quantity,
            remarks: item.remarks || null,
        })),
    })).post(route("transaction.inventory.requests.store"));
}
</script>

<template>
    <Head title="Buat Permintaan Barang" />

    <div class="procurement-ui space-y-6">
        <InventoryPageHeader title="Buat Permintaan Barang" section="Permintaan Barang" description="Permintaan akan masuk ke atasan sesuai approval workflow departemen.">
            <template #actions>
                <Link :href="route('transaction.inventory.requests.index')" class="inline-flex min-h-10 items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Kembali</Link>
            </template>
        </InventoryPageHeader>

        <form class="space-y-5" @submit.prevent="submit">
            <div v-if="Object.keys(form.errors).length" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                Periksa kembali form. {{ Object.values(form.errors)[0] }}
            </div>

            <section class="grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-3">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Branch
                    <Select v-model="form.branch_id" :options="branchOptions" option-label="label" option-value="value" filter fluid class="mt-1.5" placeholder="Pilih branch" />
                    <small v-if="form.errors.branch_id" class="mt-1 block text-red-600">{{ form.errors.branch_id }}</small>
                </label>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Departemen
                    <Select v-model="form.department_id" :options="departmentOptions" option-label="label" option-value="value" filter fluid class="mt-1.5" placeholder="Pilih departemen" />
                    <small v-if="form.errors.department_id" class="mt-1 block text-red-600">{{ form.errors.department_id }}</small>
                </label>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal permintaan
                    <InputText v-model="form.requested_at" type="date" required fluid class="mt-1.5" />
                    <small v-if="form.errors.requested_at" class="mt-1 block text-red-600">{{ form.errors.requested_at }}</small>
                </label>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Dibutuhkan tanggal
                    <InputText v-model="form.required_at" type="date" fluid class="mt-1.5" />
                    <small v-if="form.errors.required_at" class="mt-1 block text-red-600">{{ form.errors.required_at }}</small>
                </label>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-3">Keterangan
                    <Textarea v-model="form.remarks" rows="3" fluid class="mt-1.5" placeholder="Alasan atau kebutuhan barang" />
                    <small v-if="form.errors.remarks" class="mt-1 block text-red-600">{{ form.errors.remarks }}</small>
                </label>
            </section>

            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><h2 class="font-semibold text-gray-900 dark:text-white">Daftar Barang</h2><p class="mt-1 text-sm text-gray-500">Produk consumable berseri akan diminta berdasarkan jumlah unit; nomor seri dipilih petugas saat pengeluaran.</p></div>
                    <Button type="button" label="Tambah Item" severity="secondary" outlined @click="addItem" />
                </div>

                <div v-for="(item, index) in form.items" :key="index" class="grid gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700 md:grid-cols-[minmax(0,2fr)_1fr_minmax(0,2fr)_auto] md:items-end">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Produk
                        <Select v-model="item.product_id" :options="productOptions" option-label="label" option-value="value" filter fluid class="mt-1.5" placeholder="Cari produk consumable" />
                        <small v-if="itemError(index, 'product_id')" class="mt-1 block text-red-600">{{ itemError(index, 'product_id') }}</small>
                    </label>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Kuantitas
                        <InputNumber v-model="item.requested_quantity" :min="0.001" :min-fraction-digits="0" :max-fraction-digits="3" fluid class="mt-1.5" />
                        <small v-if="itemError(index, 'requested_quantity')" class="mt-1 block text-red-600">{{ itemError(index, 'requested_quantity') }}</small>
                    </label>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Catatan item
                        <InputText v-model="item.remarks" fluid class="mt-1.5" placeholder="Opsional" />
                    </label>
                    <Button type="button" label="Hapus" severity="danger" text :disabled="form.items.length === 1" @click="removeItem(index)" />
                </div>
                <p v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</p>
            </section>

            <div class="flex justify-end gap-2">
                <Link :href="route('transaction.inventory.requests.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">Batal</Link>
                <Button type="submit" label="Simpan Draft" :loading="form.processing" />
            </div>
        </form>
    </div>
</template>
