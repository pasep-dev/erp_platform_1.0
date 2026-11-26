<script setup lang="ts">
import { computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Select from 'primevue/select'

defineOptions({ layout: AppLayout })

interface BranchOption {
    id: string
    company_id: string
    code: string
    name: string
    warehouse_location_id: string | null
    warehouse_code: string | null
    warehouse_name: string | null
    warehouse_count: number
}
interface AssetOption {
    id: string; number: string; serial_number: string | null; company_id: string; department_id: string; branch_id: string | null; location_id: string
    product: { code: string; name: string }; company: { code: string; name: string }; department: { code: string; name: string }
    branch: { code: string; name: string } | null; location: { code: string; name: string }
}

const props = defineProps<{
    initial_asset: AssetOption | null
    assets: AssetOption[]
    branches: BranchOption[]
}>()

const today = new Date()
today.setMinutes(today.getMinutes() - today.getTimezoneOffset())
const form = useForm({
    asset_id: props.initial_asset?.id ?? '',
    branch_id: '',
    effective_at: today.toISOString().slice(0, 10),
    reason: '',
})

const selectedAsset = computed(() => props.assets.find((asset) => asset.id === form.asset_id) ?? null)
const destinationBranches = computed(() => props.branches.filter((branch) => branch.company_id === selectedAsset.value?.company_id && branch.id !== selectedAsset.value?.branch_id))
const destinationBranch = computed(() => destinationBranches.value.find((branch) => branch.id === form.branch_id) ?? null)

watch(() => form.asset_id, () => {
    form.branch_id = ''
})

function submit(): void {
    form.post(route('transaction.asset-management.transfers.store'), { preserveScroll: true })
}

function cancel(): void {
    if (props.initial_asset) {
        router.get(route('transaction.asset-management.assignments.show', props.initial_asset.id))
        return
    }
    router.get(route('transaction.asset-management.transfers.index'))
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader title="Transfer Aset Antar Branch" section="Transfer" description="Pilih branch tujuan. Aset otomatis dipindahkan ke warehouse branch tersebut dan tersedia kembali." />
        <form class="space-y-5" @submit.prevent="submit">
            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-900 dark:text-white">Aset</h2>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Pilih aset <span class="text-red-600">*</span><Select v-model="form.asset_id" :options="assets.map((asset) => ({ label: `${asset.number} · ${asset.product.name} · ${asset.company.name}`, value: asset.id }))" option-label="label" option-value="value" filter :disabled="!!initial_asset" placeholder="Cari nomor aset atau nama produk" fluid /><small v-if="form.errors.asset_id" class="font-normal text-red-600">{{ form.errors.asset_id }}</small></label>
                <div v-if="selectedAsset" class="grid gap-3 rounded-lg bg-purple-50 p-3 text-sm text-purple-950 dark:bg-purple-950/40 dark:text-purple-200 sm:grid-cols-3"><div><span class="block text-xs opacity-70">Aset</span><strong>{{ selectedAsset.number }}</strong><div>{{ selectedAsset.product.name }} · SN {{ selectedAsset.serial_number ?? '-' }}</div></div><div><span class="block text-xs opacity-70">Penempatan saat ini</span><strong>{{ selectedAsset.company.name }}</strong><div>{{ selectedAsset.department.name }} · {{ selectedAsset.location.name }}</div></div><div><span class="block text-xs opacity-70">Branch saat ini</span><strong>{{ selectedAsset.branch?.code ?? '-' }} · {{ selectedAsset.branch?.name ?? 'Tidak terkait branch' }}</strong></div></div>
            </section>

            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div><h2 class="font-semibold text-gray-900 dark:text-white">Branch tujuan</h2><p class="mt-1 text-xs text-gray-500">Perusahaan dan departemen aset tetap. Lokasi otomatis mengikuti warehouse aktif di branch tujuan.</p></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Pilih branch tujuan <span class="text-red-600">*</span><Select v-model="form.branch_id" :options="destinationBranches.map((branch) => ({ label: `${branch.code} · ${branch.name}${branch.warehouse_count === 1 ? '' : branch.warehouse_count === 0 ? ' — warehouse belum disiapkan' : ' — lebih dari satu warehouse aktif'}`, value: branch.id, disabled: branch.warehouse_count !== 1 }))" option-label="label" option-value="value" option-disabled="disabled" :disabled="!selectedAsset" placeholder="Pilih branch tujuan" fluid /><small v-if="form.errors.branch_id" class="font-normal text-red-600">{{ form.errors.branch_id }}</small></label>
                    <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800"><span class="block text-xs text-gray-500">Warehouse otomatis</span><strong class="mt-1 block text-gray-800 dark:text-gray-100">{{ destinationBranch?.warehouse_location_id ? `${destinationBranch.warehouse_code} · ${destinationBranch.warehouse_name}` : 'Pilih branch dengan tepat satu warehouse aktif' }}</strong></div>
                </div>
            </section>

            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-900 dark:text-white">Tanggal dan alasan</h2>
                <label class="grid max-w-sm gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal efektif <span class="text-red-600">*</span><input v-model="form.effective_at" type="date" required :max="today.toISOString().slice(0, 10)" class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="form.errors.effective_at" class="font-normal text-red-600">{{ form.errors.effective_at }}</small></label>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Alasan perpindahan <span class="text-red-600">*</span><textarea v-model="form.reason" rows="4" minlength="5" maxlength="5000" required class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Contoh: pemindahan unit kerja, perubahan kantor, atau relokasi ruang..." /><small v-if="form.errors.reason" class="font-normal text-red-600">{{ form.errors.reason }}</small></label>
                <p class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">Transfer otomatis melepas penanggung jawab aktif dan mengembalikan aset ke status tersedia di warehouse branch baru.</p>
            </section>
            <div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" outlined type="button" @click="cancel" /><Button label="Simpan Perpindahan" icon="pi pi-check" type="submit" :loading="form.processing" /></div>
        </form>
    </div>
</template>
