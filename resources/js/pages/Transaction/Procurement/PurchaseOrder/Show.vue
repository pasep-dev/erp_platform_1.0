<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import ProcurementPageHeader from '@/components/procurement/ProcurementPageHeader.vue'

defineOptions({ layout: AppLayout })

interface PriceHistory {
    previous_unit_price: string | number | null
    new_unit_price: string | number
    reason: string
    created_at: string
    changed_by?: { name: string }
}
interface PurchaseOrderItem {
    id: string
    item_type: string
    description?: string | null
    quantity: string | number
    pr_estimated_unit_price: string | number | null
    unit_price: string | number | null
    tax_included: boolean
    product?: { code: string; name: string } | null
    service_type?: { code: string; name: string } | null
    asset_id?: string | null
    uom?: { code: string; name: string } | null
    price_histories: PriceHistory[]
}
interface PurchaseOrder {
    id: string
    number: string
    status: string
    created_at: string
    submitted_at?: string | null
    cancellation_reason?: string | null
    purchase_request?: { number: string; purpose: string; request_date: string }
    company?: { name: string; code: string }
    department?: { name: string; code: string }
    vendor?: { code: string; name: string; address?: string; email?: string; phone?: string }
    submitted_by?: { name: string } | null
    items: PurchaseOrderItem[]
    can_edit_prices: boolean
    can_submit: boolean
    can_cancel: boolean
}
const props = defineProps<{ purchaseOrder: PurchaseOrder }>()
const confirm = useConfirm()
const cancelDialog = ref(false)
const cancelReason = ref('')
const processingCancel = ref(false)

const total = computed(() => props.purchaseOrder.items.reduce((sum, item) => sum + Number(item.quantity) * Number(item.unit_price ?? 0), 0))
const printMode = ref(false)
let closeTabAfterPrint = false
const money = (value: number | string | null) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value ?? 0))
const date = (value?: string | null) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value)) : '-'
const dateTime = (value?: string | null) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '-'
const statusLabel = (status: string) => status === 'SUBMITTED' ? 'SUBMITTED / ANTRIAN GR' : status === 'PARTIALLY_RECEIVED' ? 'PARTIALLY RECEIVED' : status
const statusSeverity = (status: string) => ['SUBMITTED', 'RECEIVED'].includes(status) ? 'success' : status === 'CANCELLED' ? 'danger' : 'warn'

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

    if (printMode.value && props.purchaseOrder.status !== 'CANCELLED') {
        if (closeTabAfterPrint) {
            window.addEventListener('afterprint', closePrintTab, { once: true })
        }

        window.setTimeout(() => window.print(), 300)
    }
})

function printPage() {
    if (props.purchaseOrder.status === 'CANCELLED') {
        return
    }

    window.print()
}

function submitOrder() {
    confirm.require({
        message: `Kirim PO ${props.purchaseOrder.number}? Setelah dikirim, PO akan masuk ke antrian Goods Receipt dan harga terkunci.`,
        header: 'Kirim Purchase Order',
        icon: 'pi pi-send',
        rejectLabel: 'Kembali',
        acceptLabel: 'Kirim PO',
        accept: () => router.post(route('transaction.procurement.purchase-orders.submit', props.purchaseOrder.id), {}, { preserveScroll: true }),
    })
}

function cancelOrder() {
    if (!cancelReason.value.trim()) return
    processingCancel.value = true
    router.post(route('transaction.procurement.purchase-orders.cancel', props.purchaseOrder.id), { reason: cancelReason.value }, {
        preserveScroll: true,
        onFinish: () => { processingCancel.value = false; cancelDialog.value = false },
    })
}
</script>

<template>
    <div class="procurement-ui purchase-order-page space-y-6" :class="{ 'po-cancelled': purchaseOrder.status === 'CANCELLED' }">
        <ConfirmDialog />
        <Dialog v-model:visible="cancelDialog" modal header="Batalkan Purchase Order" :style="{ width: 'min(32rem, 92vw)' }">
            <div class="space-y-3">
                <p class="text-sm text-gray-600">Alasan pembatalan wajib dicatat. PR sumber tetap Approved.</p>
                <Textarea v-model="cancelReason" rows="4" auto-resize class="w-full" placeholder="Alasan pembatalan" />
            </div>
            <template #footer>
                <Button label="Kembali" severity="secondary" text @click="cancelDialog = false" />
                <Button label="Batalkan PO" severity="danger" :disabled="!cancelReason.trim()" :loading="processingCancel" @click="cancelOrder" />
            </template>
        </Dialog>

        <ProcurementPageHeader
            class="screen-only"
            :title="purchaseOrder.number"
            section="Purchase Order"
            :description="`Pesanan dari Purchase Request ${purchaseOrder.purchase_request?.number ?? '-'}.`"
        >
            <template #badge>
                <Tag :value="statusLabel(purchaseOrder.status)" :severity="severity(purchaseOrder.status)" />
            </template>
            <template #actions>
            <div class="flex flex-wrap gap-2">
                <Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.get(route('transaction.procurement.purchase-orders.index'))" />
                <Button v-if="purchaseOrder.can_edit_prices" label="Edit Harga" icon="pi pi-pencil" severity="secondary" outlined @click="router.get(route('transaction.procurement.purchase-orders.edit', purchaseOrder.id))" />
                <Button v-if="purchaseOrder.status !== 'CANCELLED'" label="Print" icon="pi pi-print" severity="secondary" outlined @click="printPage" />
                <Button v-if="purchaseOrder.can_submit" label="Kirim PO" icon="pi pi-send" @click="submitOrder" />
                <Button v-if="purchaseOrder.can_cancel" label="Batalkan" icon="pi pi-ban" severity="danger" outlined @click="cancelDialog = true" />
            </div>
            </template>
        </ProcurementPageHeader>

        <div class="screen-only grid items-start gap-5 2xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0 space-y-5">
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Informasi Purchase Order</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">PO ini dibuat dari PR {{ purchaseOrder.purchase_request?.number ?? '-' }}.</p>
                    </div>
                    <dl class="grid gap-x-8 gap-y-5 px-5 py-5 sm:grid-cols-2 sm:px-6">
                        <div><dt class="text-sm text-gray-500 dark:text-gray-400">Tanggal PO</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ date(purchaseOrder.created_at) }}</dd></div>
                        <div><dt class="text-sm text-gray-500 dark:text-gray-400">Tanggal PR</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ date(purchaseOrder.purchase_request?.request_date) }}</dd></div>
                        <div><dt class="text-sm text-gray-500 dark:text-gray-400">Perusahaan</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ purchaseOrder.company?.name ?? '-' }}</dd></div>
                        <div><dt class="text-sm text-gray-500 dark:text-gray-400">Departemen</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ purchaseOrder.department?.name ?? '-' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-sm text-gray-500 dark:text-gray-400">Keperluan PR</dt><dd class="mt-1 whitespace-pre-line text-gray-800 dark:text-gray-200">{{ purchaseOrder.purchase_request?.purpose ?? '-' }}</dd></div>
                    </dl>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Vendor</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Informasi vendor tujuan Purchase Order.</p>
                    </div>
                    <dl class="grid gap-x-8 gap-y-5 px-5 py-5 sm:grid-cols-2 sm:px-6">
                        <div><dt class="text-sm text-gray-500 dark:text-gray-400">Nama Vendor</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ purchaseOrder.vendor?.name ?? '-' }}</dd></div>
                        <div><dt class="text-sm text-gray-500 dark:text-gray-400">Kode Vendor</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ purchaseOrder.vendor?.code ?? '-' }}</dd></div>
                        <div><dt class="text-sm text-gray-500 dark:text-gray-400">Telepon</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ purchaseOrder.vendor?.phone ?? '-' }}</dd></div>
                        <div><dt class="text-sm text-gray-500 dark:text-gray-400">Email</dt><dd class="mt-1 text-gray-800 dark:text-gray-200">{{ purchaseOrder.vendor?.email ?? '-' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-sm text-gray-500 dark:text-gray-400">Alamat</dt><dd class="mt-1 whitespace-pre-line text-gray-800 dark:text-gray-200">{{ purchaseOrder.vendor?.address ?? '-' }}</dd></div>
                    </dl>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                        <div><h2 class="text-base font-semibold text-gray-900 dark:text-white">Rincian Item</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Harga estimasi PR dibandingkan dengan harga pada PO.</p></div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ purchaseOrder.items.length }} item</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[780px] table-fixed text-left text-sm">
                            <colgroup><col class="w-[36%]" /><col class="w-[14%]" /><col class="w-[18%]" /><col class="w-[16%]" /><col class="w-[16%]" /></colgroup>
                            <thead class="bg-gray-50 text-gray-600 dark:bg-white/[0.03] dark:text-gray-300"><tr><th class="px-5 py-3 font-semibold">Barang / Jasa</th><th class="px-5 py-3 text-right font-semibold">Kuantitas</th><th class="px-5 py-3 text-right font-semibold">Estimasi PR</th><th class="px-5 py-3 text-right font-semibold">Harga PO</th><th class="px-5 py-3 text-right font-semibold">Jumlah</th></tr></thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="(item, index) in purchaseOrder.items" :key="item.id">
                                    <td class="px-5 py-4 align-top"><div class="font-semibold text-gray-900 dark:text-white">{{ item.product?.name ?? item.service_type?.name ?? item.description ?? 'Item' }}</div><div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ item.product?.code ?? item.service_type?.code ?? item.description ?? `Item ${index + 1}` }}<span v-if="item.asset_id"> · Asset {{ item.asset_id }}</span></div><div v-if="item.description && item.description !== item.product?.name && item.description !== item.service_type?.name" class="mt-2 text-gray-600 dark:text-gray-300">{{ item.description }}</div><div class="mt-1 text-xs text-gray-500">Unit: {{ item.uom?.name ?? item.uom?.code ?? '-' }}</div></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right align-top tabular-nums">{{ Number(item.quantity).toLocaleString('id-ID') }} <span class="text-gray-500">{{ item.uom?.code ?? '' }}</span></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right align-top tabular-nums text-gray-600 dark:text-gray-300">{{ item.pr_estimated_unit_price === null ? 'Belum diestimasi' : money(item.pr_estimated_unit_price) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right align-top font-medium tabular-nums text-gray-900 dark:text-white">{{ item.unit_price === null ? 'Belum diisi' : money(item.unit_price) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right align-top font-semibold tabular-nums text-gray-900 dark:text-white">{{ money(Number(item.quantity) * Number(item.unit_price ?? 0)) }}</td>
                                </tr>
                                <tr v-if="purchaseOrder.items.length === 0"><td colspan="5" class="px-5 py-10 text-center text-gray-500">Belum ada item pada Purchase Order.</td></tr>
                            </tbody>
                            <tfoot><tr class="border-t border-gray-200 dark:border-gray-800"><td colspan="4" class="px-5 py-4 text-right font-semibold text-gray-700 dark:text-gray-200">Total PO</td><td class="whitespace-nowrap px-5 py-4 text-right font-bold tabular-nums text-gray-900 dark:text-white">{{ money(total) }}</td></tr></tfoot>
                        </table>
                    </div>
                </section>

                <section v-if="purchaseOrder.items.some(item => item.price_histories.length)" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="text-base font-semibold text-gray-900 dark:text-white">Histori Perubahan Harga</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Setiap perubahan harga dan alasan yang dicatat purchasing.</p></div>
                    <div class="space-y-4 p-5 sm:px-6">
                        <div v-for="item in purchaseOrder.items.filter(row => row.price_histories.length)" :key="item.id" class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <p class="font-medium text-gray-900 dark:text-white">{{ item.product?.name ?? item.service_type?.name ?? item.description ?? 'Item' }}</p>
                            <div v-for="(history, historyIndex) in item.price_histories" :key="historyIndex" class="mt-3 border-t border-gray-100 pt-3 text-sm dark:border-gray-800">
                                <div class="flex flex-wrap justify-between gap-2"><span class="text-gray-700 dark:text-gray-200">{{ history.previous_unit_price === null ? 'Belum diisi' : money(history.previous_unit_price) }} → <strong>{{ money(history.new_unit_price) }}</strong></span><span class="text-xs text-gray-500">{{ history.changed_by?.name ?? '-' }} · {{ dateTime(history.created_at) }}</span></div>
                                <p class="mt-1 text-gray-600 dark:text-gray-400">{{ history.reason }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3"><h2 class="font-semibold text-gray-900 dark:text-white">Status Purchase Order</h2><Tag :value="statusLabel(purchaseOrder.status)" :severity="statusSeverity(purchaseOrder.status)" /></div>
                    <p v-if="purchaseOrder.submitted_at" class="mt-4 text-sm text-gray-600 dark:text-gray-300">Dikirim {{ dateTime(purchaseOrder.submitted_at) }} oleh {{ purchaseOrder.submitted_by?.name ?? '-' }}.</p>
                    <div v-if="purchaseOrder.status === 'CANCELLED'" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950/30 dark:text-red-200"><strong>Alasan pembatalan</strong><p class="mt-1">{{ purchaseOrder.cancellation_reason }}</p></div>
                    <p v-if="!purchaseOrder.submitted_at && purchaseOrder.status !== 'CANCELLED'" class="mt-4 text-sm text-gray-600 dark:text-gray-300">PO masih menunggu dikirim oleh tim purchasing.</p>
                </section>
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Ringkasan Nilai</h2>
                    <div class="mt-4 flex items-center justify-between gap-3 text-sm text-gray-600 dark:text-gray-300"><span>Total item</span><span>{{ purchaseOrder.items.length }}</span></div>
                    <div class="mt-3 flex items-start justify-between gap-3 border-t border-gray-200 pt-3 dark:border-gray-800"><span class="text-sm font-medium text-gray-700 dark:text-gray-200">Total PO</span><strong class="text-right text-lg tabular-nums text-gray-900 dark:text-white">{{ money(total) }}</strong></div>
                </section>
            </aside>
        </div>

        <section class="po-paper print-document rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="po-heading border-b border-gray-200 pb-4 text-center dark:border-gray-700">
                <h2 class="text-lg font-bold tracking-wide">PURCHASE ORDER</h2>
                <p class="mt-1 text-sm font-semibold">{{ purchaseOrder.number }}</p>
                <Tag class="screen-only mt-3" :value="statusLabel(purchaseOrder.status)" :severity="statusSeverity(purchaseOrder.status)" />
            </div>

            <div class="po-metadata mt-5 grid gap-4 sm:grid-cols-2">
                <div class="space-y-2 text-sm">
                    <div><span class="text-gray-500">Purchase Request:</span> <strong>{{ purchaseOrder.purchase_request?.number }}</strong></div>
                    <div><span class="text-gray-500">Tanggal PR:</span> {{ date(purchaseOrder.purchase_request?.request_date) }}</div>
                    <div><span class="text-gray-500">Perusahaan:</span> {{ purchaseOrder.company?.name }}</div>
                    <div><span class="text-gray-500">Department:</span> {{ purchaseOrder.department?.name }}</div>
                </div>
                <div class="space-y-2 text-sm">
                    <div><span class="text-gray-500">Tanggal PO:</span> {{ date(purchaseOrder.created_at) }}</div>
                    <div><span class="text-gray-500">Vendor:</span> <strong>{{ purchaseOrder.vendor?.name }}</strong></div>
                    <div><span class="text-gray-500">Kontak:</span> {{ purchaseOrder.vendor?.phone ?? '-' }} · {{ purchaseOrder.vendor?.email ?? '-' }}</div>
                    <div><span class="text-gray-500">Alamat:</span> {{ purchaseOrder.vendor?.address ?? '-' }}</div>
                </div>
            </div>
            <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/[0.04]">
                <span class="font-medium">Keperluan PR:</span> {{ purchaseOrder.purchase_request?.purpose ?? '-' }}
            </div>

            <div class="po-print-items mt-6 overflow-x-auto">
                <table class="po-print-items-table w-full min-w-[720px] border-collapse text-sm">
                    <colgroup>
                        <col class="po-column-number" />
                        <col class="po-column-description" />
                        <col class="po-column-quantity" />
                        <col class="po-column-unit" />
                        <col class="po-column-price" />
                        <col class="po-column-price" />
                        <col class="po-column-total" />
                    </colgroup>
                    <thead><tr class="bg-purple-950 text-left text-white"><th class="p-3">No.</th><th class="p-3">Deskripsi</th><th class="p-3 text-right">Qty</th><th class="p-3">Unit</th><th class="p-3 text-right">Estimasi PR</th><th class="p-3 text-right">Harga PO</th><th class="p-3 text-right">Jumlah</th></tr></thead>
                    <tbody>
                        <tr v-for="(item, index) in purchaseOrder.items" :key="item.id" class="border-b border-gray-200 dark:border-gray-700">
                            <td class="p-3 align-top">{{ index + 1 }}</td>
                            <td class="p-3 align-top"><div class="font-medium">{{ item.product?.name ?? item.service_type?.name ?? item.description ?? 'Item' }}</div><div class="text-xs text-gray-500">{{ item.product?.code ?? item.service_type?.code ?? item.description }}<span v-if="item.asset_id"> · Asset {{ item.asset_id }}</span></div><div v-if="item.price_histories.length" class="mt-2 text-xs text-gray-500 screen-only">{{ item.price_histories.length }} perubahan harga tercatat</div></td>
                            <td class="p-3 text-right align-top">{{ Number(item.quantity).toLocaleString('id-ID') }}</td>
                            <td class="p-3 align-top">{{ item.uom?.code ?? '-' }}</td>
                            <td class="p-3 text-right align-top">{{ item.pr_estimated_unit_price === null ? 'Belum diestimasi' : money(item.pr_estimated_unit_price) }}</td>
                            <td class="p-3 text-right align-top">{{ item.unit_price === null ? 'Belum diisi' : money(item.unit_price) }}</td>
                            <td class="p-3 text-right align-top">{{ money(Number(item.quantity) * Number(item.unit_price ?? 0)) }}</td>
                        </tr>
                    </tbody>
                    <tfoot><tr class="font-bold"><td colspan="6" class="p-3 text-right">TOTAL PO</td><td class="p-3 text-right">{{ money(total) }}</td></tr></tfoot>
                </table>
            </div>

            <div v-if="purchaseOrder.items.some(item => item.price_histories.length)" class="screen-only mt-8">
                <h3 class="mb-3 font-semibold">Histori Perubahan Harga</h3>
                <div v-for="item in purchaseOrder.items.filter(row => row.price_histories.length)" :key="item.id" class="mb-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="mb-2 text-sm font-medium">{{ item.product?.name ?? item.service_type?.name ?? item.description ?? 'Item' }}</p>
                    <div v-for="(history, historyIndex) in item.price_histories" :key="historyIndex" class="border-t border-gray-100 py-2 text-sm dark:border-gray-800">
                        <div class="flex flex-wrap justify-between gap-2"><span>{{ history.previous_unit_price === null ? 'Belum diisi' : money(history.previous_unit_price) }} → <strong>{{ money(history.new_unit_price) }}</strong></span><span class="text-xs text-gray-500">{{ history.changed_by?.name ?? '-' }} · {{ dateTime(history.created_at) }}</span></div>
                        <p class="mt-1 text-gray-600 dark:text-gray-400">{{ history.reason }}</p>
                    </div>
                </div>
            </div>
            <div v-if="purchaseOrder.status === 'CANCELLED'" class="screen-only mt-5 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950/30 dark:text-red-200"><strong>Alasan pembatalan:</strong> {{ purchaseOrder.cancellation_reason }}</div>
            <div v-if="purchaseOrder.submitted_at" class="screen-only mt-5 text-xs text-gray-500">Disubmit pada {{ dateTime(purchaseOrder.submitted_at) }} oleh {{ purchaseOrder.submitted_by?.name ?? '-' }}.</div>
            <div class="mt-12 border-t border-gray-300 pt-4 text-xs text-gray-500">{{ purchaseOrder.company?.name }} · Dokumen Purchase Order {{ purchaseOrder.number }}</div>
        </section>
    </div>
</template>

<style scoped>
.print-document { display: none; }

@media print {
    :global(body) { background: #fff !important; }
    :global(.layout-wrapper), :global(.layout-main-container), :global(.layout-main) { margin: 0 !important; padding: 0 !important; }
    :global(.layout-sidebar), :global(.layout-topbar), :global(.layout-footer), :global(.p-toast), :global(.p-dialog-mask) { display: none !important; }
    .purchase-order-page { margin: 0 !important; padding: 0 !important; }
    .screen-only { display: none !important; }
    .print-document { display: block !important; }
    .po-cancelled .print-document { display: none !important; }
    .po-paper { border: 0 !important; border-radius: 0 !important; box-shadow: none !important; padding: 0 !important; color: #111 !important; }
    .po-print-items { max-width: 100% !important; overflow: visible !important; }
    .po-print-items-table { width: 100% !important; min-width: 0 !important; table-layout: fixed !important; font-size: 7pt !important; }
    .po-print-items-table .po-column-number { width: 4%; }
    .po-print-items-table .po-column-description { width: 33%; }
    .po-print-items-table .po-column-quantity { width: 7%; }
    .po-print-items-table .po-column-unit { width: 7%; }
    .po-print-items-table .po-column-price { width: 16%; }
    .po-print-items-table .po-column-total { width: 17%; }
    .po-print-items-table :deep(th), .po-print-items-table :deep(td) { padding: 4pt 3pt !important; white-space: normal !important; overflow-wrap: anywhere; }
    .po-print-items-table :deep(th:nth-child(n+3)), .po-print-items-table :deep(td:nth-child(n+3)) { font-size: 6.5pt !important; }
    .po-paper :deep(th) { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    @page { size: A4 portrait; margin: 14mm; }
}
</style>
