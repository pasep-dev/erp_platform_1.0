<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AppPagination from '@/components/common/AppPagination.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

interface AuditItem {
    id: string
    result: string
    expected_snapshot: {
        number: string
        serial_number: string | null
        product: string | null
        product_code: string | null
        location_id: string
        location: string | null
        condition: string
    }
    observed_location_id: string | null
    observed_condition: string | null
    remarks: string | null
    checked_at: string | null
    resolution_remarks: string | null
    resolved_at: string | null
    asset: { number: string; serial_number: string | null; product: { code: string; name: string }; location: { code: string; name: string } }
    observed_location: { code: string; name: string } | null
    checked_by: { id: number; name: string } | null
    resolved_by: { id: number; name: string } | null
}
interface LocationOption { id: string; code: string; name: string }

const props = defineProps<{
    audit: { id: string; number: string; status: string; scope_snapshot: Record<string, string | null>; started_at: string; completed_at: string | null; remarks: string | null; created_by: { id: number; name: string } | null; completed_by: { id: number; name: string } | null }
    items: { data: AuditItem[]; total: number; per_page: number; links: { url: string | null; label: string; active: boolean }[] }
    summary: { total: number; pending: number; found: number; discrepancies: number; unresolved: number }
    locations: LocationOption[]
    condition_options: Record<string, string>
    can_manage: boolean
    can_print: boolean
}>()

const scanForm = useForm({ asset_number: '' })
const findingForm = useForm({ result: 'FOUND', observed_location_id: '', observed_condition: '', remarks: '' })
const resolutionForm = useForm({ resolution_remarks: '' })
const completeForm = useForm({})
const perPage = ref(props.items.per_page)
const findingDialog = ref(false)
const resolutionDialog = ref(false)
const completeDialog = ref(false)
const selectedItem = ref<AuditItem | null>(null)
const resultOptions = [
    { label: 'Ditemukan sesuai', value: 'FOUND' },
    { label: 'Berpindah lokasi', value: 'MOVED' },
    { label: 'Rusak / perlu perhatian', value: 'DAMAGED' },
    { label: 'Tidak ditemukan', value: 'MISSING' },
]
const showLocationField = computed(() => findingForm.result === 'MOVED')
const showConditionField = computed(() => findingForm.result === 'DAMAGED')

const Detector = (window as unknown as { BarcodeDetector?: new (options: { formats: string[] }) => { detect: (source: HTMLVideoElement) => Promise<{ rawValue: string }[]> } }).BarcodeDetector
const videoElement = ref<HTMLVideoElement | null>(null)
const scannerVisible = ref(false)
const scannerError = ref('')
let cameraStream: MediaStream | null = null
let cameraTimer: number | null = null
let scanPaused = false

function resultLabel(value: string): string {
    return ({ PENDING: 'Belum Diperiksa', FOUND: 'Sesuai', MOVED: 'Berpindah Lokasi', DAMAGED: 'Rusak', MISSING: 'Tidak Ditemukan' } as Record<string, string>)[value] ?? value
}

function resultSeverity(value: string): string {
    return ({ PENDING: 'secondary', FOUND: 'success', MOVED: 'warn', DAMAGED: 'danger', MISSING: 'danger' } as Record<string, string>)[value] ?? 'secondary'
}

function date(value: string | null): string {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '-'
}

function applyPerPage(): void {
    router.get(route('transaction.asset-management.audits.show', props.audit.id), { per_page: perPage.value }, { preserveScroll: true, preserveState: true, replace: true })
}

function submitScan(): void {
    if (!scanForm.asset_number.trim()) return
    scanPaused = true
    scanForm.post(route('transaction.asset-management.audits.scan', props.audit.id), {
        preserveScroll: true,
        onSuccess: () => scanForm.reset(),
        onFinish: () => {
            window.setTimeout(() => { scanPaused = false }, 1200)
        },
    })
}

function openFinding(item: AuditItem): void {
    selectedItem.value = item
    findingForm.result = item.result === 'PENDING' ? 'FOUND' : item.result
    findingForm.observed_location_id = item.observed_location_id ?? ''
    findingForm.observed_condition = item.observed_condition ?? (item.result === 'DAMAGED' ? 'DAMAGED' : '')
    findingForm.remarks = item.remarks ?? ''
    findingDialog.value = true
}

function saveFinding(): void {
    if (!selectedItem.value) return
    findingForm.put(route('transaction.asset-management.audits.items.update', { audit: props.audit.id, item: selectedItem.value.id }), {
        preserveScroll: true,
        onSuccess: () => { findingDialog.value = false },
    })
}

function markMissing(item: AuditItem): void {
    router.put(route('transaction.asset-management.audits.items.update', { audit: props.audit.id, item: item.id }), { result: 'MISSING', remarks: item.remarks ?? '' }, { preserveScroll: true })
}

function openResolution(item: AuditItem): void {
    selectedItem.value = item
    resolutionForm.resolution_remarks = item.resolution_remarks ?? ''
    resolutionDialog.value = true
}

function resolveItem(): void {
    if (!selectedItem.value) return
    resolutionForm.post(route('transaction.asset-management.audits.items.resolve', { audit: props.audit.id, item: selectedItem.value.id }), {
        preserveScroll: true,
        onSuccess: () => { resolutionDialog.value = false },
    })
}

function completeAudit(): void {
    completeForm.post(route('transaction.asset-management.audits.complete', props.audit.id), {
        preserveScroll: true,
        onSuccess: () => { completeDialog.value = false },
    })
}

async function startCamera(): Promise<void> {
    scannerError.value = ''
    if (!Detector) {
        scannerError.value = 'Browser ini belum mendukung pemindaian QR melalui kamera. Masukkan kode aset secara manual.'
        return
    }

    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
        scannerVisible.value = true
        await new Promise<void>((resolve) => window.setTimeout(resolve, 0))
        if (!videoElement.value) return
        videoElement.value.srcObject = cameraStream
        await videoElement.value.play()
        const detector = new Detector({ formats: ['qr_code'] })

        const detect = async (): Promise<void> => {
            if (!scannerVisible.value || !videoElement.value) return
            if (!scanPaused && videoElement.value.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
                try {
                    const codes = await detector.detect(videoElement.value)
                    if (codes[0]?.rawValue) {
                        scanForm.asset_number = codes[0].rawValue.trim()
                        submitScan()
                    }
                } catch {
                    scannerError.value = 'QR tidak terbaca. Arahkan kamera ke kode aset dan coba kembali.'
                }
            }
            cameraTimer = window.setTimeout(() => { void detect() }, 250)
        }

        void detect()
    } catch {
        scannerError.value = 'Kamera tidak dapat dibuka. Izinkan akses kamera atau masukkan kode aset secara manual.'
        stopCamera()
    }
}

function stopCamera(): void {
    scannerVisible.value = false
    if (cameraTimer !== null) window.clearTimeout(cameraTimer)
    cameraTimer = null
    cameraStream?.getTracks().forEach((track) => track.stop())
    cameraStream = null
    if (videoElement.value) videoElement.value.srcObject = null
}

onBeforeUnmount(stopCamera)
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader :title="audit.number" section="Audit" :description="`${Object.values(audit.scope_snapshot).filter(Boolean).join(' · ') || 'Semua aset aktif'} · Dibuat ${audit.created_by?.name ?? '-'}`">
            <template #badge><Tag :value="audit.status === 'IN_PROGRESS' ? 'Sedang Berjalan' : 'Selesai'" :severity="audit.status === 'IN_PROGRESS' ? 'info' : 'success'" /></template>
            <template #actions><Link :href="route('transaction.asset-management.audits.index')"><Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined /></Link><Link v-if="can_print" :href="route('transaction.asset-management.audits.print', audit.id)" target="_blank"><Button label="Cetak Laporan" icon="pi pi-print" severity="secondary" outlined /></Link><Button v-if="can_manage" label="Selesaikan Audit" icon="pi pi-check-circle" severity="success" :disabled="summary.pending > 0 || summary.unresolved > 0" @click="completeDialog = true" /></template>
        </AssetManagementPageHeader>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><article v-for="card in [{ label: 'Total Aset', value: summary.total }, { label: 'Belum Diperiksa', value: summary.pending }, { label: 'Sesuai', value: summary.found }, { label: 'Selisih', value: `${summary.discrepancies} · ${summary.unresolved} belum ditinjau` }]" :key="card.label" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"><p class="text-xs text-gray-500">{{ card.label }}</p><p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ card.value }}</p></article></section>

        <section v-if="can_manage" class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div><h2 class="font-semibold text-gray-900 dark:text-white">Periksa QR Aset</h2><p class="mt-1 text-sm text-gray-500">Scan QR pada label aset. Anda juga dapat memasukkan kode aset secara manual.</p></div>
            <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="submitScan"><InputText v-model="scanForm.asset_number" class="min-w-0 flex-1" placeholder="Kode aset, contoh CAA-LTP001HO" /><Button label="Catat Ditemukan" icon="pi pi-check" type="submit" :loading="scanForm.processing" /><Button v-if="!scannerVisible" label="Buka Kamera" icon="pi pi-camera" severity="secondary" outlined type="button" @click="startCamera" /><Button v-else label="Tutup Kamera" icon="pi pi-camera" severity="danger" outlined type="button" @click="stopCamera" /></form>
            <p v-if="scanForm.errors.asset_number" class="text-sm text-red-600">{{ scanForm.errors.asset_number }}</p><p v-if="scannerError" class="text-sm text-amber-700">{{ scannerError }}</p>
            <video v-if="scannerVisible" ref="videoElement" autoplay muted playsinline class="mx-auto max-h-80 w-full rounded-lg bg-black object-contain" />
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Daftar Aset dalam Audit</h2><p class="mt-1 text-xs text-gray-500">Selisih memerlukan catatan tinjauan sebelum sesi dapat diselesaikan.</p></div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                <article v-for="item in items.data" :key="item.id" class="grid gap-3 px-4 py-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                    <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="font-semibold text-gray-900 dark:text-white">{{ item.expected_snapshot.number }}</span><Tag :value="resultLabel(item.result)" :severity="resultSeverity(item.result)" /><Tag v-if="item.result !== 'PENDING' && item.result !== 'FOUND'" :value="item.resolved_at ? 'Ditinjau' : 'Perlu Tinjauan'" :severity="item.resolved_at ? 'success' : 'warn'" /></div><p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ item.expected_snapshot.product_code }} · {{ item.expected_snapshot.product }}</p><p class="text-xs text-gray-500">SN {{ item.expected_snapshot.serial_number || '-' }} · Lokasi tercatat: {{ item.expected_snapshot.location || '-' }} · Kondisi: {{ condition_options[item.expected_snapshot.condition] ?? item.expected_snapshot.condition }}</p><p v-if="item.result === 'MOVED'" class="mt-1 text-xs text-amber-700">Lokasi hasil pemeriksaan: {{ item.observed_location?.name ?? '-' }}</p><p v-if="item.result === 'DAMAGED'" class="mt-1 text-xs text-red-700">Kondisi hasil pemeriksaan: {{ condition_options[item.observed_condition ?? ''] ?? item.observed_condition }}</p><p v-if="item.remarks" class="mt-1 whitespace-pre-line text-xs text-gray-600">{{ item.remarks }}</p><p v-if="item.resolution_remarks" class="mt-1 whitespace-pre-line text-xs text-green-700">Tinjauan: {{ item.resolution_remarks }} · {{ item.resolved_by?.name ?? '-' }}</p><p v-if="item.checked_at" class="mt-1 text-xs text-gray-400">Diperiksa {{ date(item.checked_at) }} oleh {{ item.checked_by?.name ?? '-' }}</p></div>
                    <div v-if="can_manage" class="flex flex-wrap gap-2 lg:justify-end"><Button v-if="item.result === 'PENDING'" label="Tandai Tidak Ditemukan" icon="pi pi-exclamation-triangle" severity="danger" outlined size="small" @click="markMissing(item)" /><Button v-if="!item.resolved_at" label="Catat Hasil" icon="pi pi-pencil" severity="secondary" outlined size="small" @click="openFinding(item)" /><Button v-if="item.result !== 'PENDING' && item.result !== 'FOUND' && !item.resolved_at" label="Tinjau Selisih" icon="pi pi-check-circle" severity="success" outlined size="small" @click="openResolution(item)" /></div>
                </article>
                <p v-if="items.data.length === 0" class="px-5 py-12 text-center text-sm text-gray-500">Tidak ada aset pada sesi audit ini.</p>
            </div>
            <AppPagination v-if="items.total > 0" v-model:per-page="perPage" :meta="items" @change="applyPerPage" />
        </section>

        <Dialog v-model:visible="findingDialog" modal header="Catat Hasil Pemeriksaan" :style="{ width: 'min(40rem, 94vw)' }">
            <form class="space-y-4" @submit.prevent="saveFinding"><p class="text-sm text-gray-600 dark:text-gray-300">Aset: <strong>{{ selectedItem?.expected_snapshot.number }}</strong></p><label class="grid gap-1.5 text-sm font-medium">Hasil pemeriksaan<Select v-model="findingForm.result" :options="resultOptions" option-label="label" option-value="value" fluid /><small v-if="findingForm.errors.result" class="font-normal text-red-600">{{ findingForm.errors.result }}</small></label><label v-if="showLocationField" class="grid gap-1.5 text-sm font-medium">Lokasi yang ditemukan<Select v-model="findingForm.observed_location_id" :options="locations.map((location) => ({ label: `${location.code} · ${location.name}`, value: location.id }))" option-label="label" option-value="value" filter placeholder="Pilih lokasi aktual" fluid /><small v-if="findingForm.errors.observed_location_id" class="font-normal text-red-600">{{ findingForm.errors.observed_location_id }}</small></label><label v-if="showConditionField" class="grid gap-1.5 text-sm font-medium">Kondisi aktual<Select v-model="findingForm.observed_condition" :options="Object.entries(condition_options).map(([value, label]) => ({ value, label }))" option-label="label" option-value="value" fluid /><small v-if="findingForm.errors.observed_condition" class="font-normal text-red-600">{{ findingForm.errors.observed_condition }}</small></label><label class="grid gap-1.5 text-sm font-medium">Catatan<textarea v-model="findingForm.remarks" rows="3" maxlength="2000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" /></label><div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" text type="button" @click="findingDialog = false" /><Button label="Simpan Hasil" icon="pi pi-check" type="submit" :loading="findingForm.processing" /></div></form>
        </Dialog>

        <Dialog v-model:visible="resolutionDialog" modal header="Tinjau Selisih Audit" :style="{ width: 'min(38rem, 94vw)' }"><form class="space-y-4" @submit.prevent="resolveItem"><p class="text-sm text-gray-600 dark:text-gray-300">Catat tindak lanjut atau hasil verifikasi untuk <strong>{{ selectedItem?.expected_snapshot.number }}</strong>. Tinjauan ini tersimpan bersama sesi audit.</p><label class="grid gap-1.5 text-sm font-medium">Catatan resolusi <span class="text-red-600">*</span><textarea v-model="resolutionForm.resolution_remarks" rows="4" required minlength="3" maxlength="2000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Contoh: lokasi sistem diperbarui setelah pengecekan fisik" /><small v-if="resolutionForm.errors.resolution_remarks" class="font-normal text-red-600">{{ resolutionForm.errors.resolution_remarks }}</small></label><div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" text type="button" @click="resolutionDialog = false" /><Button label="Simpan Tinjauan" icon="pi pi-check" severity="success" type="submit" :loading="resolutionForm.processing" /></div></form></Dialog>

        <Dialog v-model:visible="completeDialog" modal header="Selesaikan Audit" :style="{ width: 'min(32rem, 94vw)' }"><form class="space-y-4" @submit.prevent="completeAudit"><p class="text-sm text-gray-600 dark:text-gray-300">Sesi akan dikunci sebagai riwayat. Pastikan semua aset sudah diperiksa dan semua selisih sudah ditinjau.</p><div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" text type="button" @click="completeDialog = false" /><Button label="Selesaikan Audit" icon="pi pi-check-circle" severity="success" type="submit" :loading="completeForm.processing" /></div></form></Dialog>
    </div>
</template>
