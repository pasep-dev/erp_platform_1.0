<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Select from 'primevue/select'

defineOptions({ layout: AppLayout })

interface AssetOption { id: string; number: string; serial_number: string | null; condition: string; status: string; product: { code: string; name: string } }
const props = defineProps<{
    assets: AssetOption[]
    incident_types: Record<string, string>
    severity_options: Record<string, string>
    condition_options: Record<string, string>
}>()

const now = new Date()
now.setMinutes(now.getMinutes() - now.getTimezoneOffset())
const form = useForm({ asset_id: '', incident_type: 'DAMAGED', severity: 'NORMAL', occurred_at: now.toISOString().slice(0, 16), reported_condition: '', description: '', involved_parties: '', evidence: null as File | null })
const selectedAsset = computed(() => props.assets.find((asset) => asset.id === form.asset_id) ?? null)
const evidenceInput = ref<HTMLInputElement | null>(null)

function selectEvidence(event: Event): void {
    form.evidence = (event.target as HTMLInputElement).files?.[0] ?? null
}

function submit(): void {
    form.post(route('transaction.asset-management.incidents.store'), { forceFormData: true, preserveScroll: true })
}

function cancel(): void {
    router.get(route('transaction.asset-management.incidents.index'))
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader title="Laporkan Insiden Aset" section="Incident" description="Catatan ini memulai pemeriksaan. Status dan kondisi aset tetap seperti semula sampai hasil investigasi disimpan." />
        <form class="space-y-5" @submit.prevent="submit">
            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-900 dark:text-white">Informasi kejadian</h2>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Aset <span class="text-red-600">*</span><Select v-model="form.asset_id" :options="assets.map((asset) => ({ label: `${asset.number} · ${asset.product.name} · ${asset.product.code}`, value: asset.id }))" option-label="label" option-value="value" filter placeholder="Cari kode aset atau nama produk" fluid /><small v-if="form.errors.asset_id" class="font-normal text-red-600">{{ form.errors.asset_id }}</small></label>
                <div v-if="selectedAsset" class="rounded-lg bg-purple-50 p-3 text-sm text-purple-900 dark:bg-purple-950/40 dark:text-purple-200"><span class="font-semibold">{{ selectedAsset.number }}</span> · {{ selectedAsset.product.name }} · SN {{ selectedAsset.serial_number ?? '-' }}<div class="mt-1 text-xs">Status {{ selectedAsset.status }} · Kondisi {{ condition_options[selectedAsset.condition] ?? selectedAsset.condition }}</div></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Jenis insiden <span class="text-red-600">*</span><Select v-model="form.incident_type" :options="Object.entries(incident_types).map(([value, label]) => ({ value, label }))" option-label="label" option-value="value" fluid /><small v-if="form.errors.incident_type" class="font-normal text-red-600">{{ form.errors.incident_type }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Tingkat prioritas <span class="text-red-600">*</span><Select v-model="form.severity" :options="Object.entries(severity_options).map(([value, label]) => ({ value, label }))" option-label="label" option-value="value" fluid /><small v-if="form.errors.severity" class="font-normal text-red-600">{{ form.errors.severity }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Waktu kejadian <span class="text-red-600">*</span><input v-model="form.occurred_at" type="datetime-local" required class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="form.errors.occurred_at" class="font-normal text-red-600">{{ form.errors.occurred_at }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Kondisi yang dilaporkan<Select v-model="form.reported_condition" :options="Object.entries(condition_options).map(([value, label]) => ({ value, label }))" option-label="label" option-value="value" show-clear placeholder="Belum diketahui" fluid /><small v-if="form.errors.reported_condition" class="font-normal text-red-600">{{ form.errors.reported_condition }}</small></label>
                </div>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Kronologi dan keterangan <span class="text-red-600">*</span><textarea v-model="form.description" rows="5" required maxlength="10000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Jelaskan kejadian, lokasi, dampak, dan pihak yang mengetahui..." /><small v-if="form.errors.description" class="font-normal text-red-600">{{ form.errors.description }}</small></label>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Pihak terkait / saksi<textarea v-model="form.involved_parties" rows="2" maxlength="5000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Nama, unit, atau kontak pihak yang terlibat (opsional)" /><small v-if="form.errors.involved_parties" class="font-normal text-red-600">{{ form.errors.involved_parties }}</small></label>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Bukti foto atau dokumen<input ref="evidenceInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" @change="selectEvidence" /><small class="font-normal text-gray-500">JPG, PNG, WEBP, atau PDF. Maksimal 10 MB. {{ form.evidence?.name ?? '' }}</small><small v-if="form.errors.evidence" class="font-normal text-red-600">{{ form.errors.evidence }}</small></label>
            </section>
            <div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" outlined type="button" @click="cancel" /><Button label="Simpan Laporan" icon="pi pi-check" type="submit" :loading="form.processing" /></div>
        </form>
    </div>
</template>
