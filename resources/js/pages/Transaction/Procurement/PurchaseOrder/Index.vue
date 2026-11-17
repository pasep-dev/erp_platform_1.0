<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import Dialog from 'primevue/dialog'
import { useConfirm } from 'primevue/useconfirm'
import CrudTable from '@/components/crud/CrudTable.vue'
import AppPagination from '@/components/common/AppPagination.vue'
import ProcurementPageHeader from '@/components/procurement/ProcurementPageHeader.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import DataTableToolbar from '@/components/common/DataTableToolbar.vue'
import AppLayout from '@/layout/AppLayout.vue'

defineOptions({ layout: AppLayout })

interface PurchaseOrder {
    id: string
    number: string
    status: string
    created_at: string
    items_count: number
    vendor?: { name: string }
    purchase_request?: { number: string }
    can_edit_prices: boolean
    can_submit: boolean
    can_cancel: boolean
}

const props = defineProps<{
    purchaseOrders: {
        data: PurchaseOrder[]
        from: number | null
        to: number | null
        total: number
        links: { url: string | null; label: string; active: boolean }[]
        last_page: number
        per_page: number
    }
    filters: { search?: string; status?: string }
}>()

const confirm = useConfirm()
const search = ref(props.filters.search ?? '')
const allStatusValue = 'ALL'
const status = ref(props.filters.status || allStatusValue)
const perPage = ref(props.purchaseOrders.per_page)
const cancelDialog = ref(false)
const cancelTarget = ref<PurchaseOrder | null>(null)
const cancelReason = ref('')
const cancelProcessing = ref(false)

const statusOptions = [
    { label: 'Semua Status', value: allStatusValue },
    { label: 'Draft', value: 'DRAFT' },
    { label: 'Submitted / Antrian GR', value: 'SUBMITTED' },
    { label: 'Partially Received', value: 'PARTIALLY_RECEIVED' },
    { label: 'Received', value: 'RECEIVED' },
    { label: 'Cancelled', value: 'CANCELLED' },
]
const columns = [
    { key: 'number', label: 'PO Number', class: 'w-[15%]' },
    { key: 'purchase_request', label: 'Purchase Request', class: 'w-[18%]' },
    { key: 'vendor', label: 'Vendor', class: 'w-[19%]' },
    { key: 'items_count', label: 'Items', class: 'w-[10%]' },
    { key: 'status', label: 'Status', class: 'w-[13%]' },
]

function applyFilters() {
    router.get(route('transaction.procurement.purchase-orders.index'), {
        search: search.value || undefined,
        status: status.value !== allStatusValue ? status.value : undefined,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function showOrder(order: PurchaseOrder) {
    router.get(route('transaction.procurement.purchase-orders.show', order.id))
}

function printOrder(order: PurchaseOrder) {
    const url = new URL(route('transaction.procurement.purchase-orders.show', order.id), window.location.origin)
    url.searchParams.set('print', '1')
    url.searchParams.set('closeAfterPrint', '1')
    window.open(url.toString(), '_blank', 'noopener')
}

function submitOrder(order: PurchaseOrder) {
    confirm.require({
        message: `Kirim PO ${order.number}? Status PO akan berubah menjadi Submitted dan masuk ke antrian Goods Receipt.`,
        header: 'Kirim Purchase Order',
        icon: 'pi pi-send',
        rejectLabel: 'Kembali',
        acceptLabel: 'Kirim PO',
        accept: () => router.post(route('transaction.procurement.purchase-orders.submit', order.id), {}, { preserveScroll: true }),
    })
}

function openCancel(order: PurchaseOrder) {
    cancelTarget.value = order
    cancelReason.value = ''
    cancelDialog.value = true
}

function cancelOrder() {
    if (!cancelTarget.value || !cancelReason.value.trim()) return
    cancelProcessing.value = true
    router.post(route('transaction.procurement.purchase-orders.cancel', cancelTarget.value.id), {
        reason: cancelReason.value,
    }, {
        preserveScroll: true,
        onFinish: () => { cancelProcessing.value = false; cancelDialog.value = false },
    })
}

function severity(value: string) {
    return ['SUBMITTED', 'RECEIVED'].includes(value) ? 'success' : value === 'CANCELLED' ? 'danger' : 'warn'
}

function statusLabel(value: string) {
    return value === 'SUBMITTED' ? 'SUBMITTED / ANTRIAN GR' : value === 'PARTIALLY_RECEIVED' ? 'PARTIALLY RECEIVED' : value
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <ConfirmDialog />
        <Dialog v-model:visible="cancelDialog" modal header="Batalkan Purchase Order" :style="{ width: 'min(32rem, 92vw)' }">
            <div class="space-y-3">
                <p class="text-sm text-gray-600">Alasan pembatalan wajib dicatat. PR sumber tetap berstatus Approved.</p>
                <Textarea v-model="cancelReason" rows="4" auto-resize class="w-full" placeholder="Alasan pembatalan" />
            </div>
            <template #footer>
                <Button label="Kembali" severity="secondary" text @click="cancelDialog = false" />
                <Button label="Batalkan PO" severity="danger" :disabled="!cancelReason.trim()" :loading="cancelProcessing" @click="cancelOrder" />
            </template>
        </Dialog>

        <ProcurementPageHeader
            title="Purchase Order"
            section="Transactions"
            description="Tinjau dan tindak lanjuti pesanan dari Purchase Request yang telah disetujui."
        />

        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <DataTableToolbar
                v-model="search"
                :total="purchaseOrders.total"
                total-label="Purchase Order"
                placeholder="Cari nomor PO / PR / vendor..."
                @search="applyFilters"
            >
                <AppSelect
                    v-model="status"
                    :options="statusOptions"
                    class="w-full sm:w-52"
                    aria-label="Filter status Purchase Order"
                    @change="applyFilters"
                />
            </DataTableToolbar>
            <CrudTable :columns="columns" :rows="purchaseOrders.data" table-class="table-fixed min-w-[1000px]" empty-message="Belum ada Purchase Order di antrian.">
                <template #cell-number="{ row }">
                    <button class="font-medium text-brand-600 hover:underline dark:text-brand-400" @click="showOrder(row)">{{ row.number }}</button>
                </template>
                <template #cell-purchase_request="{ row }">{{ row.purchase_request?.number ?? '-' }}</template>
                <template #cell-vendor="{ row }">{{ row.vendor?.name ?? '-' }}</template>
                <template #cell-status="{ row }"><Tag :value="statusLabel(row.status)" :severity="severity(row.status)" /></template>
                <template #actions="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <Button v-if="row.can_submit" icon="pi pi-send" class="procurement-primary-action" text rounded aria-label="Kirim PO" v-tooltip.top="'Kirim PO'" @click="submitOrder(row)" />
                        <Button v-if="row.status !== 'CANCELLED'" icon="pi pi-print" severity="secondary" text rounded aria-label="Print PO" v-tooltip.top="'Print'" @click="printOrder(row)" />
                        <Button v-if="row.can_edit_prices" icon="pi pi-pencil" severity="secondary" text rounded aria-label="Edit harga PO" v-tooltip.top="'Edit harga'" @click="router.get(route('transaction.procurement.purchase-orders.edit', row.id))" />
                        <Button icon="pi pi-eye" severity="secondary" text rounded aria-label="Lihat PO" v-tooltip.top="'Lihat'" @click="showOrder(row)" />
                        <Button v-if="row.can_cancel" icon="pi pi-ban" severity="danger" text rounded aria-label="Batalkan PO" v-tooltip.top="'Batalkan PO'" @click="openCancel(row)" />
                        <span v-if="row.status === 'CANCELLED'" class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-500 dark:bg-gray-800">Locked</span>
                    </div>
                </template>
            </CrudTable>
            <AppPagination
                v-if="purchaseOrders.total > 0"
                v-model:per-page="perPage"
                :meta="purchaseOrders"
                @change="applyFilters"
            />
        </div>
    </div>
</template>
