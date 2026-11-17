<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

interface IncidentEvent {
    id: string
    event_type: string
    status_from: string | null
    status_to: string | null
    condition_from: string | null
    condition_to: string | null
    asset_status_from: string | null
    asset_status_to: string | null
    notes: string | null
    created_at: string
    performed_by: { name: string } | null
}
interface RelatedIncident { id: string; number: string; incident_type: string; status: string; occurred_at: string; reported_by: { name: string } | null }

const props = defineProps<{
    incident: {
        id: string
        number: string
        incident_type: string
        severity: string
        status: string
        occurred_at: string
        reported_condition: string | null
        description: string
        involved_parties: string | null
        investigation_findings: string | null
        resolution_type: string | null
        resolution_notes: string | null
        condition_before: string | null
        condition_after: string | null
        asset_status_before: string | null
        asset_status_after: string | null
        evidence_path: string | null
        resolved_at: string | null
        asset: { id: string; number: string; serial_number: string | null; status: string; condition: string; product: { code: string; name: string }; company: { code: string; name: string } | null; department: { code: string; name: string } | null; location: { code: string; name: string } | null; custodian: { name: string; email: string } | null }
        reported_by: { name: string } | null
        resolved_by: { name: string } | null
        events: IncidentEvent[]
    }
    related_incidents: RelatedIncident[]
    can_create: boolean
    can_investigate: boolean
    can_resolve: boolean
    can_dismiss: boolean
    condition_options: Record<string, string>
    resolution_options: Record<string, string>
}>()

const investigateDialog = ref(false)
const resolveDialog = ref(false)
const dismissDialog = ref(false)
const investigationForm = useForm({ investigation_findings: '' })
const resolutionForm = useForm({ resolution_type: Object.keys(props.resolution_options)[0] ?? '', resolution_notes: '', condition_after: props.incident.asset.condition })
const dismissalForm = useForm({ dismissal_reason: '' })
const requiresCondition = computed(() => props.incident.incident_type === 'DAMAGED')

function startInvestigation(): void {
    investigationForm.post(route('transaction.asset-management.incidents.investigate', props.incident.id), {
        preserveScroll: true,
        onSuccess: () => { investigateDialog.value = false },
    })
}

function resolveIncident(): void {
    resolutionForm.post(route('transaction.asset-management.incidents.resolve', props.incident.id), {
        preserveScroll: true,
        onSuccess: () => { resolveDialog.value = false },
    })
}

function dismissIncident(): void {
    dismissalForm.post(route('transaction.asset-management.incidents.dismiss', props.incident.id), {
        preserveScroll: true,
        onSuccess: () => { dismissDialog.value = false },
    })
}

function date(value: string | null): string {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '-'
}

function typeLabel(value: string): string {
    return ({ DAMAGED: 'Rusak', LOST: 'Hilang', STOLEN: 'Dicuri', OTHER: 'Lainnya' } as Record<string, string>)[value] ?? value
}

function statusLabel(value: string): string {
    return ({ REPORTED: 'Dilaporkan', INVESTIGATING: 'Investigasi', RESOLVED: 'Selesai', DISMISSED: 'Ditutup' } as Record<string, string>)[value] ?? value
}

function resolutionLabel(value: string | null): string {
    return value ? (props.resolution_options[value] ?? value) : '-'
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader :title="incident.number" section="Incident" :description="`${incident.asset.number} · ${incident.asset.product.name} · ${typeLabel(incident.incident_type)}`">
            <template #badge><Tag :value="statusLabel(incident.status)" :severity="incident.status === 'RESOLVED' ? 'success' : incident.status === 'DISMISSED' ? 'secondary' : incident.status === 'INVESTIGATING' ? 'info' : 'warn'" /></template>
            <template #actions><Link :href="route('transaction.asset-management.incidents.index')"><Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined /></Link><a v-if="incident.evidence_path" :href="route('transaction.asset-management.incidents.evidence', incident.id)"><Button label="Unduh Bukti" icon="pi pi-download" severity="secondary" outlined /></a><Link v-if="can_create && incident.status === 'RESOLVED' && incident.incident_type === 'DAMAGED' && ['NEEDS_REPAIR', 'DAMAGED'].includes(incident.asset.condition)" :href="route('transaction.asset-management.maintenance.create', incident.asset.id)"><Button label="Buat Request Maintenance" icon="pi pi-wrench" severity="warn" outlined /></Link></template>
        </AssetManagementPageHeader>

        <section class="grid gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.8fr)]">
            <div class="space-y-5">
                <article class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Detail laporan</h2></div>
                    <dl class="grid gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
                        <div><dt class="text-xs text-gray-500">Jenis / Prioritas</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ typeLabel(incident.incident_type) }} · {{ incident.severity }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Waktu kejadian</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ date(incident.occurred_at) }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Dilaporkan oleh</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ incident.reported_by?.name ?? '-' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Kondisi menurut pelapor</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ condition_options[incident.reported_condition ?? ''] ?? incident.reported_condition ?? 'Belum diketahui' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Kronologi</dt><dd class="mt-1 whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200">{{ incident.description }}</dd></div>
                        <div v-if="incident.involved_parties" class="sm:col-span-2"><dt class="text-xs text-gray-500">Pihak terkait / saksi</dt><dd class="mt-1 whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200">{{ incident.involved_parties }}</dd></div>
                        <div v-if="incident.investigation_findings" class="sm:col-span-2"><dt class="text-xs text-gray-500">Temuan investigasi</dt><dd class="mt-1 whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200">{{ incident.investigation_findings }}</dd></div>
                        <div v-if="incident.resolution_notes" class="sm:col-span-2"><dt class="text-xs text-gray-500">Hasil penanganan · {{ resolutionLabel(incident.resolution_type) }}</dt><dd class="mt-1 whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200">{{ incident.resolution_notes }}</dd></div>
                    </dl>
                </article>

                <article class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Riwayat perubahan</h2><p class="mt-1 text-xs text-gray-500">Status laporan dan perubahan data aset tersimpan per kejadian.</p></div>
                    <ol class="divide-y divide-gray-100 px-5 dark:divide-gray-800">
                        <li v-for="event in incident.events" :key="event.id" class="relative py-4 first:pt-5 last:pb-5">
                            <div class="flex flex-wrap items-center justify-between gap-2"><p class="font-medium text-gray-900 dark:text-white">{{ event.event_type === 'REPORTED' ? 'Laporan dibuat' : event.event_type === 'INVESTIGATION_STARTED' ? 'Investigasi dimulai' : event.event_type === 'RESOLVED' ? 'Insiden diselesaikan' : 'Laporan ditutup' }}</p><time class="text-xs text-gray-500">{{ date(event.created_at) }}</time></div>
                            <p class="mt-1 text-xs text-gray-500">{{ event.performed_by?.name ?? '-' }}<span v-if="event.status_from || event.status_to"> · {{ statusLabel(event.status_from ?? '') }} → {{ statusLabel(event.status_to ?? '') }}</span></p>
                            <p v-if="event.condition_from !== event.condition_to && (event.condition_from || event.condition_to)" class="mt-1 text-xs text-gray-600">Kondisi: {{ condition_options[event.condition_from ?? ''] ?? event.condition_from }} → {{ condition_options[event.condition_to ?? ''] ?? event.condition_to }}</p>
                            <p v-if="event.asset_status_from !== event.asset_status_to && (event.asset_status_from || event.asset_status_to)" class="mt-1 text-xs text-gray-600">Status aset: {{ event.asset_status_from }} → {{ event.asset_status_to }}</p>
                            <p v-if="event.notes" class="mt-2 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ event.notes }}</p>
                        </li>
                    </ol>
                </article>

                <article v-if="related_incidents.length" class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Insiden lain pada aset ini</h2></div>
                    <div class="divide-y divide-gray-100 px-5 dark:divide-gray-800"><Link v-for="related in related_incidents" :key="related.id" :href="route('transaction.asset-management.incidents.show', related.id)" class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm hover:text-purple-700"><span class="font-medium">{{ related.number }} · {{ typeLabel(related.incident_type) }}</span><span class="text-xs text-gray-500">{{ date(related.occurred_at) }} · {{ statusLabel(related.status) }}</span></Link></div>
                </article>
            </div>

            <aside class="space-y-5">
                <article class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Aset terkait</h2><p class="mt-2 text-lg font-semibold text-purple-800 dark:text-purple-300">{{ incident.asset.number }}</p><p class="text-sm text-gray-600 dark:text-gray-300">{{ incident.asset.product.name }} · {{ incident.asset.product.code }}</p>
                    <dl class="mt-4 space-y-3 border-t border-gray-100 pt-4 text-sm dark:border-gray-800"><div><dt class="text-xs text-gray-500">Serial number</dt><dd class="mt-0.5">{{ incident.asset.serial_number ?? '-' }}</dd></div><div><dt class="text-xs text-gray-500">Kondisi saat ini</dt><dd class="mt-0.5">{{ condition_options[incident.asset.condition] ?? incident.asset.condition }}</dd></div><div><dt class="text-xs text-gray-500">Status aset</dt><dd class="mt-0.5">{{ incident.asset.status }}</dd></div><div><dt class="text-xs text-gray-500">Lokasi</dt><dd class="mt-0.5">{{ incident.asset.location ? `${incident.asset.location.code} · ${incident.asset.location.name}` : '-' }}</dd></div><div><dt class="text-xs text-gray-500">Penanggung jawab</dt><dd class="mt-0.5">{{ incident.asset.custodian?.name ?? 'Belum di-assign' }}</dd></div></dl>
                    <p v-if="incident.condition_before || incident.asset_status_before" class="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">Snapshot saat laporan → kondisi/status setelah penanganan: {{ condition_options[incident.condition_before ?? ''] ?? incident.condition_before ?? '-' }} → {{ condition_options[incident.condition_after ?? ''] ?? incident.condition_after ?? '-' }}; {{ incident.asset_status_before ?? '-' }} → {{ incident.asset_status_after ?? '-' }}.</p>
                </article>
                <article v-if="can_investigate || can_resolve || can_dismiss" class="space-y-3 rounded-xl border border-purple-200 bg-purple-50 p-5 dark:border-purple-900 dark:bg-purple-950/30">
                    <h2 class="font-semibold text-purple-950 dark:text-purple-100">Tindak lanjut</h2>
                    <p class="text-xs text-purple-900/80 dark:text-purple-200">{{ can_investigate ? 'Mulai pemeriksaan lalu catat temuan awal.' : can_resolve ? 'Catat hasil akhir. Status/kondisi aset akan diperbarui sesuai hasil tersebut.' : 'Laporan yang tidak valid ditutup tanpa mengubah status atau kondisi aset.' }}</p>
                    <Button v-if="can_investigate" label="Mulai Investigasi" icon="pi pi-search" class="w-full" @click="investigateDialog = true" />
                    <Button v-if="can_resolve" label="Selesaikan Insiden" icon="pi pi-check-circle" severity="success" class="w-full" @click="resolveDialog = true" />
                    <Button v-if="can_dismiss" label="Tutup sebagai Tidak Valid" icon="pi pi-times" severity="danger" outlined class="w-full" @click="dismissDialog = true" />
                </article>
                <article v-else class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><h2 class="font-semibold text-gray-900 dark:text-white">Penanganan</h2><p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ incident.status === 'RESOLVED' ? `Diselesaikan ${date(incident.resolved_at)} oleh ${incident.resolved_by?.name ?? '-'}.` : incident.status === 'DISMISSED' ? 'Laporan ditutup sebagai tidak valid.' : 'Tidak ada tindakan tersedia.' }}</p></article>
            </aside>
        </section>

        <Dialog v-model:visible="investigateDialog" modal header="Mulai Investigasi" :style="{ width: 'min(38rem, 94vw)' }"><form class="space-y-4" @submit.prevent="startInvestigation"><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Pemeriksaan awal <span class="text-red-600">*</span><textarea v-model="investigationForm.investigation_findings" rows="4" required minlength="5" maxlength="10000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Catat pemeriksaan dan tindak lanjut yang diperlukan..." /><small v-if="investigationForm.errors.investigation_findings" class="font-normal text-red-600">{{ investigationForm.errors.investigation_findings }}</small></label><div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" text type="button" @click="investigateDialog = false" /><Button label="Simpan Temuan" icon="pi pi-check" type="submit" :loading="investigationForm.processing" /></div></form></Dialog>

        <Dialog v-model:visible="resolveDialog" modal header="Selesaikan Insiden" :style="{ width: 'min(42rem, 94vw)' }"><form class="space-y-4" @submit.prevent="resolveIncident"><p class="text-sm text-gray-600 dark:text-gray-300">Simpan hasil final pemeriksaan. Untuk laporan hilang/dicuri, status aset akan berubah hanya jika hasil mengonfirmasi hilang atau ditemukan kembali.</p><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Hasil <span class="text-red-600">*</span><Select v-model="resolutionForm.resolution_type" :options="Object.entries(resolution_options).map(([value, label]) => ({ value, label }))" option-label="label" option-value="value" fluid /><small v-if="resolutionForm.errors.resolution_type" class="font-normal text-red-600">{{ resolutionForm.errors.resolution_type }}</small></label><label v-if="requiresCondition" class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Kondisi aset setelah penanganan <span class="text-red-600">*</span><Select v-model="resolutionForm.condition_after" :options="Object.entries(condition_options).map(([value, label]) => ({ value, label }))" option-label="label" option-value="value" fluid /><small v-if="resolutionForm.errors.condition_after" class="font-normal text-red-600">{{ resolutionForm.errors.condition_after }}</small></label><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Catatan penanganan <span class="text-red-600">*</span><textarea v-model="resolutionForm.resolution_notes" rows="4" required minlength="5" maxlength="10000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Catat tindakan, pihak terkait, dan tindak lanjut..." /><small v-if="resolutionForm.errors.resolution_notes" class="font-normal text-red-600">{{ resolutionForm.errors.resolution_notes }}</small></label><div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" text type="button" @click="resolveDialog = false" /><Button label="Simpan Hasil" icon="pi pi-check-circle" severity="success" type="submit" :loading="resolutionForm.processing" /></div></form></Dialog>

        <Dialog v-model:visible="dismissDialog" modal header="Tutup Laporan Tidak Valid" :style="{ width: 'min(38rem, 94vw)' }"><form class="space-y-4" @submit.prevent="dismissIncident"><p class="text-sm text-gray-600 dark:text-gray-300">Penutupan tidak akan mengubah status atau kondisi aset. Catat alasan penutupan.</p><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Alasan <span class="text-red-600">*</span><textarea v-model="dismissalForm.dismissal_reason" rows="3" required minlength="5" maxlength="10000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="dismissalForm.errors.dismissal_reason" class="font-normal text-red-600">{{ dismissalForm.errors.dismissal_reason }}</small></label><div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" text type="button" @click="dismissDialog = false" /><Button label="Tutup Laporan" icon="pi pi-times" severity="danger" type="submit" :loading="dismissalForm.processing" /></div></form></Dialog>
    </div>
</template>
