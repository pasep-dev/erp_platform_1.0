<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'

interface ReceiptSource {
    source_type: 'PURCHASE_ORDER' | 'PURCHASE_REQUEST'
    source_id: string
    source_number: string
    purchase_request_number?: string | null
    vendor_id?: string | null
    vendor?: string | null
    company?: string | null
    department?: string | null
    status: string
}

interface ReceiptLine {
    source_item_id: string
    item_type: 'PRODUCT' | 'SERVICE'
    item_name: string
    item_code?: string | null
    description?: string | null
    quantity_ordered: number
    quantity_previously_accepted: number
    remaining_quantity: number
    uom?: string | null
    uom_code?: string | null
    quantity_received?: number | string | null
    quantity_accepted?: number | string | null
    quantity_rejected?: number | string | null
    remarks?: string | null
}

interface ExistingGoodsReceipt {
    id: string
    received_at: string
    delivery_note_number?: string | null
    remarks?: string | null
    warehouse_location_id?: string | null
    storage_location_id?: string | null
    warehouse_location?: { branch_id?: string | null } | null
}

interface LocationOption {
    id: string
    code: string
    name: string
    branch_id?: string | null
    parent_id?: string | null
    location_type?: string | null
}

const props = defineProps<{
    source: ReceiptSource
    items: ReceiptLine[]
    branches: LocationOption[]
    warehouses: LocationOption[]
    storageLocations: LocationOption[]
    goodsReceipt?: ExistingGoodsReceipt | null
}>()

const selectedBranchId = ref(props.goodsReceipt?.warehouse_location?.branch_id ?? '')

const form = useForm({
    source_type: props.source.source_type,
    source_id: props.source.source_id,
    vendor_id: props.source.vendor_id ?? null,
    received_at: props.goodsReceipt?.received_at?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
    delivery_note_number: props.goodsReceipt?.delivery_note_number ?? '',
    remarks: props.goodsReceipt?.remarks ?? '',
    warehouse_location_id: props.goodsReceipt?.warehouse_location_id ?? '',
    storage_location_id: props.goodsReceipt?.storage_location_id ?? '',
    items: props.items.map((item) => ({ ...item })),
})

const isEditing = computed(() => Boolean(props.goodsReceipt))
const availableWarehouses = computed(() =>
    props.warehouses.filter((warehouse) => !selectedBranchId.value || warehouse.branch_id === selectedBranchId.value),
)
const availableStorageLocations = computed(() =>
    props.storageLocations.filter((location) => location.parent_id === form.warehouse_location_id),
)

watch(selectedBranchId, (branchId) => {
    const selectedWarehouse = props.warehouses.find((warehouse) => warehouse.id === form.warehouse_location_id)
    if (!branchId || selectedWarehouse?.branch_id !== branchId) {
        form.warehouse_location_id = ''
        form.storage_location_id = ''
    }
})

watch(() => form.warehouse_location_id, (warehouseId) => {
    const selectedWarehouse = props.warehouses.find((warehouse) => warehouse.id === warehouseId)
    if (selectedWarehouse?.branch_id) {
        selectedBranchId.value = selectedWarehouse.branch_id
    }

    if (!props.storageLocations.some((location) => location.id === form.storage_location_id && location.parent_id === warehouseId)) {
        form.storage_location_id = ''
    }
})

function updateReceivedQuantity(index: number, value: number | null) {
    const item = form.items[index]
    item.quantity_received = value
    item.quantity_accepted = Math.max(0, Number(value ?? 0) - Number(item.quantity_rejected ?? 0))
}

function updateRejectedQuantity(index: number, value: number | null) {
    const item = form.items[index]
    item.quantity_rejected = value
    item.quantity_accepted = Math.max(0, Number(item.quantity_received ?? 0) - Number(value ?? 0))
}

function submittedItemIndex(index: number): number {
    return form.items
        .slice(0, index + 1)
        .filter((item) => Number(item.quantity_received ?? 0) > 0)
        .length - 1
}

function itemError(index: number, field: string): string | undefined {
    const submittedIndex = submittedItemIndex(index)
    if (submittedIndex < 0) {
        return undefined
    }

    return form.errors[`items.${submittedIndex}.${field}`]
}

function save() {
    const payloadItems = form.items
        .filter((item) => Number(item.quantity_received ?? 0) > 0)
        .map((item) => ({
            source_item_id: item.source_item_id,
            quantity_received: item.quantity_received,
            quantity_accepted: item.quantity_accepted,
            quantity_rejected: item.quantity_rejected ?? 0,
            remarks: item.remarks,
        }))

    form.transform((data) => ({
        ...data,
        items: payloadItems,
    }))

    if (props.goodsReceipt) {
        form.put(route('transaction.procurement.goods-receipts.update', props.goodsReceipt.id), { preserveScroll: true })
        return
    }

    form.post(route('transaction.procurement.goods-receipts.store'), { preserveScroll: true })
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="save">
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="mb-5">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Sumber Penerimaan</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Barang atau jasa diterima dari satu dokumen sumber dan vendor.</p>
            </div>
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="text-xs font-medium uppercase text-gray-500">Jenis Sumber</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ source.source_type === 'PURCHASE_ORDER' ? 'Purchase Order' : 'Purchase Request tanpa PO' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase text-gray-500">Nomor Sumber</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ source.source_number }}</dd></div>
                <div><dt class="text-xs font-medium uppercase text-gray-500">Vendor</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ source.vendor ?? 'Tidak ditentukan di PR' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase text-gray-500">Perusahaan</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ source.company ?? '-' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase text-gray-500">Departemen</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ source.department ?? '-' }}</dd></div>
                <div v-if="source.purchase_request_number"><dt class="text-xs font-medium uppercase text-gray-500">PR Sumber</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ source.purchase_request_number }}</dd></div>
            </dl>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="mb-5">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Informasi Penerimaan</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Isi tanggal dan referensi surat jalan atau bukti pekerjaan vendor.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="receipt-branch" class="mb-1 block text-sm font-medium">Cabang gudang <span class="text-gray-400">(opsional)</span></label>
                    <select id="receipt-branch" v-model="selectedBranchId" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                        <option value="">Semua cabang</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.code }} · {{ branch.name }}</option>
                    </select>
                </div>
                <div>
                    <label for="receipt-warehouse" class="mb-1 block text-sm font-medium">Gudang tujuan <span class="text-gray-400">(opsional)</span></label>
                    <select id="receipt-warehouse" v-model="form.warehouse_location_id" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                        <option value="">Belum ditentukan</option>
                        <option v-for="warehouse in availableWarehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.code }} · {{ warehouse.name }}</option>
                    </select>
                    <small class="text-gray-500 dark:text-gray-400">Pilih gudang agar stok consumable tercatat otomatis. Jika belum diketahui, penerimaan dapat diposting dan dipetakan ke gudang kemudian.</small>
                    <small v-if="form.errors.warehouse_location_id" class="text-red-600">{{ form.errors.warehouse_location_id }}</small>
                </div>
                <div>
                    <label for="receipt-storage-location" class="mb-1 block text-sm font-medium">Sublokasi <span class="text-gray-400">(opsional)</span></label>
                    <select id="receipt-storage-location" v-model="form.storage_location_id" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" :disabled="!form.warehouse_location_id">
                        <option value="">Simpan di gudang</option>
                        <option v-for="location in availableStorageLocations" :key="location.id" :value="location.id">{{ location.code }} · {{ location.name }}</option>
                    </select>
                    <small v-if="form.errors.storage_location_id" class="text-red-600">{{ form.errors.storage_location_id }}</small>
                </div>
                <div>
                    <label for="received-at" class="mb-1 block text-sm font-medium">Tanggal Diterima <span class="text-red-600">*</span></label>
                    <InputText id="received-at" v-model="form.received_at" type="date" class="w-full" />
                    <small v-if="form.errors.received_at" class="text-red-600">{{ form.errors.received_at }}</small>
                </div>
                <div>
                    <label for="delivery-note" class="mb-1 block text-sm font-medium">Nomor Surat Jalan / Bukti Pekerjaan</label>
                    <InputText id="delivery-note" v-model="form.delivery_note_number" class="w-full" maxlength="100" placeholder="Opsional" />
                    <small v-if="form.errors.delivery_note_number" class="text-red-600">{{ form.errors.delivery_note_number }}</small>
                </div>
                <div class="sm:col-span-2">
                    <label for="receipt-remarks" class="mb-1 block text-sm font-medium">Catatan Umum</label>
                    <Textarea id="receipt-remarks" v-model="form.remarks" rows="3" auto-resize class="w-full" placeholder="Catatan penerimaan" />
                    <small v-if="form.errors.remarks" class="text-red-600">{{ form.errors.remarks }}</small>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Item yang Diterima</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Masukkan kuantitas yang benar-benar tiba. Item yang tidak diisi tidak akan masuk ke GR ini.</p>
            </div>
            <div class="space-y-4 p-4 sm:p-6">
                <article v-for="(item, index) in form.items" :key="item.source_item_id" class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ item.item_name }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ item.item_code ?? item.item_type }}<span v-if="item.description"> · {{ item.description }}</span></p>
                        </div>
                        <div class="text-right text-sm text-gray-600 dark:text-gray-300">
                            <div>Ordered: <strong>{{ item.quantity_ordered }} {{ item.uom_code }}</strong></div>
                            <div v-if="item.quantity_previously_accepted > 0">Sebelumnya diterima: {{ item.quantity_previously_accepted }}</div>
                            <div>Sisa terbuka: <strong>{{ item.remaining_quantity }} {{ item.uom_code }}</strong></div>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label :for="`received-${index}`" class="mb-1 block text-xs font-medium">Kuantitas Diterima</label>
                            <InputNumber :input-id="`received-${index}`" :model-value="item.quantity_received" :min="0" :max="item.remaining_quantity" :max-fraction-digits="3" fluid @update:model-value="updateReceivedQuantity(index, $event)" />
                            <small v-if="itemError(index, 'quantity_received')" class="text-red-600">{{ itemError(index, 'quantity_received') }}</small>
                        </div>
                        <div>
                            <label :for="`accepted-${index}`" class="mb-1 block text-xs font-medium">Diterima Baik</label>
                            <InputNumber :input-id="`accepted-${index}`" v-model="form.items[index].quantity_accepted" :min="0" :max="Number(item.quantity_received ?? 0)" :max-fraction-digits="3" fluid />
                            <small v-if="itemError(index, 'quantity_accepted')" class="text-red-600">{{ itemError(index, 'quantity_accepted') }}</small>
                        </div>
                        <div>
                            <label :for="`rejected-${index}`" class="mb-1 block text-xs font-medium">Ditolak / Perlu Penggantian</label>
                            <InputNumber :input-id="`rejected-${index}`" :model-value="item.quantity_rejected" :min="0" :max="Number(item.quantity_received ?? 0)" :max-fraction-digits="3" fluid @update:model-value="updateRejectedQuantity(index, $event)" />
                            <small v-if="itemError(index, 'quantity_rejected')" class="text-red-600">{{ itemError(index, 'quantity_rejected') }}</small>
                        </div>
                    </div>
                    <div v-if="Number(item.quantity_rejected ?? 0) > 0" class="mt-3">
                        <label :for="`item-remarks-${index}`" class="mb-1 block text-xs font-medium">Alasan Penolakan <span class="text-red-600">*</span></label>
                        <Textarea :id="`item-remarks-${index}`" v-model="form.items[index].remarks" rows="2" auto-resize class="w-full" placeholder="Jelaskan kondisi item atau pekerjaan yang ditolak" />
                        <small v-if="itemError(index, 'remarks')" class="text-red-600">{{ itemError(index, 'remarks') }}</small>
                    </div>
                </article>
            </div>
            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                <Button label="Kembali" severity="secondary" outlined type="button" @click="router.get(route('transaction.procurement.goods-receipts.index'))" />
                <Button :label="isEditing ? 'Simpan Draft' : 'Buat Draft GR'" icon="pi pi-save" type="submit" :loading="form.processing" />
            </div>
        </section>
        <p v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</p>
    </form>
</template>
