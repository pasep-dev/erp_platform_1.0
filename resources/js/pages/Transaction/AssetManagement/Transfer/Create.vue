<script setup lang="ts">
import { computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Select from 'primevue/select'

defineOptions({ layout: AppLayout })

interface Organization { id: string; company_id?: string; branch_id?: string | null; code: string; name: string; branch?: { code: string; name: string } | null }
interface AssetOption {
    id: string; number: string; serial_number: string | null; company_id: string; department_id: string; location_id: string
    product: { code: string; name: string }; company: Organization; department: Organization; location: Organization
}

const props = defineProps<{
    initial_asset: AssetOption | null
    assets: AssetOption[]
    companies: Organization[]
    departments: Organization[]
    locations: Organization[]
}>()

const today = new Date()
today.setMinutes(today.getMinutes() - today.getTimezoneOffset())
const form = useForm({
    asset_id: props.initial_asset?.id ?? '',
    company_id: props.initial_asset?.company_id ?? '',
    department_id: props.initial_asset?.department_id ?? '',
    location_id: props.initial_asset?.location_id ?? '',
    effective_at: today.toISOString().slice(0, 10),
    reason: '',
})

const selectedAsset = computed(() => props.assets.find((asset) => asset.id === form.asset_id) ?? null)
const departments = computed(() => props.departments.filter((department) => department.company_id === form.company_id))
const locations = computed(() => props.locations.filter((location) => location.company_id === form.company_id))
const selectedLocation = computed(() => locations.value.find((location) => location.id === form.location_id) ?? null)

watch(() => form.asset_id, (assetId) => {
    const asset = props.assets.find((option) => option.id === assetId)
    form.company_id = asset?.company_id ?? ''
    form.department_id = asset?.department_id ?? ''
    form.location_id = asset?.location_id ?? ''
})
watch(() => form.company_id, (companyId, previousCompanyId) => {
    if (previousCompanyId && companyId !== previousCompanyId && companyId !== selectedAsset.value?.company_id) {
        form.department_id = ''
        form.location_id = ''
    }
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
        <AssetManagementPageHeader title="Catat Perpindahan Lokasi / Organisasi" section="Transfer" description="Perpindahan ini mengubah penempatan organisasi aset dan tidak mengubah penanggung jawab atau kondisi." />
        <form class="space-y-5" @submit.prevent="submit">
            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-900 dark:text-white">Aset</h2>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Pilih aset <span class="text-red-600">*</span><Select v-model="form.asset_id" :options="assets.map((asset) => ({ label: `${asset.number} · ${asset.product.name} · ${asset.company.name}`, value: asset.id }))" option-label="label" option-value="value" filter :disabled="!!initial_asset" placeholder="Cari nomor aset atau nama produk" fluid /><small v-if="form.errors.asset_id" class="font-normal text-red-600">{{ form.errors.asset_id }}</small></label>
                <div v-if="selectedAsset" class="grid gap-3 rounded-lg bg-purple-50 p-3 text-sm text-purple-950 dark:bg-purple-950/40 dark:text-purple-200 sm:grid-cols-3"><div><span class="block text-xs opacity-70">Aset</span><strong>{{ selectedAsset.number }}</strong><div>{{ selectedAsset.product.name }} · SN {{ selectedAsset.serial_number ?? '-' }}</div></div><div><span class="block text-xs opacity-70">Penempatan saat ini</span><strong>{{ selectedAsset.company.name }}</strong><div>{{ selectedAsset.department.name }} · {{ selectedAsset.location.name }}</div></div><div><span class="block text-xs opacity-70">Cabang</span><strong>{{ selectedAsset.location.branch?.name ?? 'Tidak terkait cabang' }}</strong></div></div>
            </section>

            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div><h2 class="font-semibold text-gray-900 dark:text-white">Penempatan tujuan</h2><p class="mt-1 text-xs text-gray-500">Pilih perusahaan terlebih dahulu untuk memuat departemen dan lokasi yang sesuai.</p></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Perusahaan <span class="text-red-600">*</span><Select v-model="form.company_id" :options="companies.map((company) => ({ label: `${company.code} · ${company.name}`, value: company.id }))" option-label="label" option-value="value" filter placeholder="Pilih perusahaan" fluid /><small v-if="form.errors.company_id" class="font-normal text-red-600">{{ form.errors.company_id }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Departemen <span class="text-red-600">*</span><Select v-model="form.department_id" :options="departments.map((department) => ({ label: `${department.code} · ${department.name}`, value: department.id }))" option-label="label" option-value="value" filter :disabled="!form.company_id" placeholder="Pilih departemen" fluid /><small v-if="form.errors.department_id" class="font-normal text-red-600">{{ form.errors.department_id }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Lokasi <span class="text-red-600">*</span><Select v-model="form.location_id" :options="locations.map((location) => ({ label: `${location.code} · ${location.name}${location.branch ? ` · ${location.branch.code}` : ''}`, value: location.id }))" option-label="label" option-value="value" filter :disabled="!form.company_id" placeholder="Pilih lokasi" fluid /><small v-if="form.errors.location_id" class="font-normal text-red-600">{{ form.errors.location_id }}</small></label>
                    <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800"><span class="block text-xs text-gray-500">Cabang tujuan (mengikuti lokasi)</span><strong class="mt-1 block text-gray-800 dark:text-gray-100">{{ selectedLocation?.branch ? `${selectedLocation.branch.code} · ${selectedLocation.branch.name}` : selectedLocation ? 'Tidak terkait cabang' : 'Pilih lokasi' }}</strong></div>
                </div>
            </section>

            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-900 dark:text-white">Tanggal dan alasan</h2>
                <label class="grid max-w-sm gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal efektif <span class="text-red-600">*</span><input v-model="form.effective_at" type="date" required :max="today.toISOString().slice(0, 10)" class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="form.errors.effective_at" class="font-normal text-red-600">{{ form.errors.effective_at }}</small></label>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Alasan perpindahan <span class="text-red-600">*</span><textarea v-model="form.reason" rows="4" minlength="5" maxlength="5000" required class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Contoh: pemindahan unit kerja, perubahan kantor, atau relokasi ruang..." /><small v-if="form.errors.reason" class="font-normal text-red-600">{{ form.errors.reason }}</small></label>
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Jika aset dipindahkan ke perusahaan lain dan sedang ditugaskan kepada pengguna dari perusahaan asal, aset harus dikembalikan terlebih dahulu.</p>
            </section>
            <div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" outlined type="button" @click="cancel" /><Button label="Simpan Perpindahan" icon="pi pi-check" type="submit" :loading="form.processing" /></div>
        </form>
    </div>
</template>
