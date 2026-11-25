<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import CrudTable from '@/components/crud/CrudTable.vue'
import AppPagination from '@/components/common/AppPagination.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import DataTableToolbar from '@/components/common/DataTableToolbar.vue'
import ProcurementPageHeader from '@/components/procurement/ProcurementPageHeader.vue'

defineOptions({ layout: AppLayout })

interface ReceiptHistory {
    id: string
    number: string
    status: 'DRAFT' | 'POSTED'
    received_at: string
    posted_at: string | null
    quantity_received: number
    quantity_accepted: number
    quantity_rejected: number
}

interface ReceiptSource {
    key: string
    source_type: 'PURCHASE_ORDER' | 'PURCHASE_REQUEST'
    source_id: string
    source_number: string
    purchase_request_number?: string | null
    vendor_id?: string | null
    vendor?: string | null
    company?: string | null
    department?: string | null
    queue_status: 'WAITING_RECEIPT' | 'PARTIALLY_RECEIVED' | 'RECEIVED'
    items_count: number
    open_items_count: number
    received_quantity: number
    outstanding_quantity: number
    receipts: ReceiptHistory[]
    timeline: Array<{
        label: string
        at: string | null
        status: 'done' | 'current' | 'waiting'
    }>
}

const props = defineProps<{
    queue: ReceiptSource[]
    permissions: {
        create: boolean
        update: boolean
        post: boolean
        delete: boolean
    }
}>()

const confirm = useConfirm()
const historyVisible = ref(false)
const selectedSource = ref<ReceiptSource | null>(null)
const search = ref('')
const allFilterValue = 'ALL'
const sourceTypeFilter = ref(allFilterValue)
const statusFilter = ref(allFilterValue)
const perPage = ref(15)
const currentPage = ref(1)

const columns = [
    { key: 'source_type', label: 'Jenis Sumber' },
    { key: 'source_number', label: 'Nomor Dokumen' },
    { key: 'purchase_request_number', label: 'Purchase Request' },
    { key: 'vendor', label: 'Vendor' },
    { key: 'items_count', label: 'Jumlah Item' },
    { key: 'received_quantity', label: 'Diterima Baik' },
    { key: 'outstanding_quantity', label: 'Sisa Kuantitas' },
    { key: 'queue_status', label: 'Status' },
]

const sourceTypeOptions = [
    { label: 'Semua Jenis Sumber', value: allFilterValue },
    { label: 'Purchase Order', value: 'PURCHASE_ORDER' },
    { label: 'Purchase Request tanpa PO', value: 'PURCHASE_REQUEST' },
]

const queueStatusOptions = [
    { label: 'Semua Status', value: allFilterValue },
    { label: 'Menunggu Penerimaan', value: 'WAITING_RECEIPT' },
    { label: 'Diterima Sebagian', value: 'PARTIALLY_RECEIVED' },
    { label: 'Diterima', value: 'RECEIVED' },
]

const filteredQueue = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('id-ID')

    return props.queue.filter((source) => {
        const matchesSearch = !query || [
            source.source_number,
            source.purchase_request_number,
            source.vendor,
            source.company,
            source.department,
        ].some((value) => value?.toLocaleLowerCase('id-ID').includes(query))
        const matchesType = sourceTypeFilter.value === allFilterValue || source.source_type === sourceTypeFilter.value
        const matchesStatus = statusFilter.value === allFilterValue || source.queue_status === statusFilter.value

        return matchesSearch && matchesType && matchesStatus
    })
})

const hasActiveFilters = computed(() => Boolean(
    search.value.trim() || sourceTypeFilter.value !== allFilterValue || statusFilter.value !== allFilterValue,
))

const pageCount = computed(() => Math.max(1, Math.ceil(filteredQueue.value.length / perPage.value)))
const paginationMeta = computed(() => ({ total: filteredQueue.value.length }))
const paginatedQueue = computed(() => {
    const startIndex = (currentPage.value - 1) * perPage.value

    return filteredQueue.value.slice(startIndex, startIndex + perPage.value)
})

watch([search, sourceTypeFilter, statusFilter, perPage], () => {
    currentPage.value = 1
})

function resetQueueFilters() {
    search.value = ''
    sourceTypeFilter.value = allFilterValue
    statusFilter.value = allFilterValue
}

function createUrl(source: ReceiptSource): string {
    const parameters = new URLSearchParams({
        source_type: source.source_type,
        source_id: source.source_id,
    })
    if (source.vendor_id) {
        parameters.set('vendor_id', source.vendor_id)
    }

    return `${route('transaction.procurement.goods-receipts.create')}?${parameters.toString()}`
}

function showHistory(source: ReceiptSource) {
    selectedSource.value = source
    historyVisible.value = true
}

function openReceipt(receipt: ReceiptHistory) {
    router.get(route('transaction.procurement.goods-receipts.show', receipt.id))
}

function printReceipt(receipt: ReceiptHistory) {
    const url = new URL(route('transaction.procurement.goods-receipts.show', receipt.id), window.location.origin)
    url.searchParams.set('print', '1')
    url.searchParams.set('closeAfterPrint', '1')
    window.open(url.toString(), '_blank', 'noopener')
}

function editReceipt(receipt: ReceiptHistory) {
    router.get(route('transaction.procurement.goods-receipts.edit', receipt.id))
}

function postReceipt(receipt: ReceiptHistory) {
    confirm.require({
        message: `Posting ${receipt.number} akan mengunci data penerimaan dan memperbarui progres sumber. Lanjutkan?`,
        header: 'Posting Goods Receipt',
        icon: 'pi pi-check-circle',
        rejectLabel: 'Periksa Lagi',
        acceptLabel: 'Posting',
        accept: () => router.post(route('transaction.procurement.goods-receipts.submit', receipt.id), {}, { preserveScroll: true }),
    })
}

function deleteReceipt(receipt: ReceiptHistory) {
    confirm.require({
        message: `Hapus Draft Goods Receipt ${receipt.number}?`,
        header: 'Hapus Draft GR',
        icon: 'pi pi-trash',
        rejectLabel: 'Kembali',
        acceptLabel: 'Hapus',
        accept: () => router.delete(route('transaction.procurement.goods-receipts.destroy', receipt.id), { preserveScroll: true }),
    })
}

function sourceTypeLabel(sourceType: string): string {
    return sourceType === 'PURCHASE_ORDER' ? 'Purchase Order' : 'Purchase Request tanpa PO'
}

function statusLabel(status: ReceiptSource['queue_status']): string {
    if (status === 'RECEIVED') {
        return 'Diterima'
    }

    return status === 'PARTIALLY_RECEIVED' ? 'Diterima Sebagian' : 'Menunggu Penerimaan'
}

function statusSeverity(status: ReceiptSource['queue_status']): 'success' | 'warn' | 'info' {
    if (status === 'RECEIVED') {
        return 'success'
    }

    return status === 'PARTIALLY_RECEIVED' ? 'warn' : 'info'
}

function date(value: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value))
}

function dateTime(value: string | null): string {
    if (!value) {
        return 'Menunggu'
    }

    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <ConfirmDialog />
        <Dialog v-model:visible="historyVisible" modal header="Riwayat Penerimaan" :style="{ width: 'min(48rem, 94vw)' }">
            <div v-if="selectedSource" class="space-y-4">
                <div class="rounded-lg bg-gray-50 p-4 text-sm dark:bg-white/[0.03]">
                    <div class="font-semibold text-gray-900 dark:text-white">{{ selectedSource.source_number }}</div>
                    <div class="mt-1 text-gray-500 dark:text-gray-400">{{ sourceTypeLabel(selectedSource.source_type) }} · {{ selectedSource.vendor ?? 'Tidak ditentukan di PR' }}</div>
                </div>
                <section aria-label="Alur dokumen" class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Alur dokumen</h3>
                    <ol class="mt-3 space-y-3">
                        <li v-for="(step, index) in selectedSource.timeline" :key="`${step.label}-${index}`" class="flex items-start gap-3">
                            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :class="step.status === 'done' ? 'bg-emerald-500' : 'bg-amber-500'" />
                            <div class="flex min-w-0 flex-1 items-center justify-between gap-3 text-sm">
                                <span :class="step.status === 'current' ? 'font-semibold text-amber-700 dark:text-amber-400' : 'text-gray-700 dark:text-gray-300'">{{ step.label }}</span>
                                <time v-if="step.at" class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ dateTime(step.at) }}</time>
                                <span v-else class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ step.status === 'done' ? 'Selesai' : 'Menunggu' }}</span>
                            </div>
                        </li>
                    </ol>
                </section>
                <p v-if="selectedSource.receipts.length === 0" class="py-6 text-center text-sm text-gray-500">Belum ada dokumen GR untuk sumber ini.</p>
                <article v-for="receipt in selectedSource.receipts" :key="receipt.id" class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <button class="font-semibold text-brand-600 hover:underline dark:text-brand-400" @click="openReceipt(receipt)">{{ receipt.number }}</button>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tanggal diterima: {{ date(receipt.received_at) }}</div>
                        </div>
                        <Tag :value="receipt.status === 'POSTED' ? 'Posted' : 'Draft'" :severity="receipt.status === 'POSTED' ? 'success' : 'warn'" />
                    </div>
                    <div class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                        <div><span class="text-gray-500">Kuantitas diterima</span><div class="font-medium">{{ Number(receipt.quantity_received).toLocaleString('id-ID') }}</div></div>
                        <div><span class="text-gray-500">Diterima baik</span><div class="font-medium text-emerald-700 dark:text-emerald-400">{{ Number(receipt.quantity_accepted).toLocaleString('id-ID') }}</div></div>
                        <div><span class="text-gray-500">Ditolak</span><div class="font-medium text-red-700 dark:text-red-400">{{ Number(receipt.quantity_rejected).toLocaleString('id-ID') }}</div></div>
                    </div>
                    <div v-if="receipt.status === 'DRAFT'" class="mt-4 flex justify-end gap-1">
                        <Button icon="pi pi-print" severity="secondary" text rounded aria-label="Cetak GR" v-tooltip.top="'Cetak'" @click="printReceipt(receipt)" />
                        <Button icon="pi pi-eye" severity="secondary" text rounded aria-label="Lihat GR" v-tooltip.top="'Lihat'" @click="openReceipt(receipt)" />
                        <Button v-if="permissions.update" icon="pi pi-pencil" severity="secondary" text rounded aria-label="Edit Draft GR" v-tooltip.top="'Edit'" @click="editReceipt(receipt)" />
                        <Button v-if="permissions.post" icon="pi pi-check" severity="success" text rounded aria-label="Posting GR" v-tooltip.top="'Posting'" @click="postReceipt(receipt)" />
                        <Button v-if="permissions.delete" icon="pi pi-trash" severity="danger" text rounded aria-label="Hapus Draft GR" v-tooltip.top="'Hapus'" @click="deleteReceipt(receipt)" />
                    </div>
                    <div v-else class="mt-4 flex justify-end">
                        <Button icon="pi pi-print" severity="secondary" text rounded aria-label="Cetak GR" v-tooltip.top="'Cetak'" @click="printReceipt(receipt)" />
                        <Button icon="pi pi-eye" severity="secondary" text rounded aria-label="Lihat GR" v-tooltip.top="'Lihat'" @click="openReceipt(receipt)" />
                    </div>
                </article>
            </div>
        </Dialog>

        <ProcurementPageHeader
            title="Goods Receipt"
            section="Transactions"
            description="Catat dan pantau penerimaan barang berdasarkan dokumen sumber."
        />

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <DataTableToolbar
                v-model="search"
                :total="filteredQueue.length"
                total-label="Sumber Penerimaan"
                placeholder="Cari sumber, PR, atau vendor..."
            >
                <AppSelect
                    v-model="sourceTypeFilter"
                    :options="sourceTypeOptions"
                    class="w-full sm:w-64"
                    aria-label="Filter jenis sumber"
                />
                <AppSelect
                    v-model="statusFilter"
                    :options="queueStatusOptions"
                    class="w-full sm:w-56"
                    aria-label="Filter status penerimaan"
                />
                <Button
                    icon="pi pi-refresh"
                    severity="secondary"
                    outlined
                    aria-label="Reset filter"
                    title="Reset filter"
                    :disabled="!hasActiveFilters"
                    @click="resetQueueFilters"
                />
            </DataTableToolbar>
            <CrudTable
                row-key="key"
                :columns="columns"
                :rows="paginatedQueue"
                :empty-message="hasActiveFilters ? 'Tidak ada sumber penerimaan yang cocok dengan filter.' : 'Belum ada sumber penerimaan.'"
            >
                <template #cell-source_type="{ row }">{{ sourceTypeLabel(row.source_type) }}</template>
                <template #cell-source_number="{ row }"><span class="font-medium text-gray-900 dark:text-white">{{ row.source_number }}</span></template>
                <template #cell-purchase_request_number="{ row }">{{ row.purchase_request_number ?? '-' }}</template>
                <template #cell-vendor="{ row }">{{ row.vendor ?? 'Tidak ditentukan di PR' }}</template>
                <template #cell-items_count="{ row }">{{ row.items_count }}</template>
                <template #cell-received_quantity="{ row }">{{ Number(row.received_quantity).toLocaleString('id-ID') }}</template>
                <template #cell-outstanding_quantity="{ row }">{{ Number(row.outstanding_quantity).toLocaleString('id-ID') }}</template>
                <template #cell-queue_status="{ row }"><Tag :value="statusLabel(row.queue_status)" :severity="statusSeverity(row.queue_status)" /></template>
                <template #actions="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <Button v-if="permissions.create && row.queue_status !== 'RECEIVED'" icon="pi pi-plus" severity="secondary" text rounded aria-label="Buat Draft GR" v-tooltip.top="'Catat Penerimaan'" @click="router.get(createUrl(row))" />
                        <Button icon="pi pi-eye" severity="secondary" text rounded aria-label="Lihat Riwayat GR" v-tooltip.top="'Lihat Riwayat GR'" @click="showHistory(row)" />
                    </div>
                </template>
            </CrudTable>
            <AppPagination
                v-if="filteredQueue.length > 0"
                v-model:per-page="perPage"
                v-model:page="currentPage"
                :meta="paginationMeta"
                :client-page-count="pageCount"
            />
        </section>
    </div>
</template>
