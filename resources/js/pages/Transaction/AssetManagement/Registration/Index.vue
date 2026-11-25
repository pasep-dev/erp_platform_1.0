<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed, ref, watch } from 'vue'
import AppLayout from '@/layout/AppLayout.vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import CrudTable from '@/components/crud/CrudTable.vue'
import AppPagination from '@/components/common/AppPagination.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import DataTableToolbar from '@/components/common/DataTableToolbar.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'

defineOptions({ layout: AppLayout })

interface QueueItem {
    goods_receipt_item_id: string
    goods_receipt_number: string
    source_number: string
    received_at: string
    company_name: string
    product_name: string
    product_code: string
    type_name: string | null
    quantity_accepted: number
    registered_count: number
    pending_count: number
    has_fractional_quantity: boolean
}

const props = defineProps<{
    queue: QueueItem[]
    can_create: boolean
}>()

const columns = [
    { key: 'goods_receipt_number', label: 'Goods Receipt' },
    { key: 'product', label: 'Produk' },
    { key: 'source_number', label: 'Sumber' },
    { key: 'company_name', label: 'Perusahaan' },
    { key: 'quantity', label: 'Diterima / Belum Diregistrasi' },
    { key: 'status', label: 'Status' },
]

const rows = computed(() => props.queue.map((item) => ({
    id: item.goods_receipt_item_id,
    goods_receipt_number: item.goods_receipt_number,
    source_number: item.source_number,
    company_name: item.company_name,
    product: { name: item.product_name, code: item.product_code, type: item.type_name },
    quantity: `${Number(item.quantity_accepted).toLocaleString('id-ID')} diterima · ${Number(item.registered_count).toLocaleString('id-ID')} terdaftar · ${Number(item.pending_count).toLocaleString('id-ID')} tersisa`,
    status: item.has_fractional_quantity ? 'Perlu koreksi kuantitas' : 'Menunggu Registrasi',
    status_severity: item.has_fractional_quantity ? 'warn' : 'info',
    queue_item: item,
})))
const perPage = ref(15)
const currentPage = ref(1)
const search = ref('')
const queueStatus = ref('ALL')
const importVisible = ref(false)
const importInput = ref<HTMLInputElement | null>(null)
const importForm = useForm<{ file: File | null }>({ file: null })
const filteredRows = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase('id-ID')

    return rows.value.filter((row) => {
        const matchesSearch = !keyword || [
            row.goods_receipt_number,
            row.source_number,
            row.product.code,
            row.product.name,
            row.company_name,
        ].some((value) => value.toLocaleLowerCase('id-ID').includes(keyword))
        const matchesStatus = queueStatus.value === 'ALL'
            || (queueStatus.value === 'NEEDS_REVIEW' ? row.queue_item.has_fractional_quantity : !row.queue_item.has_fractional_quantity)

        return matchesSearch && matchesStatus
    })
})
const pageCount = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / perPage.value)))
const visibleRows = computed(() => filteredRows.value.slice((currentPage.value - 1) * perPage.value, currentPage.value * perPage.value))
const paginationMeta = computed(() => ({ total: filteredRows.value.length }))

function registrationHref(item: QueueItem): string {
    const url = new URL(route('transaction.asset-management.registrations.create'), window.location.origin)
    url.searchParams.set('goods_receipt_item_id', item.goods_receipt_item_id)

    return `${url.pathname}${url.search}`
}

watch([search, queueStatus, perPage], () => {
    currentPage.value = 1
})

function updatePerPage(): void {
    currentPage.value = 1
}

function chooseImportFile(event: Event): void {
    importForm.file = (event.target as HTMLInputElement).files?.[0] ?? null
}

function submitImport(): void {
    if (importForm.file === null) {
        return
    }

    importForm.post(route('transaction.asset-management.directory.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importVisible.value = false
            importForm.reset()
            if (importInput.value) {
                importInput.value.value = ''
            }
        },
    })
}

</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader title="Registration" section="Registration" description="Proses registrasi aset dari GR. Aset yang sudah tercatat dapat dicari di Asset Directory.">
            <template #actions>
                <Button v-if="can_create" label="Registrasi Aset Existing" icon="pi pi-plus" severity="secondary" outlined @click="router.get(route('transaction.asset-management.registrations.create'))" />
                <Button v-if="can_create" label="Import Aset Existing" icon="pi pi-upload" severity="secondary" outlined @click="importVisible = true" />
            </template>
        </AssetManagementPageHeader>

        <section class="space-y-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Antrean Registrasi</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Aset dari GR yang sudah diposting dan masih memiliki unit untuk diregistrasi.</p>
            </div>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <DataTableToolbar v-model="search" :total="filteredRows.length" total-label="Antrean" placeholder="Cari GR, produk, atau perusahaan...">
                    <AppSelect v-model="queueStatus" :options="[{ label: 'Semua Status', value: 'ALL' }, { label: 'Menunggu Registrasi', value: 'PENDING' }, { label: 'Perlu Koreksi Kuantitas', value: 'NEEDS_REVIEW' }]" class="w-full sm:w-56" aria-label="Filter status registrasi" />
                </DataTableToolbar>
                <CrudTable :columns="columns" :rows="visibleRows" row-key="id" empty-message="Belum ada antrean registrasi dari GR.">
                    <template #cell-goods_receipt_number="{ row }"><span class="font-medium text-gray-900 dark:text-white">{{ row.goods_receipt_number }}</span></template>
                    <template #cell-product="{ row }"><div class="font-medium">{{ row.product.name }}</div><div class="text-xs text-gray-500">{{ row.product.code }}<template v-if="row.product.type"> · {{ row.product.type }}</template></div></template>
                    <template #cell-status="{ row }"><Tag :value="row.status" :severity="row.status_severity" /></template>
                    <template #actions="{ row }">
                        <a
                            v-if="can_create"
                            :href="registrationHref(row.queue_item)"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-blue-600 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-blue-400 dark:hover:bg-blue-500/10"
                            :aria-label="`Registrasikan ${row.product.name}`"
                            :title="row.queue_item.has_fractional_quantity ? 'Buka registrasi; kuantitas pecahan akan ditolak saat disimpan' : 'Registrasi Unit Aset'"
                        >
                            <i class="pi pi-plus" aria-hidden="true"></i>
                        </a>
                        <span v-else class="text-xs text-gray-400" title="Akses registration.create diperlukan">Tidak ada akses</span>
                    </template>
                </CrudTable>
                <AppPagination v-if="queue.length > 0" v-model:per-page="perPage" v-model:page="currentPage" :meta="paginationMeta" :client-page-count="pageCount" @change="updatePerPage" />
            </div>
        </section>

        <Dialog v-model:visible="importVisible" modal header="Import Aset Existing" :style="{ width: 'min(42rem, 94vw)' }">
            <div class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">Gunakan template CSV untuk memasukkan aset existing. Kode produk, perusahaan, departemen, dan lokasi harus sesuai dengan master data. Nomor aset dibuat otomatis dari aturan Document Numbering.</p>
                <a :href="route('transaction.asset-management.directory.template')" class="inline-flex items-center gap-2 text-sm font-medium text-purple-700 hover:underline dark:text-purple-300"><i class="pi pi-download" /> Unduh template CSV</a>
                <input ref="importInput" type="file" accept=".csv,text/csv" class="block w-full rounded-lg border border-gray-300 p-2 text-sm dark:border-gray-700" @change="chooseImportFile" />
                <small v-if="importForm.errors.file" class="block whitespace-pre-line text-red-600">{{ importForm.errors.file }}</small>
                <p class="text-xs text-gray-500">Satu baris CSV menghasilkan satu unit aset dan satu nomor aset unik. Untuk beberapa unit, gunakan satu baris per unit. Semua baris diperiksa sebelum disimpan.</p>
            </div>
            <template #footer>
                <Button label="Batal" severity="secondary" text @click="importVisible = false" />
                <Button label="Import" icon="pi pi-upload" :loading="importForm.processing" :disabled="!importForm.file" @click="submitImport" />
            </template>
        </Dialog>
    </div>
</template>
