<script setup lang="ts">
import { computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

interface AssetOption {
    id: string
    number: string
    condition: string
    product: { code: string; name: string }
}
interface VendorOption { id: string; code: string; name: string; phone: string | null; email: string | null }
interface MaintenanceComponent {
    id?: string
    component_name: string
    quantity: string | number
    estimated_unit_cost: string | number
    needed_at: string
}
interface MaintenanceRecord {
    id: string
    number: string
    asset_id: string
    maintenance_type: string
    execution_mode: string
    priority: string
    requested_at: string
    issue_description: string
    reported_condition: string
    inspection_result: string | null
    vendor_id: string | null
    estimated_cost: string | null
    scheduled_at: string | null
    components: MaintenanceComponent[]
}

const props = defineProps<{
    maintenance: MaintenanceRecord | null
    assets: AssetOption[]
    vendors: VendorOption[]
    initial_asset_id: string | null
    type_options: Record<string, string>
    priority_options: Record<string, string>
    condition_options: Record<string, string>
}>()

const today = new Date()
today.setMinutes(today.getMinutes() - today.getTimezoneOffset())
const isEdit = computed(() => props.maintenance !== null)
const form = useForm({
    asset_id: props.maintenance?.asset_id ?? props.initial_asset_id ?? '',
    maintenance_type: props.maintenance?.maintenance_type ?? 'CORRECTIVE',
    execution_mode: props.maintenance?.execution_mode ?? 'INTERNAL',
    priority: props.maintenance?.priority ?? 'NORMAL',
    requested_at: props.maintenance?.requested_at?.slice(0, 10) ?? today.toISOString().slice(0, 10),
    issue_description: props.maintenance?.issue_description ?? '',
    reported_condition: props.maintenance?.reported_condition ?? 'GOOD',
    inspection_result: props.maintenance?.inspection_result ?? '',
    vendor_id: props.maintenance?.vendor_id ?? '',
    estimated_cost: props.maintenance?.estimated_cost ?? '0',
    scheduled_at: props.maintenance?.scheduled_at?.slice(0, 10) ?? '',
    components: props.maintenance?.components?.map((component) => ({
        component_name: component.component_name,
        quantity: component.quantity,
        estimated_unit_cost: component.estimated_unit_cost,
        needed_at: component.needed_at?.slice(0, 10) ?? '',
    })) ?? [],
})

const componentEstimatedTotal = computed(() => form.components.reduce((total, component) => (
    total + Number(component.quantity || 0) * Number(component.estimated_unit_cost || 0)
), 0))

watch(() => form.execution_mode, (value) => {
    if (value === 'INTERNAL') {
        form.vendor_id = ''
        form.estimated_cost = form.components.length > 0 ? componentEstimatedTotal.value.toFixed(2) : '0'
    } else {
        form.components = []
    }
})

watch(() => form.components, () => {
    if (form.execution_mode === 'INTERNAL') {
        form.estimated_cost = componentEstimatedTotal.value.toFixed(2)
    }
}, { deep: true })

const selectedAsset = computed(() => props.assets.find((asset) => asset.id === form.asset_id) ?? null)
const assetOptions = computed(() => props.assets.map((asset) => ({
    label: `${asset.number} - ${asset.product.code} - ${asset.product.name}`,
    value: asset.id,
})))
const validationMessages = computed(() => Object.values(form.errors).filter((message): message is string => Boolean(message)))

function submit(): void {
    if (form.execution_mode === 'INTERNAL' && form.components.length > 0) {
        form.estimated_cost = componentEstimatedTotal.value.toFixed(2)
    }

    if (props.maintenance) {
        form.put(route('transaction.asset-management.maintenance.update', props.maintenance.id), { preserveScroll: true })
        return
    }

    form.post(route('transaction.asset-management.maintenance.store'), { preserveScroll: true })
}

function addComponent(): void {
    form.components.push({ component_name: '', quantity: 1, estimated_unit_cost: 0, needed_at: form.scheduled_at || today.toISOString().slice(0, 10) })
}

function removeComponent(index: number): void {
    form.components.splice(index, 1)
}

function componentError(index: number, field: string): string | undefined {
    return (form.errors as Record<string, string>)[`components.${index}.${field}`]
}

function cancel(): void {
    if (props.maintenance) {
        router.get(route('transaction.asset-management.maintenance.show', props.maintenance.id))
        return
    }

    router.get(route('transaction.asset-management.maintenance.index'))
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader :title="isEdit ? `Perbarui ${maintenance?.number}` : 'Request Maintenance'" section="Maintenance" description="Catat keluhan, hasil pemeriksaan, rencana perbaikan, dan estimasi sebelum mengajukan permintaan." />

        <section v-if="validationMessages.length" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <h2 class="font-semibold">Permintaan belum bisa disimpan. Periksa bagian berikut:</h2>
            <ul class="mt-2 list-inside list-disc space-y-1"><li v-for="(message, index) in validationMessages" :key="`${index}-${message}`">{{ message }}</li></ul>
        </section>

        <form class="space-y-5" @submit.prevent="submit">
            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div><h2 class="font-semibold text-gray-900 dark:text-white">Informasi Permintaan</h2><p class="mt-1 text-xs text-gray-500">Pilih aset, pihak yang mengerjakan, dan keluhan awal.</p></div>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Aset <span class="text-red-600">*</span>
                    <Select
                        v-model="form.asset_id"
                        :options="assetOptions"
                        option-label="label"
                        option-value="value"
                        filter
                        :disabled="isEdit"
                        placeholder="Cari nomor aset, kode, atau nama"
                        empty-message="Belum ada aset aktif."
                        empty-filter-message="Aset tidak ditemukan."
                        fluid
                    />
                    <small v-if="form.errors.asset_id" class="font-normal text-red-600">{{ form.errors.asset_id }}</small>
                </label>
                <div v-if="selectedAsset" class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-purple-50 px-4 py-3 text-sm dark:bg-purple-950/30"><div><p class="font-semibold text-purple-900 dark:text-purple-100">{{ selectedAsset.number }} · {{ selectedAsset.product.name }}</p><p class="text-xs text-purple-800 dark:text-purple-200">Kondisi aset saat ini: {{ condition_options[selectedAsset.condition] ?? selectedAsset.condition }}</p></div><Tag :value="condition_options[selectedAsset.condition] ?? selectedAsset.condition" severity="info" /></div>
                <label class="grid max-w-md gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Pelaksana <span class="text-red-600">*</span><Select v-model="form.execution_mode" :options="[{ label: 'Tim internal IT / GA', value: 'INTERNAL' }, { label: 'Vendor eksternal', value: 'VENDOR' }]" option-label="label" option-value="value" required fluid /><small v-if="form.errors.execution_mode" class="font-normal text-red-600">{{ form.errors.execution_mode }}</small></label>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Jenis maintenance <span class="text-red-600">*</span><select v-model="form.maintenance_type" required class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"><option v-for="(label, value) in type_options" :key="value" :value="value">{{ label }}</option></select><small v-if="form.errors.maintenance_type" class="font-normal text-red-600">{{ form.errors.maintenance_type }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Prioritas <span class="text-red-600">*</span><select v-model="form.priority" required class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"><option v-for="(label, value) in priority_options" :key="value" :value="value">{{ label }}</option></select><small v-if="form.errors.priority" class="font-normal text-red-600">{{ form.errors.priority }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal permintaan <span class="text-red-600">*</span><input v-model="form.requested_at" type="date" required class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="form.errors.requested_at" class="font-normal text-red-600">{{ form.errors.requested_at }}</small></label>
                </div>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Keluhan / alasan service <span class="text-red-600">*</span><textarea v-model="form.issue_description" rows="3" maxlength="5000" required class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Contoh: AC ruang server tidak dingin dan mengeluarkan suara..." /><small v-if="form.errors.issue_description" class="font-normal text-red-600">{{ form.errors.issue_description }}</small></label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Kondisi aset saat diperiksa <span class="text-red-600">*</span><select v-model="form.reported_condition" required class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"><option v-for="(label, value) in condition_options" :key="value" :value="value">{{ label }}</option></select><small v-if="form.errors.reported_condition" class="font-normal text-red-600">{{ form.errors.reported_condition }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Tanggapan / rencana perbaikan<textarea v-model="form.inspection_result" rows="3" maxlength="5000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Catat hasil pemeriksaan, tindakan yang diusulkan, atau alasan service..." /><small v-if="form.errors.inspection_result" class="font-normal text-red-600">{{ form.errors.inspection_result }}</small><small class="font-normal text-gray-500">Wajib diisi sebelum permintaan diajukan.</small></label>
                </div>
            </section>

            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div><h2 class="font-semibold text-gray-900 dark:text-white">{{ form.execution_mode === 'INTERNAL' ? 'Komponen dan Estimasi' : 'Vendor dan Estimasi' }}</h2><p class="mt-1 text-xs text-gray-500">{{ form.execution_mode === 'INTERNAL' ? 'Rincikan komponen yang perlu dibeli. Total estimasi dihitung otomatis dari jumlah dan harga setiap komponen.' : 'Estimasi biaya vendor menjadi dasar pengajuan persetujuan.' }}</p></div>
                <div v-if="form.execution_mode === 'INTERNAL'" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-gray-600 dark:text-gray-300">Tambahkan komponen yang diperlukan, misalnya RAM atau SSD. Daftar dapat dikosongkan jika tidak ada komponen yang perlu dibeli.</p>
                        <Button label="Tambah Komponen" icon="pi pi-plus" severity="secondary" outlined type="button" @click="addComponent" />
                    </div>
                    <div v-if="form.components.length" class="space-y-3">
                        <article v-for="(component, index) in form.components" :key="index" class="grid gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.4fr)_0.65fr_1fr_1fr_auto]">
                            <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Nama komponen <span class="text-red-600">*</span><input v-model="component.component_name" type="text" maxlength="150" required class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Contoh: RAM 16 GB" /><small v-if="componentError(index, 'component_name')" class="font-normal text-red-600">{{ componentError(index, 'component_name') }}</small></label>
                            <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Jumlah <span class="text-red-600">*</span><input v-model="component.quantity" type="number" min="0.001" step="0.001" required class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="componentError(index, 'quantity')" class="font-normal text-red-600">{{ componentError(index, 'quantity') }}</small></label>
                            <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Estimasi harga satuan (Rp) <span class="text-red-600">*</span><input v-model="component.estimated_unit_cost" type="number" min="0" step="0.01" required class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="componentError(index, 'estimated_unit_cost')" class="font-normal text-red-600">{{ componentError(index, 'estimated_unit_cost') }}</small></label>
                            <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal dibutuhkan <span class="text-red-600">*</span><input v-model="component.needed_at" type="date" required class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="componentError(index, 'needed_at')" class="font-normal text-red-600">{{ componentError(index, 'needed_at') }}</small></label>
                            <div class="flex items-end justify-end"><Button icon="pi pi-trash" severity="danger" text rounded type="button" aria-label="Hapus komponen" @click="removeComponent(index)" /></div>
                            <p class="text-xs text-gray-500 sm:col-span-2 lg:col-span-5">Subtotal: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(component.quantity || 0) * Number(component.estimated_unit_cost || 0)) }}</p>
                        </article>
                    </div>
                    <p v-else class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:bg-gray-800">Belum ada komponen yang ditambahkan.</p>
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-purple-50 px-4 py-3 dark:bg-purple-950/30"><span class="text-sm font-medium text-purple-900 dark:text-purple-100">Total estimasi komponen</span><strong class="text-base text-purple-950 dark:text-purple-100">{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(componentEstimatedTotal) }}</strong></div>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label v-if="form.execution_mode === 'VENDOR'" class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Vendor <span class="text-red-600">*</span><Select v-model="form.vendor_id" :options="vendors.map((vendor) => ({ label: `${vendor.name} · ${vendor.code}`, value: vendor.id }))" option-label="label" option-value="value" filter placeholder="Cari atau pilih vendor" fluid /><small v-if="form.errors.vendor_id" class="font-normal text-red-600">{{ form.errors.vendor_id }}</small></label>
                    <label v-if="form.execution_mode === 'VENDOR'" class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Estimasi biaya (Rp)<span v-if="isEdit" class="text-red-600">*</span><input v-model="form.estimated_cost" type="number" min="0" step="0.01" :required="isEdit" class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="0" /><small class="font-normal text-gray-500">{{ isEdit ? 'Wajib diisi sebelum permintaan diajukan.' : 'Dapat dilengkapi setelah pemeriksaan.' }}</small><small v-if="form.errors.estimated_cost" class="font-normal text-red-600">{{ form.errors.estimated_cost }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Jadwal service<input v-model="form.scheduled_at" type="date" class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="form.errors.scheduled_at" class="font-normal text-red-600">{{ form.errors.scheduled_at }}</small></label>
                </div>
            </section>

            <div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" outlined type="button" @click="cancel" /><Button :label="isEdit ? 'Simpan Perubahan' : 'Buat Permintaan'" icon="pi pi-check" type="submit" :loading="form.processing" :disabled="!form.asset_id" /></div>
        </form>
    </div>
</template>
