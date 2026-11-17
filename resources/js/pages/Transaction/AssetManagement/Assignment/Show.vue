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

interface Snapshot {
    custodian_name?: string | null
    custodian_email?: string | null
    company?: string | null
    department?: string | null
    location?: string | null
    branch?: string | null
}
interface AssignmentHistory {
    id: string
    event_type: 'ASSIGNED' | 'TRANSFERRED' | 'RETURNED' | string
    from_snapshot: Snapshot | null
    to_snapshot: Snapshot | null
    effective_at: string
    remarks: string | null
    from_condition: string | null
    to_condition: string | null
    performed_by: { name: string } | null
}
interface MaintenanceRecord {
    id: string
    number: string
    maintenance_type: string
    status: string
    requested_at: string
    completed_at: string | null
    actual_cost: string | null
    estimated_cost: string | null
}
interface TransferSnapshot { company?: string | null; department?: string | null; branch?: string | null; location?: string | null }
interface TransferHistory {
    id: string
    number: string
    effective_at: string
    reason: string
    from_snapshot: TransferSnapshot
    to_snapshot: TransferSnapshot
    performed_by: { name: string } | null
}
interface Disposal {
    id: string
    number: string
    disposal_type: string
    status: string
    disposal_date: string
    amount: string | null
    reason: string
    decision_notes: string | null
    requested_by: { name: string } | null
    decided_by: { name: string } | null
}
interface Asset {
    id: string
    number: string
    status: string
    condition: string
    registration_method: string
    serial_number: string | null
    specifications: Record<string, string> | null
    acquisition_date: string
    acquisition_cost: string | null
    product: { code: string; name: string; category?: { name: string } | null; type?: { name: string } | null; brand?: { name: string } | null; model?: { name: string } | null }
    company: { code: string; name: string }
    department: { code: string; name: string }
    location: { code: string; name: string }
    branch: { code: string; name: string } | null
    custodian: { id: number; name: string; email: string } | null
    assignment_histories: AssignmentHistory[]
    maintenance_records: MaintenanceRecord[]
    transfer_histories: TransferHistory[]
    disposals: Disposal[]
}

const props = defineProps<{ asset: Asset; can_manage: boolean; can_create_maintenance: boolean; can_dispose: boolean; condition_options: Record<string, string> }>()

const returnDialogVisible = ref(false)
const today = new Date()
today.setMinutes(today.getMinutes() - today.getTimezoneOffset())
const returnForm = useForm({
    condition: props.asset.condition || 'GOOD',
    effective_at: today.toISOString().slice(0, 10),
    remarks: '',
})

const assignmentStatus = computed(() => {
    if (props.asset.status === 'DISPOSAL_PENDING') return 'Menunggu Pelepasan'
    if (props.asset.status === 'DISPOSED') return 'Disposed'
    if (props.asset.status === 'MISSING') return 'Tidak Ditemukan'
    return props.asset.custodian ? 'Assigned' : 'Available'
})

const statusSeverity = computed(() => {
    if (props.asset.status === 'MISSING') return 'danger'
    if (props.asset.status === 'DISPOSAL_PENDING') return 'warn'
    if (props.asset.status === 'DISPOSED') return 'warn'
    return props.asset.custodian ? 'info' : 'success'
})

function date(value: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(`${value}T00:00:00`))
}

function eventLabel(event: AssignmentHistory): string {
    if (event.event_type === 'ASSIGNED') return 'Aset ditugaskan'
    if (event.event_type === 'TRANSFERRED') return 'Penanggung jawab dipindahkan'
    if (event.event_type === 'RETURNED') return 'Aset dikembalikan'
    return event.event_type
}

function conditionLabel(condition: string | null): string {
    return condition ? props.condition_options[condition] ?? condition : '-'
}

function printHistory(event: AssignmentHistory): void {
    const printUrl = new URL(route('transaction.asset-management.assignments.history.print', event.id), window.location.origin)
    printUrl.searchParams.set('print', '1')
    printUrl.searchParams.set('closeAfterPrint', '1')
    window.open(printUrl.toString(), '_blank', 'noopener')
}

function maintenanceStatusLabel(status: string): string {
    return ({ REQUESTED: 'Draft Pemeriksaan', WAITING_APPROVAL: 'Menunggu Approval', WAITING_PARTS: 'Menunggu Sparepart', READY_TO_START: 'Siap Dikerjakan', IN_PROGRESS: 'Sedang Dikerjakan', COMPLETED: 'Selesai', CANCELLED: 'Dibatalkan' } as Record<string, string>)[status] ?? status
}

function disposalTypeLabel(type: string): string {
    return ({ SOLD: 'Dijual', DONATED: 'Dihibahkan', WRITTEN_OFF: 'Dihapuskan' } as Record<string, string>)[type] ?? type
}

function disposalStatusLabel(status: string): string {
    return ({ PENDING_APPROVAL: 'Menunggu Keputusan', APPROVED: 'Disetujui', REJECTED: 'Ditolak' } as Record<string, string>)[status] ?? status
}

function returnAsset(): void {
    returnForm.post(route('transaction.asset-management.assignments.return', props.asset.id), {
        preserveScroll: true,
        onSuccess: () => {
            returnDialogVisible.value = false
        returnForm.reset('remarks')
        },
    })
}

function backToDirectory(): void {
    router.get(route('transaction.asset-management.assignments.index'))
}
</script>

<template>
    <div>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader :title="asset.number" section="Assignment" :description="`${asset.product.name} · ${asset.product.code}`">
            <template #badge><Tag :value="assignmentStatus" :severity="statusSeverity" /></template>
            <template #actions>
                <Button label="Kembali" severity="secondary" outlined @click="backToDirectory" />
                <Link v-if="can_manage && asset.status === 'ACTIVE'" :href="route('transaction.asset-management.assignments.create', asset.id)">
                    <Button :label="asset.custodian ? 'Transfer' : 'Assign Aset'" :icon="asset.custodian ? 'pi pi-sync' : 'pi pi-user-plus'" />
                </Link>
                <Link v-if="can_manage && asset.status === 'ACTIVE'" :href="route('transaction.asset-management.transfers.create', asset.id)"><Button label="Pindahkan Lokasi" icon="pi pi-arrows-alt" severity="secondary" outlined /></Link>
                <Link v-if="can_create_maintenance && asset.status === 'ACTIVE'" :href="route('transaction.asset-management.maintenance.create', asset.id)"><Button label="Request Maintenance" icon="pi pi-wrench" severity="secondary" outlined /></Link>
                <Link v-if="can_dispose && asset.status === 'ACTIVE' && !asset.custodian" :href="route('transaction.asset-management.disposals.create', asset.id)"><Button label="Ajukan Pelepasan" icon="pi pi-ban" severity="warn" outlined /></Link>
                <Button v-if="can_manage && asset.custodian" label="Return" icon="pi pi-replay" severity="warn" outlined @click="returnDialogVisible = true" />
            </template>
        </AssetManagementPageHeader>

        <section class="grid gap-4 lg:grid-cols-[1fr_1.2fr]">
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-200"><i class="pi pi-box" /></span><div><h2 class="font-semibold text-gray-900 dark:text-white">Kondisi penugasan saat ini</h2><p class="text-xs text-gray-500 dark:text-gray-400">Status dan penempatan aset</p></div></div>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-gray-500">Penanggung jawab</dt><dd class="mt-1 font-medium">{{ asset.custodian?.name ?? 'Belum ditugaskan' }}</dd><dd v-if="asset.custodian" class="text-xs text-gray-500">{{ asset.custodian.email }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Lokasi fisik</dt><dd class="mt-1 font-medium">{{ asset.location.name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Perusahaan</dt><dd class="mt-1 font-medium">{{ asset.company.name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Departemen</dt><dd class="mt-1 font-medium">{{ asset.department.name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Cabang</dt><dd class="mt-1 font-medium">{{ asset.branch?.name ?? '-' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Serial number</dt><dd class="mt-1 font-medium">{{ asset.serial_number || '-' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Kondisi aset</dt><dd class="mt-1 font-medium">{{ conditionLabel(asset.condition) }}</dd></div>
                </dl>
                <p class="mt-5 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">Assignment mengubah penanggung jawab. Informasi departemen dan lokasi fisik tetap dicatat terpisah.</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold text-gray-900 dark:text-white">Riwayat Assignment</h2><p class="text-xs text-gray-500 dark:text-gray-400">Setiap assign, transfer, dan return tercatat di sini.</p></div><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ asset.assignment_histories.length }} aktivitas</span></div>
                <div v-if="asset.assignment_histories.length" class="mt-5 space-y-0">
                    <article v-for="(event, index) in asset.assignment_histories" :key="event.id" class="relative flex gap-3 pb-5 last:pb-0">
                        <div class="flex w-5 shrink-0 flex-col items-center"><span class="z-10 mt-1 h-3 w-3 rounded-full border-2 border-purple-600 bg-white dark:bg-gray-900" /><span v-if="index < asset.assignment_histories.length - 1" class="absolute top-4 bottom-0 w-px bg-gray-200 dark:bg-gray-700" /></div>
                        <div class="min-w-0 flex-1 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/70">
                            <div class="flex flex-wrap items-start justify-between gap-2"><h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ eventLabel(event) }}</h3><div class="flex items-center gap-2"><time class="text-xs text-gray-500">{{ date(event.effective_at) }}</time><Button icon="pi pi-print" severity="secondary" text rounded aria-label="Cetak bukti serah terima" v-tooltip.top="'Cetak bukti serah terima / return'" @click="printHistory(event)" /></div></div>
                            <p class="mt-2 text-xs leading-5 text-gray-600 dark:text-gray-300"><span class="font-medium">{{ event.from_snapshot?.custodian_name ?? 'Available' }}</span><i class="pi pi-arrow-right mx-2 text-[10px] text-gray-400" /><span class="font-medium">{{ event.to_snapshot?.custodian_name ?? 'Available' }}</span></p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">Kondisi: {{ conditionLabel(event.from_condition) }} <i class="pi pi-arrow-right mx-1 text-[10px] text-gray-400" /> {{ conditionLabel(event.to_condition) }}</p>
                            <p v-if="event.remarks" class="mt-2 whitespace-pre-line text-xs text-gray-600 dark:text-gray-300">{{ event.remarks }}</p>
                            <p class="mt-2 text-[11px] text-gray-500">Dicatat oleh {{ event.performed_by?.name ?? 'Pengguna tidak tersedia' }}</p>
                        </div>
                    </article>
                </div>
                <div v-else class="mt-5 rounded-lg border border-dashed border-gray-300 p-6 text-center dark:border-gray-700"><i class="pi pi-history text-2xl text-gray-400" /><p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">Belum ada riwayat assignment</p><p class="mt-1 text-xs text-gray-500">{{ asset.custodian ? 'Penanggung jawab saat ini berasal dari data registrasi. Perubahan berikutnya akan mulai tercatat di sini.' : 'Aktivitas assignment aset akan muncul di bagian ini.' }}</p></div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800"><div><h2 class="font-semibold text-gray-900 dark:text-white">Riwayat Perpindahan Organisasi</h2><p class="mt-1 text-xs text-gray-500">Perubahan perusahaan, departemen, cabang, dan lokasi aset.</p></div><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ asset.transfer_histories.length }} perpindahan</span></div>
            <div v-if="asset.transfer_histories.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                <article v-for="transfer in asset.transfer_histories" :key="transfer.id" class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                    <div class="min-w-0"><Link :href="route('transaction.asset-management.transfers.show', transfer.id)" class="text-sm font-semibold text-purple-700 hover:underline dark:text-purple-300">{{ transfer.number }}</Link><p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ transfer.from_snapshot.company }} · {{ transfer.from_snapshot.department }} · {{ transfer.from_snapshot.location }}</p><p class="mt-1 text-xs text-gray-600 dark:text-gray-300"><i class="pi pi-arrow-down-right mr-1 text-purple-600" />{{ transfer.to_snapshot.company }} · {{ transfer.to_snapshot.department }} · {{ transfer.to_snapshot.location }}</p><p class="mt-2 text-xs text-gray-500">{{ transfer.reason }}</p><p class="mt-1 text-[11px] text-gray-500">Dicatat oleh {{ transfer.performed_by?.name ?? '-' }}</p></div>
                    <time class="shrink-0 text-xs text-gray-500">{{ date(transfer.effective_at) }}</time>
                </article>
            </div>
            <div v-else class="p-5 text-sm text-gray-500">Belum ada perubahan penempatan organisasi untuk aset ini.</div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800"><div><h2 class="font-semibold text-gray-900 dark:text-white">Riwayat Pelepasan</h2><p class="mt-1 text-xs text-gray-500">Seluruh pengajuan dan keputusan pelepasan aset.</p></div><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ asset.disposals.length }} pengajuan</span></div>
            <div v-if="asset.disposals.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                <article v-for="disposal in asset.disposals" :key="disposal.id" class="flex flex-wrap items-start justify-between gap-3 px-5 py-4"><div><Link :href="route('transaction.asset-management.disposals.show', disposal.id)" class="text-sm font-semibold text-purple-700 hover:underline dark:text-purple-300">{{ disposal.number }}</Link><p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ disposalTypeLabel(disposal.disposal_type) }} · {{ disposalStatusLabel(disposal.status) }} · {{ date(disposal.disposal_date) }}</p><p class="mt-2 text-xs text-gray-500">{{ disposal.reason }}</p><p v-if="disposal.decision_notes" class="mt-1 text-xs text-gray-500">Catatan keputusan: {{ disposal.decision_notes }}</p></div><Tag :value="disposalStatusLabel(disposal.status)" :severity="disposal.status === 'APPROVED' ? 'success' : disposal.status === 'REJECTED' ? 'danger' : 'warn'" /></article>
            </div>
            <div v-else class="p-5 text-sm text-gray-500">Belum ada riwayat pengajuan pelepasan untuk aset ini.</div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800"><div><h2 class="font-semibold text-gray-900 dark:text-white">Riwayat Maintenance</h2><p class="mt-1 text-xs text-gray-500">Riwayat pemeriksaan dan service untuk aset ini.</p></div><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ asset.maintenance_records.length }} catatan</span></div>
            <div v-if="asset.maintenance_records.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                <div v-for="record in asset.maintenance_records" :key="record.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"><div><Link :href="route('transaction.asset-management.maintenance.show', record.id)" class="text-sm font-semibold text-purple-700 hover:underline dark:text-purple-300">{{ record.number }}</Link><p class="mt-1 text-xs text-gray-500">{{ record.maintenance_type === 'PREVENTIVE' ? 'Preventive' : 'Corrective' }} · {{ date(record.requested_at) }} · Selesai {{ date(record.completed_at ?? '') }}</p></div><div class="flex items-center gap-3"><span class="text-xs text-gray-500">{{ record.actual_cost ?? record.estimated_cost ? `Rp ${Number(record.actual_cost ?? record.estimated_cost).toLocaleString('id-ID')}` : '-' }}</span><Tag :value="maintenanceStatusLabel(record.status)" :severity="record.status === 'COMPLETED' || record.status === 'READY_TO_START' ? 'success' : record.status === 'CANCELLED' ? 'danger' : ['IN_PROGRESS', 'WAITING_PARTS'].includes(record.status) ? 'info' : 'warn'" /></div></div>
            </div>
            <div v-else class="flex flex-wrap items-center justify-between gap-3 p-5"><p class="text-sm text-gray-500">Belum ada riwayat pemeriksaan atau service untuk aset ini.</p><Link v-if="can_create_maintenance && asset.status === 'ACTIVE'" :href="route('transaction.asset-management.maintenance.create', asset.id)"><Button label="Buat Request" icon="pi pi-plus" size="small" severity="secondary" outlined /></Link></div>
        </section>
    </div>

    <Dialog v-model:visible="returnDialogVisible" modal header="Return Aset" :style="{ width: 'min(34rem, 94vw)' }">
        <form class="space-y-4" @submit.prevent="returnAsset">
            <p class="text-sm text-gray-600 dark:text-gray-300">Aset <strong>{{ asset.number }}</strong> akan dilepas dari penanggung jawab <strong>{{ asset.custodian?.name }}</strong> dan menjadi Available.</p>
            <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Kondisi saat dikembalikan <span class="text-red-600">*</span><select v-model="returnForm.condition" required class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"><option v-for="(label, value) in condition_options" :key="value" :value="value">{{ label }}</option></select><small v-if="returnForm.errors.condition" class="font-normal text-red-600">{{ returnForm.errors.condition }}</small></label>
            <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal pengembalian <span class="text-red-600">*</span><input v-model="returnForm.effective_at" type="date" required class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="returnForm.errors.effective_at" class="font-normal text-red-600">{{ returnForm.errors.effective_at }}</small></label>
            <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Catatan <span class="font-normal text-gray-500">(opsional)</span><textarea v-model="returnForm.remarks" rows="3" maxlength="2000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Kondisi saat aset dikembalikan..." /><small v-if="returnForm.errors.remarks" class="font-normal text-red-600">{{ returnForm.errors.remarks }}</small></label>
            <small v-if="returnForm.errors.asset" class="block text-red-600">{{ returnForm.errors.asset }}</small>
            <div class="flex justify-end gap-2 pt-2"><Button label="Batal" severity="secondary" text type="button" @click="returnDialogVisible = false" /><Button label="Konfirmasi Return" icon="pi pi-replay" severity="warn" type="submit" :loading="returnForm.processing" /></div>
        </form>
    </Dialog>
    </div>
</template>
