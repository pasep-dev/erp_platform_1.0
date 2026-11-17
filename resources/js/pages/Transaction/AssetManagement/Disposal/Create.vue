<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'

defineOptions({ layout: AppLayout })

interface AssetOption {
    id: string; number: string; serial_number: string | null; condition: string
    product: { code: string; name: string }; company: { code: string; name: string }
    department: { code: string; name: string }; location: { code: string; name: string }
}

const props = defineProps<{ initial_asset: AssetOption | null; assets: AssetOption[]; type_options: Record<string, string> }>()
const now = new Date()
now.setMinutes(now.getMinutes() - now.getTimezoneOffset())
const form = useForm({
    asset_id: props.initial_asset?.id ?? '',
    disposal_type: 'SOLD',
    disposal_date: now.toISOString().slice(0, 10),
    amount: null as number | null,
    reason: '',
    evidence: null as File | null,
})
const selectedAsset = computed(() => props.assets.find((asset) => asset.id === form.asset_id) ?? null)
const evidenceInput = ref<HTMLInputElement | null>(null)

function setEvidence(event: Event): void {
    form.evidence = (event.target as HTMLInputElement).files?.[0] ?? null
}

function submit(): void {
    form.post(route('transaction.asset-management.disposals.store'), { forceFormData: true, preserveScroll: true })
}

function cancel(): void {
    if (props.initial_asset) {
        router.get(route('transaction.asset-management.assignments.show', props.initial_asset.id))
        return
    }
    router.get(route('transaction.asset-management.disposals.index'))
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader title="Ajukan Pelepasan Aset" section="Disposal" description="Aset tetap tercatat. Statusnya baru menjadi Disposed setelah pengajuan disetujui." />
        <form class="space-y-5" @submit.prevent="submit">
            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><h2 class="font-semibold text-gray-900 dark:text-white">Aset</h2><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Pilih aset <span class="text-red-600">*</span><Select v-model="form.asset_id" :options="assets.map((asset) => ({ label: `${asset.number} · ${asset.product.name} · ${asset.company.code}`, value: asset.id }))" option-label="label" option-value="value" filter :disabled="!!initial_asset" placeholder="Cari nomor aset atau produk" fluid /><small v-if="form.errors.asset_id" class="font-normal text-red-600">{{ form.errors.asset_id }}</small></label><div v-if="selectedAsset" class="grid gap-3 rounded-lg bg-purple-50 p-3 text-sm text-purple-950 dark:bg-purple-950/40 dark:text-purple-200 sm:grid-cols-2"><div><span class="block text-xs opacity-70">Aset</span><strong>{{ selectedAsset.number }} · {{ selectedAsset.product.name }}</strong><div>Serial number: {{ selectedAsset.serial_number ?? 'Tidak ada' }}</div></div><div><span class="block text-xs opacity-70">Penempatan</span><strong>{{ selectedAsset.company.name }}</strong><div>{{ selectedAsset.department.name }} · {{ selectedAsset.location.name }}</div></div></div><p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Aset harus berstatus aktif, tidak sedang ditugaskan, dan tidak memiliki maintenance yang belum selesai. Kembalikan aset terlebih dahulu jika masih dipegang pengguna.</p></section>
            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><h2 class="font-semibold text-gray-900 dark:text-white">Rencana pelepasan</h2><div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Jenis pelepasan <span class="text-red-600">*</span><Select v-model="form.disposal_type" :options="Object.entries(type_options).map(([value, label]) => ({ value, label }))" option-label="label" option-value="value" fluid /><small v-if="form.errors.disposal_type" class="font-normal text-red-600">{{ form.errors.disposal_type }}</small></label><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal pelepasan <span class="text-red-600">*</span><input v-model="form.disposal_date" type="date" required :max="now.toISOString().slice(0, 10)" class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="form.errors.disposal_date" class="font-normal text-red-600">{{ form.errors.disposal_date }}</small></label><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 sm:col-span-2">Nilai hasil pelepasan (opsional)<InputNumber v-model="form.amount" mode="currency" currency="IDR" locale="id-ID" :min="0" :min-fraction-digits="0" :max-fraction-digits="2" fluid /><small v-if="form.errors.amount" class="font-normal text-red-600">{{ form.errors.amount }}</small></label></div><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Alasan dan keterangan <span class="text-red-600">*</span><textarea v-model="form.reason" rows="5" minlength="5" maxlength="10000" required class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Jelaskan kondisi aset, alasan dijual/dihibahkan/dihapuskan, serta informasi pendukung..." /><small v-if="form.errors.reason" class="font-normal text-red-600">{{ form.errors.reason }}</small></label><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Dokumen pendukung<input ref="evidenceInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" @change="setEvidence" /><small class="font-normal text-gray-500">JPG, PNG, WEBP, atau PDF. Maksimal 10 MB. {{ form.evidence?.name ?? '' }}</small><small v-if="form.errors.evidence" class="font-normal text-red-600">{{ form.errors.evidence }}</small></label></section>
            <div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" outlined type="button" @click="cancel" /><Button label="Ajukan Pelepasan" icon="pi pi-send" severity="warn" type="submit" :loading="form.processing" /></div>
        </form>
    </div>
</template>
