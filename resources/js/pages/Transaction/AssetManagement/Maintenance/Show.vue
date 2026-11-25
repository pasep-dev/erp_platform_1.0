<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

interface UserSummary { id: number; name: string }
interface MaintenanceComponent {
    id: string
    component_name: string
    quantity: string
    estimated_unit_cost: string
    needed_at: string
}
interface MaintenanceHistoryItem {
    id: string
    number: string
    maintenance_type: string
    status: string
    requested_at: string
    submitted_at: string | null
    approved_at: string | null
    rejected_at: string | null
    completed_at: string | null
    cancelled_at: string | null
    estimated_cost: string | null
    actual_cost: string | null
    reported_condition: string
    completed_condition: string | null
    inspection_result: string | null
    work_summary: string | null
    cancellation_reason: string | null
    requested_by: UserSummary | null
    approved_by: UserSummary | null
    rejected_by: UserSummary | null
    cancelled_by: UserSummary | null
    completed_by: UserSummary | null
    components: MaintenanceComponent[]
}
interface MaintenanceRecord extends MaintenanceHistoryItem {
    priority: string
    issue_description: string
    reported_condition: string
    completed_condition: string | null
    scheduled_at: string | null
    started_at: string | null
    next_service_date: string | null
    approval_remarks: string | null
    approval_document_path: string | null
    cancelled_at: string | null
    asset: {
        id: string
        number: string
        serial_number: string | null
        condition: string
        product: { code: string; name: string }
        company: { code: string; name: string }
        department: { code: string; name: string }
        location: { code: string; name: string }
        custodian: { name: string; email: string } | null
        maintenance_records: MaintenanceHistoryItem[]
    }
    vendor: { code: string; name: string; phone: string | null; email: string | null; address: string | null } | null
    components: MaintenanceComponent[]
}

const props = defineProps<{
    maintenance: MaintenanceRecord
    can_submit: boolean
    can_mark_approved: boolean
    can_reject: boolean
    can_update: boolean
    can_cancel: boolean
    can_print: boolean
    condition_options: Record<string, string>
    status_labels: Record<string, string>
}>()

const approvalDialogVisible = ref(false)
const rejectDialogVisible = ref(false)
const cancelDialogVisible = ref(false)
const approvalForm = useForm<{ signed_document: File | null; remarks: string }>({ signed_document: null, remarks: '' })
const rejectForm = useForm({ rejection_reason: '' })
const cancelForm = useForm({ cancellation_reason: '' })
const hasApprovalDecision = computed(() => props.maintenance.approved_at !== null || props.maintenance.rejected_at !== null)
const latestCompletedService = computed(() => props.maintenance.asset.maintenance_records
    .filter((record) => record.id !== props.maintenance.id && record.status === 'COMPLETED' && record.completed_at)
    .sort((left, right) => (right.completed_at ?? '').localeCompare(left.completed_at ?? ''))[0] ?? null)

function statusSeverity(value: string): string {
    return ({ REQUESTED: 'warn', WAITING_APPROVAL: 'info', WAITING_PARTS: 'info', READY_TO_START: 'info', IN_PROGRESS: 'info', COMPLETED: 'success', CANCELLED: props.maintenance.rejected_at ? 'danger' : 'secondary' } as Record<string, string>)[value] ?? 'secondary'
}

function date(value: string | null): string {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(`${value.slice(0, 10)}T00:00:00`)) : '-'
}

function money(value: string | null): string {
    return value === null ? '-' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value))
}

function condition(value: string | null): string {
    return value ? props.condition_options[value] ?? value : '-'
}

function printRequest(): void {
    const printUrl = new URL(route('transaction.asset-management.maintenance.print', props.maintenance.id), window.location.origin)
    printUrl.searchParams.set('print', '1')
    printUrl.searchParams.set('closeAfterPrint', '1')
    window.open(printUrl.toString(), '_blank', 'noopener')
}

function submitForApproval(): void {
    router.post(route('transaction.asset-management.maintenance.submit', props.maintenance.id), {}, { preserveScroll: true })
}

function setApprovalDocument(event: Event): void {
    approvalForm.signed_document = (event.target as HTMLInputElement).files?.[0] ?? null
}

function markApproved(): void {
    approvalForm.post(route('transaction.asset-management.maintenance.mark-approved', props.maintenance.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { approvalDialogVisible.value = false },
    })
}

function markRejected(): void {
    rejectForm.post(route('transaction.asset-management.maintenance.mark-rejected', props.maintenance.id), {
        preserveScroll: true,
        onSuccess: () => { rejectDialogVisible.value = false },
    })
}

function cancelRequest(): void {
    cancelForm.post(route('transaction.asset-management.maintenance.cancel', props.maintenance.id), {
        preserveScroll: true,
        onSuccess: () => { cancelDialogVisible.value = false },
    })
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader :title="maintenance.number" section="Maintenance" :description="`${maintenance.asset.number} · ${maintenance.asset.product.name}`">
            <template #badge><Tag :value="status_labels[maintenance.status] ?? maintenance.status" :severity="statusSeverity(maintenance.status)" /></template>
            <template #actions>
                <Link :href="route('transaction.asset-management.maintenance.index')"><Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined /></Link>
                <Button v-if="can_print" label="Cetak Pengajuan" icon="pi pi-print" severity="secondary" outlined @click="printRequest" />
                <Link v-if="can_update" :href="route('transaction.asset-management.maintenance.edit', maintenance.id)"><Button label="Edit Pemeriksaan" icon="pi pi-pencil" severity="secondary" outlined /></Link>
                <Button v-if="can_submit" label="Ajukan" icon="pi pi-send" @click="submitForApproval" />
                <Button v-if="can_mark_approved" label="Mark Approval" icon="pi pi-check-circle" severity="success" @click="approvalDialogVisible = true" />
                <Button v-if="can_reject" label="Mark Rejected" icon="pi pi-times-circle" severity="danger" outlined @click="rejectDialogVisible = true" />
                <Button v-if="can_cancel" label="Batalkan" icon="pi pi-times" severity="secondary" outlined @click="cancelDialogVisible = true" />
            </template>
        </AssetManagementPageHeader>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-5">
                <section v-if="maintenance.status === 'REQUESTED' && !can_submit && can_update" class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                    Lengkapi <span v-if="!maintenance.inspection_result">tanggapan / rencana perbaikan<span v-if="maintenance.estimated_cost === null"> dan </span></span><span v-if="maintenance.estimated_cost === null">estimasi biaya</span> melalui tombol <strong>Edit Pemeriksaan</strong>. Setelah disimpan, tombol Ajukan akan tersedia.
                </section>
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Permintaan dan Tanggapan</h2></div>
                    <dl class="grid gap-x-8 gap-y-5 px-5 py-5 sm:grid-cols-2 sm:px-6">
                        <div><dt class="text-xs text-gray-500">Jenis maintenance</dt><dd class="mt-1 text-sm font-medium">{{ maintenance.maintenance_type === 'PREVENTIVE' ? 'Preventive' : 'Perbaikan / Corrective' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Prioritas</dt><dd class="mt-1 text-sm font-medium">{{ maintenance.priority }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Tanggal permintaan</dt><dd class="mt-1 text-sm font-medium">{{ date(maintenance.requested_at) }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Pemohon / pencatat</dt><dd class="mt-1 text-sm font-medium">{{ maintenance.requested_by?.name ?? '-' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Keluhan / alasan service</dt><dd class="mt-1 whitespace-pre-line text-sm">{{ maintenance.issue_description }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Kondisi saat diperiksa</dt><dd class="mt-1 text-sm font-medium">{{ condition(maintenance.reported_condition) }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Vendor / pelaksana</dt><dd class="mt-1 text-sm font-medium">{{ maintenance.vendor?.name ?? 'Tim internal IT / GA' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Tanggapan / rencana perbaikan</dt><dd class="mt-1 whitespace-pre-line text-sm">{{ maintenance.inspection_result || 'Belum dicatat.' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Estimasi biaya</dt><dd class="mt-1 text-sm font-medium">{{ money(maintenance.estimated_cost) }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Jadwal pengerjaan</dt><dd class="mt-1 text-sm font-medium">{{ date(maintenance.scheduled_at) }}</dd></div>
                    </dl>
                </section>

                <section v-if="maintenance.execution_mode === 'INTERNAL'" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Komponen yang Dibutuhkan</h2><p class="mt-1 text-xs text-gray-500">Daftar komponen dan estimasi untuk rencana perbaikan internal.</p></div>
                    <div v-if="maintenance.components.length" class="overflow-x-auto">
                        <table class="w-full min-w-[620px] text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th class="px-5 py-3">Komponen</th><th class="px-5 py-3">Jumlah</th><th class="px-5 py-3">Harga satuan</th><th class="px-5 py-3">Dibutuhkan</th><th class="px-5 py-3 text-right">Subtotal</th></tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800"><tr v-for="component in maintenance.components" :key="component.id"><td class="px-5 py-3 font-medium">{{ component.component_name }}</td><td class="px-5 py-3">{{ Number(component.quantity) }}</td><td class="px-5 py-3">{{ money(component.estimated_unit_cost) }}</td><td class="px-5 py-3">{{ date(component.needed_at) }}</td><td class="px-5 py-3 text-right font-medium">{{ money(String(Number(component.quantity) * Number(component.estimated_unit_cost))) }}</td></tr></tbody>
                        </table>
                    </div>
                    <p v-else class="px-5 py-4 text-sm text-gray-500">Tidak ada komponen pembelian yang dicatat untuk request ini.</p>
                </section>

                <section v-if="hasApprovalDecision" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Keputusan Persetujuan</h2>
                    <p v-if="maintenance.approved_at" class="mt-2 text-sm text-gray-700 dark:text-gray-300">Disetujui {{ date(maintenance.approved_at) }} oleh {{ maintenance.approved_by?.name ?? '-' }}.</p>
                    <p v-if="maintenance.approval_remarks" class="mt-2 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ maintenance.approval_remarks }}</p>
                    <a v-if="maintenance.approval_document_path" :href="route('transaction.asset-management.maintenance.approval-document', maintenance.id)" class="mt-3 inline-flex text-sm font-medium text-purple-700 hover:underline dark:text-purple-300">Unduh bukti persetujuan</a>
                    <p v-if="maintenance.rejected_at" class="mt-2 text-sm text-red-700 dark:text-red-300">Ditolak {{ date(maintenance.rejected_at) }} oleh {{ maintenance.rejected_by?.name ?? '-' }}.</p>
                    <p v-if="maintenance.rejected_at && maintenance.cancellation_reason" class="mt-2 whitespace-pre-line text-sm text-red-700 dark:text-red-300">Alasan: {{ maintenance.cancellation_reason }}</p>
                </section>

                <section v-if="maintenance.work_summary" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Hasil Pengerjaan</h2></div>
                    <dl class="grid gap-x-8 gap-y-4 px-5 py-5 sm:grid-cols-2"><div class="sm:col-span-2"><dt class="text-xs text-gray-500">Tindakan yang dilakukan</dt><dd class="mt-1 whitespace-pre-line text-sm">{{ maintenance.work_summary }}</dd></div><div><dt class="text-xs text-gray-500">Tanggal selesai</dt><dd class="mt-1 text-sm">{{ date(maintenance.completed_at) }}</dd></div><div><dt class="text-xs text-gray-500">Dicatat oleh</dt><dd class="mt-1 text-sm">{{ maintenance.completed_by?.name ?? '-' }}</dd></div><div><dt class="text-xs text-gray-500">Kondisi akhir</dt><dd class="mt-1 text-sm">{{ condition(maintenance.completed_condition) }}</dd></div><div><dt class="text-xs text-gray-500">Biaya aktual</dt><dd class="mt-1 text-sm">{{ money(maintenance.actual_cost) }}</dd></div><div v-if="maintenance.next_service_date"><dt class="text-xs text-gray-500">Jadwal service berikutnya</dt><dd class="mt-1 text-sm">{{ date(maintenance.next_service_date) }}</dd></div></dl>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Riwayat Maintenance Aset</h2><p class="mt-1 text-xs text-gray-500">Setiap request tetap tersimpan sebagai catatan terpisah pada aset ini.</p></div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="record in maintenance.asset.maintenance_records" :key="record.id" class="space-y-2 px-5 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-3"><div><Link :href="route('transaction.asset-management.maintenance.show', record.id)" class="text-sm font-semibold text-purple-700 hover:underline dark:text-purple-300">{{ record.number }}</Link><p class="mt-1 text-xs text-gray-500">{{ date(record.requested_at) }} · Dicatat oleh {{ record.requested_by?.name ?? '-' }}</p></div><div class="flex items-center gap-3"><span class="text-xs text-gray-500">Aktual {{ money(record.actual_cost) }} · Estimasi {{ money(record.estimated_cost) }}</span><Tag :value="status_labels[record.status] ?? record.status" :severity="statusSeverity(record.status)" /></div></div>
                            <p v-if="record.inspection_result" class="text-sm text-gray-700 dark:text-gray-300">Tanggapan: {{ record.inspection_result }}</p>
                            <p v-if="record.components?.length" class="text-sm text-gray-700 dark:text-gray-300">Komponen: {{ record.components.map((component) => `${component.component_name} (${component.quantity} × ${money(component.estimated_unit_cost)})`).join(', ') }}</p>
                            <p v-if="record.work_summary" class="text-sm text-gray-700 dark:text-gray-300">Hasil: {{ record.work_summary }} · Kondisi akhir {{ condition(record.completed_condition) }}</p>
                            <p v-if="record.completed_at" class="text-xs text-gray-500">Selesai {{ date(record.completed_at) }} oleh {{ record.completed_by?.name ?? '-' }}</p>
                            <p v-if="record.approved_at" class="text-xs text-gray-500">Disetujui {{ date(record.approved_at) }} oleh {{ record.approved_by?.name ?? '-' }}</p>
                            <p v-if="record.rejected_at" class="text-xs text-red-700 dark:text-red-300">Ditolak {{ date(record.rejected_at) }} oleh {{ record.rejected_by?.name ?? '-' }} · {{ record.cancellation_reason }}</p>
                            <p v-else-if="record.cancelled_at" class="text-xs text-gray-500">Dibatalkan {{ date(record.cancelled_at) }} oleh {{ record.cancelled_by?.name ?? '-' }} · {{ record.cancellation_reason }}</p>
                        </div>
                    </div>
                </section>
            </div>

            <aside>
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Aset Terkait</h2>
                    <p class="mt-3 text-lg font-semibold text-purple-800 dark:text-purple-200">{{ maintenance.asset.number }}</p>
                    <p class="mt-1 text-sm font-medium">{{ maintenance.asset.product.name }}</p>
                    <p class="text-xs text-gray-500">{{ maintenance.asset.product.code }} · SN {{ maintenance.asset.serial_number || '-' }}</p>
                    <dl class="mt-4 space-y-3 border-t border-gray-100 pt-4 text-sm dark:border-gray-800"><div><dt class="text-xs text-gray-500">Perusahaan</dt><dd class="mt-1">{{ maintenance.asset.company.name }}</dd></div><div><dt class="text-xs text-gray-500">Departemen / lokasi</dt><dd class="mt-1">{{ maintenance.asset.department.name }} · {{ maintenance.asset.location.name }}</dd></div><div><dt class="text-xs text-gray-500">Penanggung jawab</dt><dd class="mt-1">{{ maintenance.asset.custodian?.name ?? 'Available' }}</dd></div></dl>
                </section>

                <section class="mt-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Maintenance / Service Terakhir</h2>
                    <template v-if="latestCompletedService">
                        <Link :href="route('transaction.asset-management.maintenance.show', latestCompletedService.id)" class="mt-3 inline-flex font-mono text-sm font-semibold text-purple-700 hover:underline dark:text-purple-300">{{ latestCompletedService.number }}</Link>
                        <p class="mt-1 text-xs text-gray-500">{{ date(latestCompletedService.completed_at) }} · {{ latestCompletedService.maintenance_type === 'PREVENTIVE' ? 'Preventive' : 'Corrective' }}</p>
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ latestCompletedService.work_summary || '-' }}</p>
                    </template>
                    <p v-else class="mt-3 text-sm text-gray-500">-</p>
                </section>
            </aside>
        </section>

        <Dialog v-model:visible="approvalDialogVisible" modal header="Catat Persetujuan Manual" :style="{ width: 'min(38rem, 94vw)' }">
            <form class="space-y-4" @submit.prevent="markApproved">
                <p class="text-sm text-gray-600 dark:text-gray-300">Konfirmasi setelah persetujuan fisik diterima. Bukti tanda tangan dapat dilampirkan untuk arsip.</p>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Dokumen bertanda tangan (opsional, PDF/JPG/PNG)<input type="file" accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border border-gray-300 bg-white p-2 text-sm dark:border-gray-700 dark:bg-gray-950" @change="setApprovalDocument" /><small v-if="approvalForm.errors.signed_document" class="font-normal text-red-600">{{ approvalForm.errors.signed_document }}</small></label>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Catatan persetujuan (opsional)<textarea v-model="approvalForm.remarks" rows="3" maxlength="2000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" /></label>
                <div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" text type="button" @click="approvalDialogVisible = false" /><Button label="Mark Approval" icon="pi pi-check" severity="success" type="submit" :loading="approvalForm.processing" /></div>
            </form>
        </Dialog>

        <Dialog v-model:visible="rejectDialogVisible" modal header="Tolak Pengajuan Maintenance" :style="{ width: 'min(34rem, 94vw)' }">
            <form class="space-y-4" @submit.prevent="markRejected"><p class="text-sm text-gray-600 dark:text-gray-300">Pengajuan yang ditolak akan berstatus Ditolak dan tersimpan di riwayat aset.</p><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Alasan penolakan <span class="text-red-600">*</span><textarea v-model="rejectForm.rejection_reason" rows="3" required minlength="5" maxlength="2000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="rejectForm.errors.rejection_reason" class="font-normal text-red-600">{{ rejectForm.errors.rejection_reason }}</small></label><div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" text type="button" @click="rejectDialogVisible = false" /><Button label="Tolak Pengajuan" icon="pi pi-times" severity="danger" type="submit" :loading="rejectForm.processing" /></div></form>
        </Dialog>

        <Dialog v-model:visible="cancelDialogVisible" modal header="Batalkan Draft Maintenance" :style="{ width: 'min(34rem, 94vw)' }">
            <form class="space-y-4" @submit.prevent="cancelRequest"><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Alasan pembatalan <span class="text-red-600">*</span><textarea v-model="cancelForm.cancellation_reason" rows="3" required minlength="5" maxlength="2000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="cancelForm.errors.cancellation_reason" class="font-normal text-red-600">{{ cancelForm.errors.cancellation_reason }}</small></label><div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" text type="button" @click="cancelDialogVisible = false" /><Button label="Batalkan Draft" icon="pi pi-times" severity="danger" type="submit" :loading="cancelForm.processing" /></div></form>
        </Dialog>
    </div>
</template>
