<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Tag from 'primevue/tag'
import ProcurementPageHeader from '@/components/procurement/ProcurementPageHeader.vue'
import { useConfirm } from 'primevue/useconfirm'

defineOptions({ layout: AppLayout })

interface SourceItem {
    item_type: string
    description?: string | null
    quantity: string | number
    product?: { code: string; name: string } | null
    service_type?: { code: string; name: string } | null
    uom?: { code: string; name: string } | null
}

interface GoodsReceiptItem {
    id: string
    quantity_received: string | number
    quantity_accepted: string | number
    quantity_rejected: string | number
    remarks?: string | null
    purchase_order_item?: SourceItem | null
    purchase_request_item?: SourceItem | null
}

interface GoodsReceipt {
    id: string
    number: string
    source_type: 'PURCHASE_ORDER' | 'PURCHASE_REQUEST'
    status: 'DRAFT' | 'POSTED'
    received_at: string
    delivery_note_number?: string | null
    remarks?: string | null
    posted_at?: string | null
    purchase_order?: { number: string; purchase_request?: { number: string } | null } | null
    purchase_request?: { number: string } | null
    company?: { name: string } | null
    department?: { name: string } | null
    warehouse_location?: { name: string } | null
    storage_location?: { name: string } | null
    vendor?: { code: string; name: string } | null
    created_by?: { name: string } | null
    posted_by?: { name: string } | null
    items: GoodsReceiptItem[]
    can_edit: boolean
    can_post: boolean
    can_delete: boolean
}

const props = defineProps<{ goodsReceipt: GoodsReceipt }>()
const confirm = useConfirm()
const printMode = ref(false)
let closeTabAfterPrint = false

const sourceNumber = props.goodsReceipt.purchase_order?.number
    ?? props.goodsReceipt.purchase_request?.number
    ?? '-'
const sourceTypeLabel = props.goodsReceipt.source_type === 'PURCHASE_ORDER'
    ? 'Purchase Order'
    : 'Purchase Request tanpa PO'
const totalAccepted = props.goodsReceipt.items.reduce((total, item) => total + Number(item.quantity_accepted), 0)
const totalRejected = props.goodsReceipt.items.reduce((total, item) => total + Number(item.quantity_rejected), 0)

function sourceItem(item: GoodsReceiptItem): SourceItem | null {
    return item.purchase_order_item ?? item.purchase_request_item ?? null
}

function itemName(item: GoodsReceiptItem): string {
    const source = sourceItem(item)
    return source?.product?.name ?? source?.service_type?.name ?? source?.description ?? 'Item'
}

function itemCode(item: GoodsReceiptItem): string {
    const source = sourceItem(item)
    return source?.product?.code ?? source?.service_type?.code ?? '-'
}

function unit(item: GoodsReceiptItem): string {
    const source = sourceItem(item)
    return source?.uom?.name ?? source?.uom?.code ?? '-'
}

function date(value: string) {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value))
}

function dateTime(value?: string | null) {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '-'
}

function closePrintTab() {
    if (!closeTabAfterPrint) {
        return
    }

    window.close()
    window.setTimeout(() => {
        if (!window.closed) {
            window.location.replace('about:blank')
        }
    }, 100)
}

onMounted(() => {
    const printParameters = new URLSearchParams(window.location.search)
    printMode.value = printParameters.get('print') === '1'
    closeTabAfterPrint = printParameters.get('closeAfterPrint') === '1'

    if (printMode.value) {
        if (closeTabAfterPrint) {
            window.addEventListener('afterprint', closePrintTab, { once: true })
        }

        window.setTimeout(() => window.print(), 300)
    }
})

function printPage() {
    window.print()
}

function postReceipt() {
    confirm.require({
        message: `Posting ${props.goodsReceipt.number} akan mengunci data dan memperbarui status/sisa sumber penerimaan.`,
        header: 'Posting Goods Receipt',
        icon: 'pi pi-check-circle',
        rejectLabel: 'Periksa Lagi',
        acceptLabel: 'Posting',
        accept: () => router.post(route('transaction.procurement.goods-receipts.submit', props.goodsReceipt.id), {}, { preserveScroll: true }),
    })
}

function deleteReceipt() {
    confirm.require({
        message: `Hapus Draft Goods Receipt ${props.goodsReceipt.number}?`,
        header: 'Hapus Draft GR',
        icon: 'pi pi-trash',
        rejectLabel: 'Kembali',
        acceptLabel: 'Hapus',
        accept: () => router.delete(route('transaction.procurement.goods-receipts.destroy', props.goodsReceipt.id)),
    })
}
</script>

<template>
    <div class="procurement-ui goods-receipt-page space-y-6">
        <div class="print-document">
            <article class="gr-paper">
                <div v-if="goodsReceipt.status === 'DRAFT'" class="gr-draft-watermark">DRAFT</div>
                <header class="gr-document-header">
                    <div class="gr-company">{{ goodsReceipt.company?.name ?? 'Citra Group' }}</div>
                    <div class="gr-title">GOODS RECEIPT</div>
                    <div class="gr-number">{{ goodsReceipt.number }}</div>
                    <div class="gr-status" :class="goodsReceipt.status === 'DRAFT' ? 'gr-status-draft' : 'gr-status-posted'">{{ goodsReceipt.status === 'DRAFT' ? 'DRAFT — BELUM DIPOSTING' : 'POSTED / FINAL' }}</div>
                </header>

                <table class="gr-meta-table">
                    <tbody>
                        <tr><th>Nomor GR</th><td>{{ goodsReceipt.number }}</td><th>Tanggal diterima</th><td>{{ date(goodsReceipt.received_at) }}</td></tr>
                        <tr><th>Jenis sumber</th><td>{{ sourceTypeLabel }}</td><th>Nomor sumber</th><td>{{ sourceNumber }}</td></tr>
                        <tr v-if="goodsReceipt.purchase_order?.purchase_request"><th>Purchase Request</th><td>{{ goodsReceipt.purchase_order.purchase_request.number }}</td><th>Nomor surat jalan</th><td>{{ goodsReceipt.delivery_note_number ?? '-' }}</td></tr>
                        <tr v-else><th>Nomor surat jalan</th><td>{{ goodsReceipt.delivery_note_number ?? '-' }}</td><th>Vendor</th><td>{{ goodsReceipt.vendor?.name ?? 'Tidak ditentukan di PR' }}</td></tr>
                        <tr v-if="goodsReceipt.purchase_order?.purchase_request"><th>Vendor</th><td>{{ goodsReceipt.vendor?.name ?? 'Tidak ditentukan di PR' }}</td><th>Departemen</th><td>{{ goodsReceipt.department?.name ?? '-' }}</td></tr>
                        <tr v-else><th>Perusahaan</th><td>{{ goodsReceipt.company?.name ?? '-' }}</td><th>Departemen</th><td>{{ goodsReceipt.department?.name ?? '-' }}</td></tr>
                        <tr v-if="goodsReceipt.purchase_order?.purchase_request"><th>Perusahaan</th><td colspan="3">{{ goodsReceipt.company?.name ?? '-' }}</td></tr>
                    </tbody>
                </table>

                <table class="gr-items-table">
                    <thead><tr><th>No.</th><th>Item / Deskripsi</th><th>Satuan</th><th>Diterima</th><th>Diterima baik</th><th>Ditolak</th><th>Catatan</th></tr></thead>
                    <tbody>
                        <tr v-for="(item, index) in goodsReceipt.items" :key="item.id">
                            <td class="gr-center">{{ index + 1 }}</td>
                            <td><strong>{{ itemName(item) }}</strong><small>{{ itemCode(item) }}</small></td>
                            <td class="gr-center">{{ unit(item) }}</td>
                            <td class="gr-number-cell">{{ Number(item.quantity_received).toLocaleString('id-ID') }}</td>
                            <td class="gr-number-cell">{{ Number(item.quantity_accepted).toLocaleString('id-ID') }}</td>
                            <td class="gr-number-cell">{{ Number(item.quantity_rejected).toLocaleString('id-ID') }}</td>
                            <td>{{ item.remarks || '-' }}</td>
                        </tr>
                    </tbody>
                </table>

                <section class="gr-remarks"><strong>Catatan penerimaan</strong><p>{{ goodsReceipt.remarks || '-' }}</p></section>
                <section class="gr-signatures">
                    <div><strong>Disiapkan oleh</strong><span>{{ goodsReceipt.created_by?.name ?? '-' }}</span><small>Petugas penerimaan</small></div>
                    <div><strong>Diperiksa / Diterima oleh</strong><span>{{ goodsReceipt.status === 'POSTED' ? goodsReceipt.posted_by?.name ?? '-' : 'Belum diposting' }}</span><small>{{ goodsReceipt.posted_at ? `Posting ${dateTime(goodsReceipt.posted_at)}` : 'Menunggu posting' }}</small></div>
                    <div><strong>Perwakilan vendor</strong><span>&nbsp;</span><small>Nama dan tanda tangan</small></div>
                </section>
                <footer class="gr-footer">{{ goodsReceipt.company?.name ?? 'Citra Group' }} · Dokumen Goods Receipt · {{ goodsReceipt.number }}</footer>
            </article>
        </div>

        <div class="screen-only space-y-6">
        <ConfirmDialog />
        <ProcurementPageHeader
            class="screen-only"
            :title="goodsReceipt.number"
            section="Goods Receipt"
            description="Rincian dokumen penerimaan barang."
        >
            <template #badge>
                <Tag :value="goodsReceipt.status === 'POSTED' ? 'Posted' : 'Draft'" :severity="goodsReceipt.status === 'POSTED' ? 'success' : 'warn'" />
            </template>
            <template #actions>
            <div class="flex flex-wrap gap-2">
                <Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.get(route('transaction.procurement.goods-receipts.index'))" />
                <Button label="Print" icon="pi pi-print" severity="secondary" outlined @click="printPage" />
                <Button v-if="goodsReceipt.can_edit" label="Edit Draft" icon="pi pi-pencil" severity="secondary" outlined @click="router.get(route('transaction.procurement.goods-receipts.edit', goodsReceipt.id))" />
                <Button v-if="goodsReceipt.can_post" label="Posting GR" icon="pi pi-check-circle" @click="postReceipt" />
                <Button v-if="goodsReceipt.can_delete" label="Hapus Draft" icon="pi pi-trash" severity="danger" outlined @click="deleteReceipt" />
            </div>
            </template>
        </ProcurementPageHeader>

        <div class="grid items-start gap-5 2xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0 space-y-5">
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                        <h2 class="font-semibold text-gray-900 dark:text-white">Informasi Penerimaan</h2>
                    </div>
                    <dl class="grid gap-x-8 gap-y-5 px-5 py-5 sm:grid-cols-2 sm:px-6">
                        <div><dt class="text-sm text-gray-500">Sumber</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ sourceTypeLabel }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Nomor Sumber</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ sourceNumber }}</dd></div>
                        <div v-if="goodsReceipt.purchase_order?.purchase_request"><dt class="text-sm text-gray-500">Purchase Request</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ goodsReceipt.purchase_order.purchase_request.number }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Vendor</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ goodsReceipt.vendor?.name ?? 'Tidak ditentukan di PR' }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Tanggal Diterima</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ date(goodsReceipt.received_at) }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Surat Jalan / Bukti Pekerjaan</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ goodsReceipt.delivery_note_number ?? '-' }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Perusahaan</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ goodsReceipt.company?.name ?? '-' }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Departemen</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ goodsReceipt.department?.name ?? '-' }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Gudang Tujuan</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ goodsReceipt.warehouse_location?.name ?? '-' }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Sublokasi</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ goodsReceipt.storage_location?.name ?? 'Langsung di gudang' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-sm text-gray-500">Catatan</dt><dd class="mt-1 whitespace-pre-line text-gray-800 dark:text-gray-200">{{ goodsReceipt.remarks || 'Tidak ada catatan.' }}</dd></div>
                    </dl>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                        <h2 class="font-semibold text-gray-900 dark:text-white">Rincian Item Diterima</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-sm">
                            <thead class="bg-gray-50 text-gray-600 dark:bg-white/[0.03] dark:text-gray-300"><tr><th class="px-5 py-3">Item</th><th class="px-5 py-3 text-right">Diterima</th><th class="px-5 py-3 text-right">Diterima Baik</th><th class="px-5 py-3 text-right">Ditolak</th><th class="px-5 py-3">Catatan</th></tr></thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="item in goodsReceipt.items" :key="item.id">
                                    <td class="px-5 py-4 align-top"><div class="font-medium text-gray-900 dark:text-white">{{ itemName(item) }}</div><div class="mt-1 text-xs text-gray-500">{{ itemCode(item) }} · {{ unit(item) }}</div></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right align-top tabular-nums">{{ Number(item.quantity_received).toLocaleString('id-ID') }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right align-top font-medium tabular-nums text-emerald-700 dark:text-emerald-400">{{ Number(item.quantity_accepted).toLocaleString('id-ID') }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right align-top tabular-nums" :class="Number(item.quantity_rejected) > 0 ? 'font-medium text-red-700 dark:text-red-400' : ''">{{ Number(item.quantity_rejected).toLocaleString('id-ID') }}</td>
                                    <td class="max-w-xs whitespace-normal px-5 py-4 align-top text-gray-600 dark:text-gray-300">{{ item.remarks || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Ringkasan Kuantitas</h2>
                    <div class="mt-4 flex justify-between gap-3 text-sm text-gray-600 dark:text-gray-300"><span>Total diterima baik</span><strong>{{ totalAccepted.toLocaleString('id-ID') }}</strong></div>
                    <div class="mt-3 flex justify-between gap-3 text-sm text-gray-600 dark:text-gray-300"><span>Total ditolak</span><strong>{{ totalRejected.toLocaleString('id-ID') }}</strong></div>
                </section>
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Riwayat Dokumen</h2>
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">Draft dibuat oleh {{ goodsReceipt.created_by?.name ?? '-' }}.</p>
                    <p v-if="goodsReceipt.posted_at" class="mt-2 text-sm text-gray-600 dark:text-gray-300">Diposting {{ dateTime(goodsReceipt.posted_at) }} oleh {{ goodsReceipt.posted_by?.name ?? '-' }}.</p>
                    <p v-else class="mt-2 text-sm text-amber-700 dark:text-amber-400">Draft belum memengaruhi saldo penerimaan.</p>
                </section>
            </aside>
        </div>
        </div>
    </div>
</template>

<style scoped>
.print-document { display: none; }

@media print {
    :global(body) { background: #fff !important; }
    :global(.layout-wrapper), :global(.layout-main-container), :global(.layout-main) { margin: 0 !important; padding: 0 !important; }
    :global(.layout-sidebar), :global(.layout-topbar), :global(.layout-footer), :global(.p-toast), :global(.p-dialog-mask) { display: none !important; }
    .goods-receipt-page { margin: 0 !important; padding: 0 !important; max-width: none !important; }
    .screen-only { display: none !important; }
    .print-document { display: block !important; }
    .gr-paper { position: relative; overflow: hidden; color: #171717; font: 9pt Arial, sans-serif; }
    .gr-document-header { position: relative; z-index: 1; text-align: center; border-bottom: 1px solid #555; padding-bottom: 10pt; margin-bottom: 12pt; }
    .gr-company { font-size: 10pt; font-weight: bold; letter-spacing: .08em; text-transform: uppercase; }
    .gr-title { margin-top: 8pt; font-size: 17pt; font-weight: bold; letter-spacing: .08em; }
    .gr-number { margin-top: 3pt; font-size: 10pt; font-weight: bold; }
    .gr-status { margin-top: 5pt; font-size: 8pt; font-weight: bold; }
    .gr-status-draft { color: #a16207; }
    .gr-status-posted { color: #166534; }
    .gr-draft-watermark { position: fixed; z-index: 0; top: 42%; left: 20%; color: rgba(120, 53, 15, .12); font-size: 70pt; font-weight: bold; transform: rotate(-32deg); }
    .gr-meta-table, .gr-items-table { position: relative; z-index: 1; width: 100%; border-collapse: collapse; table-layout: fixed; }
    .gr-meta-table { margin-bottom: 12pt; font-size: 8pt; }
    .gr-meta-table th, .gr-meta-table td, .gr-items-table th, .gr-items-table td { border: 1px solid #444; padding: 5pt 4pt; vertical-align: top; overflow-wrap: anywhere; }
    .gr-meta-table th { width: 16%; background: #f0f0f0; text-align: left; }
    .gr-meta-table td { width: 34%; }
    .gr-items-table { font-size: 7.5pt; }
    .gr-items-table thead { display: table-header-group; background: #eee; }
    .gr-items-table th { text-align: left; }
    .gr-items-table th:nth-child(1) { width: 5%; }
    .gr-items-table th:nth-child(2) { width: 28%; }
    .gr-items-table th:nth-child(3) { width: 9%; }
    .gr-items-table th:nth-child(4), .gr-items-table th:nth-child(5), .gr-items-table th:nth-child(6) { width: 11%; }
    .gr-items-table th:nth-child(7) { width: 25%; }
    .gr-items-table td small { display: block; margin-top: 2pt; color: #555; }
    .gr-items-table tfoot { display: table-footer-group; background: #f0f0f0; }
    .gr-center { text-align: center; }
    .gr-number-cell { text-align: right; font-variant-numeric: tabular-nums; }
    .gr-remarks { position: relative; z-index: 1; margin-top: 12pt; min-height: 34pt; border: 1px solid #777; padding: 6pt; font-size: 8pt; }
    .gr-remarks p { margin: 4pt 0 0; white-space: pre-wrap; }
    .gr-signatures { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(3, 1fr); margin-top: 22pt; page-break-inside: avoid; }
    .gr-signatures div { min-height: 72pt; border: 1px solid #444; padding: 5pt; text-align: center; font-size: 8pt; }
    .gr-signatures span { display: block; margin-top: 38pt; border-bottom: 1px solid #555; padding-bottom: 2pt; }
    .gr-signatures small { display: block; margin-top: 3pt; color: #555; }
    .gr-footer { position: relative; z-index: 1; margin-top: 18pt; padding-top: 5pt; border-top: 1px solid #aaa; font-size: 7pt; color: #555; }
    @page { size: A4 portrait; margin: 14mm; }
}
</style>
