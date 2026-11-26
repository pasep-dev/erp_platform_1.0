<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import AssetBarcode from '@/components/asset/AssetBarcode.vue'
import AssetQrCode from '@/components/asset/AssetQrCode.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

interface Asset {
    id: string
    number: string
    created_at?: string | null
    status: string
    registration_method: string
    serial_number: string | null
    acquisition_date: string | null
    acquisition_cost: string | null
    remarks: string | null
    registered_by?: { name: string } | null
    goods_receipt_item?: { goods_receipt?: {
        number: string
        received_at: string | null
        created_by?: { name: string } | null
        posted_by?: { name: string } | null
        purchase_order?: {
            number: string
            created_at: string | null
            submitted_at: string | null
            submitted_by?: { name: string } | null
            purchase_request?: { number: string; created_at: string | null; approved_at: string | null; requester?: { name: string } | null } | null
        } | null
        purchase_request?: { number: string; created_at: string | null; approved_at: string | null; requester?: { name: string } | null } | null
    } | null } | null
    product?: { code: string; name: string } | null
    company?: { code: string; name: string } | null
    department?: { name: string } | null
    location?: { name: string } | null
    branch?: { name: string } | null
    assignment_type: 'USER' | 'LOCATION'
    custodian?: { name: string } | null
    assignment_histories: { id: string; number: string; event_type: string; effective_at: string | null; from_custodian?: { name: string } | null; to_custodian?: { name: string } | null; performed_by?: { name: string } | null; remarks: string | null }[]
    maintenance_records: { id: string; number: string; status: string; maintenance_type: string; requested_at: string | null; completed_at: string | null; issue_description: string | null; work_summary: string | null; actual_cost: string | null; next_service_date: string | null; vendor?: { name: string } | null }[]
    audit_items: { id: string; result: string; checked_at: string | null; observed_condition: string | null; remarks: string | null; session?: { number: string; status: string } | null; observed_location?: { name: string } | null; checked_by?: { name: string } | null }[]
    incidents: { id: string; number: string; incident_type: string; status: string; occurred_at: string | null; description: string; resolution_type: string | null; resolution_notes: string | null; reported_by?: { name: string } | null; events: { event_type: string; notes: string | null; created_at: string; performed_by?: { name: string } | null }[] }[]
    transfer_histories: { id: string; number: string; effective_at: string | null; reason: string; from_snapshot: Record<string, string> | null; to_snapshot: Record<string, string> | null; performed_by?: { name: string } | null }[]
    disposals: { id: string; number: string; disposal_type: string; status: string; disposal_date: string | null; amount: string | null; reason: string; decision_notes: string | null; requested_by?: { name: string } | null; decided_by?: { name: string } | null }[]
}

const props = defineProps<{
    asset: Asset
    purchase_request_approval: { method: string; approved_by: string | null } | null
    can_create_maintenance: boolean
    can_dispose: boolean
}>()

const originTracking = computed(() => {
    if (props.asset.registration_method === 'EXISTING') {
        return [
            {
                title: 'Perolehan Existing',
                reference: 'Pendataan manual',
                dateLabel: 'Tanggal perolehan',
                date: props.asset.acquisition_date,
            },
            {
                title: 'Aset Diregistrasi',
                reference: props.asset.number,
                dateLabel: 'Tanggal pendataan',
                date: props.asset.created_at ?? null,
                responsible: props.asset.registered_by?.name ?? '-',
                actorLabel: 'Diregistrasi oleh',
            },
        ]
    }

    const receipt = props.asset.goods_receipt_item?.goods_receipt
    const purchaseOrder = receipt?.purchase_order
    const purchaseRequest = purchaseOrder?.purchase_request ?? receipt?.purchase_request
    const steps = []

    if (purchaseRequest) {
        steps.push({
            title: 'PR Dibuat',
            reference: purchaseRequest.number,
            date: purchaseRequest.created_at,
            responsible: purchaseRequest.requester?.name ?? '-',
            actorLabel: 'Dibuat oleh',
        })
        if (purchaseRequest.approved_at) {
            steps.push({
                title: 'PR Disetujui',
                reference: purchaseRequest.number,
                date: purchaseRequest.approved_at,
                responsible: props.purchase_request_approval?.approved_by ?? '-',
                method: props.purchase_request_approval?.method ?? '-',
            })
        }
    }

    if (purchaseOrder) {
        steps.push({
            title: 'PO Dikirim',
            reference: purchaseOrder.number,
            date: purchaseOrder.submitted_at ?? purchaseOrder.created_at,
            responsible: purchaseOrder.submitted_by?.name ?? '-',
            actorLabel: 'Dikirim oleh',
        })
    }

    if (receipt) {
        steps.push({
            title: 'GR Diterima',
            reference: receipt.number,
            date: receipt.received_at,
            responsible: receipt.created_by?.name ?? '-',
            confirmedBy: receipt.posted_by?.name ?? null,
        })
    }

    steps.push({
        title: 'Aset Diregistrasi',
        reference: props.asset.number,
        date: props.asset.created_at ?? null,
        responsible: props.asset.registered_by?.name ?? '-',
        actorLabel: 'Diregistrasi oleh',
    })

    return steps
})

function date(value: string | null): string {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value)) : '-'
}

function money(value: string | null): string {
    return value ? `Rp ${Number(value).toLocaleString('id-ID')}` : '-'
}

function snapshot(snapshot: Record<string, string> | null): string {
    if (!snapshot) return '-'
    return [snapshot.company_name, snapshot.department_name, snapshot.location_name, snapshot.custodian_name].filter(Boolean).join(' · ') || '-'
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader :title="asset.number" section="Asset Directory">
            <template #badge><Tag :value="asset.status" :severity="asset.status === 'ACTIVE' ? 'success' : 'secondary'" /></template>
            <template #actions>
                <Link :href="route('transaction.asset-management.directory.index')"><Button label="Kembali ke Asset Directory" icon="pi pi-arrow-left" severity="secondary" outlined /></Link>
                <Link v-if="can_create_maintenance && asset.status === 'ACTIVE'" :href="route('transaction.asset-management.maintenance.create', asset.id)"><Button label="Request Maintenance" icon="pi pi-wrench" severity="secondary" /></Link>
                <Link v-if="can_dispose && asset.status === 'ACTIVE' && !asset.custodian" :href="route('transaction.asset-management.disposals.create', asset.id)"><Button label="Ajukan Pelepasan" icon="pi pi-ban" severity="warn" outlined /></Link>
            </template>
        </AssetManagementPageHeader>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                <h2 class="font-semibold text-gray-900 dark:text-white">Informasi Aset</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Identitas, lokasi, dan asal-usul aset.</p>
            </div>
            <div class="grid lg:grid-cols-[minmax(0,1fr)_16rem]">
                <dl class="grid gap-x-6 gap-y-5 p-5 sm:grid-cols-2 lg:p-6">
                    <div><dt class="text-sm text-gray-500">Produk</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ asset.product?.code }} · {{ asset.product?.name }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Nomor seri</dt><dd class="mt-1">{{ asset.serial_number || '-' }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Metode registrasi</dt><dd class="mt-1">{{ asset.registration_method === 'PROCUREMENT' ? 'Dari procurement' : 'Aset existing' }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Perusahaan / Department</dt><dd class="mt-1">{{ asset.company?.name }} · {{ asset.department?.name }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Lokasi / Cabang</dt><dd class="mt-1">{{ asset.location?.name }} · {{ asset.branch?.name ?? '-' }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Target penempatan</dt><dd class="mt-1">{{ asset.assignment_type === 'USER' ? asset.custodian?.name ?? 'Pengguna tidak tersedia' : asset.location?.name ?? 'Lokasi tidak tersedia' }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Tanggal perolehan / nilai</dt><dd class="mt-1">{{ date(asset.acquisition_date) }} · {{ money(asset.acquisition_cost) }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Diregistrasi oleh</dt><dd class="mt-1">{{ asset.registered_by?.name ?? '-' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-sm text-gray-500">Catatan registrasi</dt><dd class="mt-1 whitespace-pre-line">{{ asset.remarks || '-' }}</dd></div>
                </dl>
                <aside class="flex flex-col items-center justify-center gap-3 border-t border-gray-200 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-white/[0.02] lg:border-l lg:border-t-0">
                    <AssetQrCode :value="asset.number" :company-code="asset.company?.code ?? ''" :show-label="false" />
                    <div class="w-full rounded-xl border-2 border-purple-900 bg-white p-3 dark:border-purple-800">
                        <AssetBarcode :value="asset.number" />
                        <p class="mt-1 text-center font-mono text-xs font-semibold tracking-wide text-gray-700 dark:text-gray-300">{{ asset.number }}</p>
                    </div>
                    <p class="text-center text-xs text-gray-500 dark:text-gray-400">QR dan barcode menggunakan nomor aset yang sama.</p>
                </aside>
            </div>
            <div class="border-t border-gray-200 px-5 py-5 dark:border-gray-800 sm:px-6">
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Asal Muasal & Tracking Aset</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Riwayat dokumen dari sumber perolehan sampai aset masuk ke directory.</p>
                </div>
                <div class="overflow-x-auto pb-2">
                    <ol class="flex min-w-max items-start">
                        <li v-for="(step, index) in originTracking" :key="`${step.title}-${index}`" class="w-56 shrink-0 pr-5">
                            <div class="flex items-center">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ index + 1 }}</span>
                                <span v-if="index < originTracking.length - 1" class="h-px w-full bg-gray-300 dark:bg-gray-700" />
                            </div>
                            <div class="mt-3 pr-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ step.title }}</p>
                                <p class="mt-1 truncate font-mono text-sm font-semibold text-gray-900 dark:text-white" :title="step.reference">{{ step.reference }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ step.dateLabel }} {{ date(step.date) }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ step.actorLabel ?? 'Penanggung jawab' }}: {{ step.responsible ?? '-' }}</p>
                                <p v-if="step.method" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Approval: {{ step.method }}</p>
                                <p v-if="step.confirmedBy" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Dikonfirmasi oleh: {{ step.confirmedBy }}</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold">Riwayat Assignment</h2></div>
            <div v-if="asset.assignment_histories.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                <article v-for="history in asset.assignment_histories" :key="history.id" class="grid gap-2 px-5 py-4 sm:grid-cols-[10rem_1fr_12rem]">
                    <div><p class="font-medium">{{ history.number }} · {{ history.event_type }}</p><p class="text-sm text-gray-500">{{ date(history.effective_at) }}</p></div>
                    <div class="text-sm">{{ history.from_custodian?.name ?? 'Belum ada pemegang' }} → {{ history.to_custodian?.name ?? 'Tidak ada pemegang' }}<p v-if="history.remarks" class="mt-1 text-gray-500">{{ history.remarks }}</p></div>
                    <p class="text-sm text-gray-500">Dicatat oleh {{ history.performed_by?.name ?? '-' }}</p>
                </article>
            </div>
            <p v-else class="px-5 py-4 text-sm text-gray-500">Belum ada riwayat assignment.</p>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold">Riwayat Maintenance & Service</h2></div>
            <div v-if="asset.maintenance_records.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                <article v-for="record in asset.maintenance_records" :key="record.id" class="grid gap-2 px-5 py-4 sm:grid-cols-[10rem_1fr_12rem]">
                    <div><p class="font-medium">{{ record.number }}</p><p class="text-sm text-gray-500">{{ record.maintenance_type }} · {{ record.status }}</p></div>
                    <div class="text-sm"><p>{{ record.issue_description || record.work_summary || '-' }}</p><p v-if="record.work_summary" class="mt-1 text-gray-500">Hasil: {{ record.work_summary }}</p><p v-if="record.vendor" class="mt-1 text-gray-500">Vendor: {{ record.vendor.name }}</p></div>
                    <div class="text-sm text-gray-500"><p>Diminta {{ date(record.requested_at) }}</p><p v-if="record.completed_at">Selesai {{ date(record.completed_at) }}</p><p>Biaya {{ money(record.actual_cost) }}</p><p v-if="record.next_service_date">Service berikutnya {{ date(record.next_service_date) }}</p></div>
                </article>
            </div>
            <p v-else class="px-5 py-4 text-sm text-gray-500">Belum ada riwayat maintenance.</p>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold">Riwayat Audit / Stocktake</h2></div>
            <div v-if="asset.audit_items.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                <article v-for="item in asset.audit_items" :key="item.id" class="grid gap-2 px-5 py-4 sm:grid-cols-[10rem_1fr_12rem]">
                    <div><p class="font-medium">{{ item.session?.number ?? 'Audit' }}</p><p class="text-sm text-gray-500">{{ item.session?.status }}</p></div>
                    <div class="text-sm"><p>Hasil: {{ item.result }} · Kondisi: {{ item.observed_condition ?? '-' }}</p><p>Lokasi teramati: {{ item.observed_location?.name ?? '-' }}</p><p v-if="item.remarks" class="mt-1 text-gray-500">{{ item.remarks }}</p></div>
                    <p class="text-sm text-gray-500">{{ date(item.checked_at) }} · {{ item.checked_by?.name ?? '-' }}</p>
                </article>
            </div>
            <p v-else class="px-5 py-4 text-sm text-gray-500">Belum ada catatan audit aset.</p>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold">Riwayat Incident</h2></div>
            <div v-if="asset.incidents.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                <article v-for="incident in asset.incidents" :key="incident.id" class="space-y-3 px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-2"><div><p class="font-medium">{{ incident.number }} · {{ incident.incident_type }}</p><p class="text-sm text-gray-500">{{ date(incident.occurred_at) }} · {{ incident.status }} · dilaporkan {{ incident.reported_by?.name ?? '-' }}</p></div><Tag :value="incident.status" severity="secondary" /></div>
                    <p class="text-sm">{{ incident.description }}</p>
                    <p v-if="incident.resolution_notes" class="text-sm text-gray-500">Penyelesaian ({{ incident.resolution_type ?? '-' }}): {{ incident.resolution_notes }}</p>
                    <div v-if="incident.events.length" class="border-l-2 border-purple-200 pl-4 dark:border-purple-900"><p v-for="(event, index) in incident.events" :key="`${incident.id}-${index}`" class="py-1 text-sm text-gray-500">{{ date(event.created_at) }} · {{ event.event_type }} · {{ event.notes || '-' }} ({{ event.performed_by?.name ?? '-' }})</p></div>
                </article>
            </div>
            <p v-else class="px-5 py-4 text-sm text-gray-500">Belum ada riwayat incident.</p>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold">Riwayat Transfer</h2></div>
            <div v-if="asset.transfer_histories.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                <article v-for="transfer in asset.transfer_histories" :key="transfer.id" class="grid gap-2 px-5 py-4 sm:grid-cols-[10rem_1fr_12rem]">
                    <div><p class="font-medium">{{ transfer.number }}</p><p class="text-sm text-gray-500">{{ date(transfer.effective_at) }}</p></div>
                    <div class="text-sm"><p>{{ snapshot(transfer.from_snapshot) }}</p><p class="text-purple-700 dark:text-purple-300">→ {{ snapshot(transfer.to_snapshot) }}</p><p class="mt-1 text-gray-500">{{ transfer.reason }}</p></div>
                    <p class="text-sm text-gray-500">Diproses {{ transfer.performed_by?.name ?? '-' }}</p>
                </article>
            </div>
            <p v-else class="px-5 py-4 text-sm text-gray-500">Belum ada riwayat transfer.</p>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold">Riwayat Disposal</h2></div>
            <div v-if="asset.disposals.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                <article v-for="disposal in asset.disposals" :key="disposal.id" class="grid gap-2 px-5 py-4 sm:grid-cols-[10rem_1fr_12rem]">
                    <div><p class="font-medium">{{ disposal.number }}</p><p class="text-sm text-gray-500">{{ disposal.disposal_type }} · {{ disposal.status }}</p></div>
                    <div class="text-sm"><p>{{ disposal.reason }}</p><p v-if="disposal.decision_notes" class="mt-1 text-gray-500">Keputusan: {{ disposal.decision_notes }}</p><p class="mt-1 text-gray-500">Nilai: {{ money(disposal.amount) }}</p></div>
                    <div class="text-sm text-gray-500"><p>Tanggal {{ date(disposal.disposal_date) }}</p><p>Diminta {{ disposal.requested_by?.name ?? '-' }}</p><p v-if="disposal.decided_by">Diputuskan {{ disposal.decided_by.name }}</p></div>
                </article>
            </div>
            <p v-else class="px-5 py-4 text-sm text-gray-500">Belum ada riwayat disposal.</p>
        </section>
    </div>
</template>
