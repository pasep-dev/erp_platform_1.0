<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AppPagination from '@/components/common/AppPagination.vue'
import DataTableToolbar from '@/components/common/DataTableToolbar.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import PrintDocumentHeader from '@/components/print/PrintDocumentHeader.vue'
import AssetQrCode from '@/components/asset/AssetQrCode.vue'
import AssetBarcode from '@/components/asset/AssetBarcode.vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Select from 'primevue/select'

defineOptions({ layout: AppLayout })

interface Asset {
    id: string
    number: string
    registration_method: string
    status: string
    assignment_type: 'USER' | 'LOCATION'
    serial_number: string | null
    specifications: Record<string, string> | null
    warranty_start_date: string | null
    warranty_end_date: string | null
    warranty_duration_months: number | null
    acquisition_date: string
    acquisition_cost: string | null
    remarks: string | null
    product: { code: string; name: string; category?: { name: string } | null; type?: { name: string } | null; brand?: { name: string } | null; model?: { name: string } | null }
    company: { code: string; name: string }
    department: { code: string; name: string }
    location: { code: string; name: string; location_type?: string | null }
    branch?: { code: string; name: string } | null
    custodian?: { name: string; email: string } | null
    registered_by?: { name: string } | null
    latest_maintenance_record?: { next_service_date: string | null } | null
}

interface OrganizationOption {
    id: string
    code: string
    name: string
}

interface ReminderAsset {
    id: string
    number: string
    product: { code: string; name: string }
    latest_maintenance_record?: { next_service_date: string | null } | null
}

const props = defineProps<{
    assets: { data: Asset[]; total: number; per_page: number; links: { url: string | null; label: string; active: boolean }[] }
    filters: { search: string; company_id: string | null; department_id: string | null; branch_id: string | null; status: string; sort_field: string; sort_direction: string; per_page: number }
    counts: { total: number; active: number; assigned: number; disposed: number }
    companies: OrganizationOption[]
    departments: OrganizationOption[]
    branches: OrganizationOption[]
    can_create_maintenance: boolean
    can_dispose: boolean
    reminders: { service_due: ReminderAsset[] }
}>()

const search = ref(props.filters.search)
const allOrganizationValue = 'ALL'
const companyId = ref(props.filters.company_id || allOrganizationValue)
const departmentId = ref(props.filters.department_id || allOrganizationValue)
const branchId = ref(props.filters.branch_id || allOrganizationValue)
const status = ref(props.filters.status || 'ALL')
const perPage = ref(props.assets.per_page)
const selectedIds = ref<string[]>([])
const selectedAsset = ref<Asset | null>(null)
const detailVisible = ref(false)
const codeCopied = ref(false)
const printDialogVisible = ref(false)
const printScope = ref<'selected' | 'page' | 'all'>('page')
const activeReminder = ref<'service' | null>(null)
const sortField = ref(props.filters.sort_field || 'asset')
const sortDirection = ref(props.filters.sort_direction || 'asc')

const companyOptions = computed(() => [{ id: allOrganizationValue, code: '', name: 'Semua perusahaan' }, ...props.companies])
const printCompanyName = computed(() => {
    const companyNames = [...new Set(props.assets.data.map((asset) => asset.company.name).filter(Boolean))]

    return companyNames.length === 1 ? companyNames[0] : companyNames.length > 1 ? 'Multi-company' : null
})
const departmentOptions = computed(() => [{ id: allOrganizationValue, code: '', name: 'Semua departemen' }, ...props.departments])
const branchOptions = computed(() => [
    { id: allOrganizationValue, code: '', name: 'Semua cabang' },
    ...props.branches.map((branch) => ({ ...branch, name: `${branch.code} · ${branch.name}` })),
])

watch(() => props.assets.data, (assets) => {
    const visibleIds = new Set(assets.map((asset) => asset.id))
    selectedIds.value = selectedIds.value.filter((id) => visibleIds.has(id))
})

function applyFilters(): void {
    router.get(route('transaction.asset-management.directory.index'), filterParams(), { preserveState: true, preserveScroll: true, replace: true })
}

function filterParams(): Record<string, string | number | undefined> {
    return {
        search: search.value.trim() || undefined,
        company_id: companyId.value !== allOrganizationValue ? companyId.value || undefined : undefined,
        department_id: departmentId.value !== allOrganizationValue ? departmentId.value || undefined : undefined,
        branch_id: branchId.value !== allOrganizationValue ? branchId.value || undefined : undefined,
        status: status.value !== 'ALL' ? status.value : undefined,
        sort_field: sortField.value,
        sort_direction: sortDirection.value,
        per_page: perPage.value,
    }
}

function clearFilters(): void {
    search.value = ''
    companyId.value = allOrganizationValue
    departmentId.value = allOrganizationValue
    branchId.value = allOrganizationValue
    status.value = 'ALL'
    sortField.value = 'asset'
    sortDirection.value = 'asc'
    perPage.value = 15
    applyFilters()
}

function exportReport(): void {
    const exportUrl = new URL(route('transaction.asset-management.directory.export'), window.location.origin)
    for (const [key, value] of Object.entries(filterParams())) {
        if (value !== undefined && value !== '') {
            exportUrl.searchParams.set(key, String(value))
        }
    }
    window.location.href = exportUrl.toString()
}

function printReport(): void {
    window.print()
}

function changePerPage(): void {
    applyFilters()
}

function toggleAssetSelection(assetId: string, event: Event): void {
    const checked = (event.target as HTMLInputElement).checked
    selectedIds.value = checked
        ? [...new Set([...selectedIds.value, assetId])]
        : selectedIds.value.filter((id) => id !== assetId)
}

function openDetails(asset: Asset): void {
    selectedAsset.value = asset
    codeCopied.value = false
    detailVisible.value = true
}

async function copyAssetNumber(): Promise<void> {
    if (!selectedAsset.value) {
        return
    }

    await navigator.clipboard.writeText(selectedAsset.value.number)
    codeCopied.value = true
    window.setTimeout(() => {
        codeCopied.value = false
    }, 1800)
}

function changeSort(field: string): void {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortField.value = field
        sortDirection.value = 'asc'
    }
    applyFilters()
}

function printQrCodes(scope: 'page' | 'all' | 'selected' | 'specific', specificId?: string): void {
    if ((scope === 'selected' && selectedIds.value.length === 0) || (scope === 'specific' && !specificId)) {
        return
    }

    const printUrl = new URL(route('transaction.asset-management.directory.qr-codes.print'), window.location.origin)
    printUrl.searchParams.set('scope', scope)
    printUrl.searchParams.set('print', '1')
    printUrl.searchParams.set('closeAfterPrint', '1')

    if (scope === 'page') {
        props.assets.data.forEach((asset) => printUrl.searchParams.append('ids[]', asset.id))
    } else if (scope === 'selected') {
        selectedIds.value.forEach((id) => printUrl.searchParams.append('ids[]', id))
    } else if (scope === 'specific' && specificId) {
        printUrl.searchParams.append('ids[]', specificId)
    } else {
        for (const [key, value] of Object.entries(filterParams())) {
            if (value !== undefined && value !== '') {
                printUrl.searchParams.set(key, String(value))
            }
        }
    }

    window.open(printUrl.toString(), '_blank', 'noopener')
}

function openPrintOptions(): void {
    printScope.value = selectedIds.value.length > 0 ? 'selected' : 'page'
    printDialogVisible.value = true
}

function submitPrintSelection(): void {
    printDialogVisible.value = false
    printQrCodes(printScope.value)
}

function date(value: string | null | undefined): string {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value)) : '-'
}

function daysUntil(value: string | null | undefined): number | null {
    if (!value) return null
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    const dueDate = new Date(`${value.slice(0, 10)}T00:00:00`)
    return Math.round((dueDate.getTime() - today.getTime()) / 86_400_000)
}

function dueLabel(value: string | null | undefined): string {
    const remainingDays = daysUntil(value)
    if (remainingDays === null) return '-'
    if (remainingDays < 0) return `Terlambat ${Math.abs(remainingDays)} hari`
    if (remainingDays === 0) return 'Jatuh tempo hari ini'
    return `Dalam ${remainingDays} hari`
}

function dueSeverity(value: string | null | undefined): 'danger' | 'warn' {
    return (daysUntil(value) ?? 1) < 0 ? 'danger' : 'warn'
}

function warrantyLabel(asset: Asset): string {
    const months = asset.warranty_duration_months
    if (!months) return 'Tidak dicatat'
    const duration = months % 12 === 0 ? `${months / 12} tahun` : `${months} bulan`
    return `${duration} · ${date(asset.warranty_start_date)} sampai ${date(asset.warranty_end_date)}`
}

function assetStatusLabel(status: string): string {
    return ({ ACTIVE: 'Aktif', MISSING: 'Tidak Ditemukan', DISPOSAL_PENDING: 'Menunggu Pelepasan', DISPOSED: 'Disposed' } as Record<string, string>)[status] ?? status
}

function assetStatusSeverity(status: string): 'success' | 'danger' | 'warn' | 'secondary' {
    return ({ ACTIVE: 'success', MISSING: 'danger', DISPOSAL_PENDING: 'warn', DISPOSED: 'warn' } as Record<string, 'success' | 'danger' | 'warn' | 'secondary'>)[status] ?? 'secondary'
}

function assignmentLabel(asset: Asset): string {
    if (asset.status === 'DISPOSED') return 'Disposed'
    if (asset.status === 'DISPOSAL_PENDING') return 'Menunggu Pelepasan'
    if (asset.status === 'MISSING') return 'Tidak Ditemukan'
    if (asset.assignment_type === 'USER') return 'Ditugaskan ke user'
    return asset.location.location_type?.toUpperCase() === 'WAREHOUSE' ? 'Tersedia' : 'Penempatan lokasi'
}

function assignmentSeverity(asset: Asset): 'success' | 'info' | 'danger' | 'warn' {
    if (asset.status === 'MISSING') return 'danger'
    if (asset.status === 'DISPOSAL_PENDING' || asset.status === 'DISPOSED') return 'warn'
    return asset.assignment_type === 'USER' ? 'info' : 'success'
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader title="Asset Directory" section="Directory" description="Dashboard aset untuk memantau kondisi, mencari detail, membuat laporan, dan mengelola QR code.">
            <template #actions>
            <div class="directory-actions flex flex-wrap gap-2 print:hidden">
                <Button label="Cetak Laporan" icon="pi pi-file-pdf" severity="secondary" outlined :disabled="assets.data.length === 0" @click="printReport" />
                <Button label="Export CSV" icon="pi pi-download" severity="secondary" :disabled="assets.total === 0" @click="exportReport" />
                <Button label="Cetak QR" icon="pi pi-qrcode" severity="secondary" outlined :disabled="assets.total === 0" v-tooltip.top="'Pilih aset yang akan dicetak QR code-nya'" @click="openPrintOptions" />
            </div>
            </template>
        </AssetManagementPageHeader>

        <section class="report-summary grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"><p class="text-xs text-gray-500">Aset sesuai filter</p><p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ counts.total.toLocaleString('id-ID') }}</p></article>
            <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30"><p class="text-xs text-emerald-800 dark:text-emerald-300">Aktif</p><p class="mt-2 text-2xl font-semibold text-emerald-700">{{ counts.active.toLocaleString('id-ID') }}</p></article>
            <article class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30"><p class="text-xs text-blue-800 dark:text-blue-300">Sedang ditugaskan</p><p class="mt-2 text-2xl font-semibold text-blue-700">{{ counts.assigned.toLocaleString('id-ID') }}</p></article>
            <article class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30"><p class="text-xs text-amber-800 dark:text-amber-300">Disposed</p><p class="mt-2 text-2xl font-semibold text-amber-700">{{ counts.disposed.toLocaleString('id-ID') }}</p></article>
        </section>

        <section class="report-toolbar print:hidden">
            <button type="button" class="flex w-full flex-wrap items-center justify-between gap-3 rounded-xl border border-purple-200 bg-white px-4 py-3 text-left shadow-sm dark:border-purple-900 dark:bg-gray-900" :aria-expanded="activeReminder === 'service'" @click="activeReminder = activeReminder === 'service' ? null : 'service'">
                <span class="flex items-center gap-2 font-medium text-gray-900 dark:text-white"><i class="pi pi-wrench text-purple-700 dark:text-purple-300" />Pengingat service <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-800 dark:bg-purple-900/50 dark:text-purple-200">{{ reminders.service_due.length }}</span></span>
                <span class="text-xs text-gray-500">Jadwal terlewat atau dalam 30 hari <i :class="activeReminder === 'service' ? 'pi pi-chevron-up ml-1' : 'pi pi-chevron-down ml-1'" /></span>
            </button>
            <div v-if="activeReminder === 'service'" class="mt-2 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <Link v-for="asset in reminders.service_due" :key="asset.id" :href="route('transaction.asset-management.directory.show', asset.id)" class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-2.5 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.03]">
                    <span class="min-w-0"><span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ asset.number }} · {{ asset.product.name }}</span><span class="text-xs text-gray-500">Jadwal {{ date(asset.latest_maintenance_record?.next_service_date) }}</span></span>
                    <Tag :value="dueLabel(asset.latest_maintenance_record?.next_service_date)" :severity="dueSeverity(asset.latest_maintenance_record?.next_service_date)" />
                </Link>
                <p v-if="reminders.service_due.length === 0" class="px-4 py-3 text-sm text-gray-500">Tidak ada jadwal service yang jatuh tempo.</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Daftar dan laporan aset</h2>
                <p class="text-xs text-gray-500">Gunakan filter untuk menyusun hasil laporan. Export memuat semua hasil, cetak memuat halaman aktif.</p>
            </div>
            <div class="hidden px-5 pt-4 print:block">
                <PrintDocumentHeader domain="asset-management" title="Asset Directory" :company-name="printCompanyName" :date-label="`Dicetak · ${new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date())}`" />
            </div>
            <DataTableToolbar v-model="search" :total="assets.total" total-label="Aset" placeholder="Cari kode aset, SN, produk, atau pengguna..." @search="applyFilters">
                <Select v-model="companyId" :options="companyOptions" option-label="name" option-value="id" filter class="w-full sm:w-44" aria-label="Filter perusahaan" @change="applyFilters" />
                <Select v-model="departmentId" :options="departmentOptions" option-label="name" option-value="id" filter class="w-full sm:w-44" aria-label="Filter departemen" @change="applyFilters" />
                <Select v-model="branchId" :options="branchOptions" option-label="name" option-value="id" filter class="w-full sm:w-44" aria-label="Filter cabang" @change="applyFilters" />
                <Select v-model="status" :options="[{ label: 'Semua Status', value: 'ALL' }, { label: 'Tidak Ditemukan', value: 'MISSING' }, { label: 'Menunggu Pelepasan', value: 'DISPOSAL_PENDING' }, { label: 'Disposed', value: 'DISPOSED' }]" option-label="label" option-value="value" class="w-full sm:w-44" aria-label="Filter status aset" @change="applyFilters" />
                <Button type="button" label="Reset" severity="secondary" outlined @click="clearFilters" />
            </DataTableToolbar>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-3 py-3 print:hidden"></th><th v-for="column in [{ key: 'asset', label: 'Aset' }, { key: 'company', label: 'Perusahaan / Departemen' }, { key: 'branch', label: 'Cabang / Lokasi' }, { key: 'assignment', label: 'Penugasan' }, { key: 'status', label: 'Status' }, { key: 'warranty', label: 'Garansi' }]" :key="column.key" class="px-4 py-3"><button type="button" class="inline-flex items-center gap-1 hover:text-purple-700" @click="changeSort(column.key)">{{ column.label }}<i :class="sortField === column.key ? (sortDirection === 'asc' ? 'pi pi-sort-amount-up-alt' : 'pi pi-sort-amount-down') : 'pi pi-sort-alt'" /></button></th><th class="px-4 py-3 text-right print:hidden">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="asset in assets.data" :key="asset.id" class="text-gray-700 dark:text-gray-200">
                            <td class="px-3 py-3 print:hidden"><input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-purple-700 focus:ring-purple-600" :checked="selectedIds.includes(asset.id)" :aria-label="`Pilih ${asset.number}`" @change="toggleAssetSelection(asset.id, $event)" /></td>
                            <td class="px-4 py-3"><div class="font-semibold text-gray-900 dark:text-white">{{ asset.number }}</div><div class="text-xs text-gray-500">{{ asset.product.code }} · {{ asset.product.name }}</div><div v-if="asset.serial_number" class="text-xs text-gray-500">SN {{ asset.serial_number }}</div></td>
                            <td class="px-4 py-3">{{ asset.company.code }} · {{ asset.company.name }}<div class="text-xs text-gray-500">{{ asset.department.code }} · {{ asset.department.name }}</div></td>
                            <td class="px-4 py-3">{{ asset.branch?.code ?? '-' }}<div class="text-xs text-gray-500">{{ asset.location.code }} · {{ asset.location.name }}</div></td>
                            <td class="px-4 py-3"><Tag :value="assignmentLabel(asset)" :severity="assignmentSeverity(asset)" /><div v-if="asset.assignment_type === 'USER' && asset.custodian" class="mt-1 text-xs text-gray-500">{{ asset.custodian.name }}</div><div v-else class="mt-1 text-xs text-gray-500">{{ asset.location.name }}</div></td>
                            <td class="px-4 py-3"><Tag :value="assetStatusLabel(asset.status)" :severity="assetStatusSeverity(asset.status)" /></td>
                            <td class="px-4 py-3">{{ date(asset.warranty_end_date) }}</td>
                            <td class="px-4 py-3 text-right print:hidden"><Link :href="route('transaction.asset-management.directory.show', asset.id)" class="inline-flex" aria-label="Lihat detail aset"><Button icon="pi pi-eye" severity="secondary" text rounded v-tooltip.top="'Lihat detail dan riwayat aset'" /></Link></td>
                        </tr>
                        <tr v-if="assets.data.length === 0"><td colspan="8" class="px-4 py-12 text-center text-sm text-gray-500">Tidak ada aset yang sesuai dengan filter.</td></tr>
                    </tbody>
                </table>
            </div>
            <AppPagination v-if="assets.total > 0" class="report-toolbar print:hidden" v-model:per-page="perPage" :meta="assets" @change="changePerPage" />
        </section>

        <Dialog v-model:visible="printDialogVisible" modal header="Cetak QR Code Aset" :style="{ width: 'min(36rem, 94vw)' }">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">Pilih cakupan aset yang akan disiapkan untuk dicetak.</p>
            <div class="grid gap-3">
                <button v-for="option in [
                    { value: 'selected', title: 'Aset dipilih', description: `${selectedIds.length} aset yang dicentang`, icon: 'pi pi-check-square', disabled: selectedIds.length === 0 },
                    { value: 'page', title: 'Halaman ini', description: `${assets.data.length} aset pada halaman saat ini`, icon: 'pi pi-list', disabled: assets.data.length === 0 },
                    { value: 'all', title: 'Semua hasil', description: `${assets.total} aset sesuai filter saat ini`, icon: 'pi pi-th-large', disabled: assets.total === 0 },
                ]" :key="option.value" type="button" :disabled="option.disabled" class="flex items-center gap-3 rounded-xl border p-4 text-left transition" :class="[
                    printScope === option.value ? 'border-purple-600 bg-purple-50 ring-1 ring-purple-600 dark:bg-purple-950/30' : 'border-gray-200 hover:border-purple-300 dark:border-gray-700',
                    option.disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                ]" @click="printScope = option.value as 'selected' | 'page' | 'all'">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-200"><i :class="option.icon" /></span>
                    <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">{{ option.title }}</span><span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ option.description }}</span></span>
                    <i :class="printScope === option.value ? 'pi pi-check-circle text-purple-700 dark:text-purple-300' : 'pi pi-circle text-gray-300'" />
                </button>
            </div>
            <template #footer>
                <Button label="Batal" severity="secondary" text @click="printDialogVisible = false" />
                <Button label="Lanjut cetak" icon="pi pi-print" :disabled="(printScope === 'selected' && selectedIds.length === 0) || (printScope === 'page' && assets.data.length === 0) || (printScope === 'all' && assets.total === 0)" @click="submitPrintSelection" />
            </template>
        </Dialog>

        <Dialog v-model:visible="detailVisible" modal :show-header="false" :style="{ width: 'min(68rem, 96vw)' }" :content-style="{ padding: '0' }">
            <div v-if="selectedAsset" class="overflow-hidden rounded-xl bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100">
                <div class="relative border-b border-gray-200 bg-gradient-to-br from-purple-50 via-white to-white px-6 py-6 dark:border-gray-800 dark:from-purple-950/40 dark:via-gray-900 dark:to-gray-900 sm:px-8">
                    <div class="flex flex-col gap-6 pr-8 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <Tag :value="assetStatusLabel(selectedAsset.status)" :severity="assetStatusSeverity(selectedAsset.status)" />
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-2.5 py-1 text-xs font-medium text-gray-600 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700">
                                    <i class="pi pi-box text-purple-700 dark:text-purple-300" />
                                    {{ selectedAsset.registration_method === 'PROCUREMENT' ? 'Dari procurement' : 'Aset existing' }}
                                </span>
                            </div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-purple-700 dark:text-purple-300">Asset Directory</p>
                            <h2 class="mt-1 break-all text-2xl font-bold tracking-tight sm:text-3xl">{{ selectedAsset.number }}</h2>
                            <p class="mt-2 text-base font-medium text-gray-700 dark:text-gray-200">{{ selectedAsset.product.name }}</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ selectedAsset.product.code }}<span v-if="selectedAsset.serial_number"> · SN {{ selectedAsset.serial_number }}</span></p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <Button size="small" :label="codeCopied ? 'Tersalin' : 'Salin kode aset'" :icon="codeCopied ? 'pi pi-check' : 'pi pi-copy'" severity="secondary" outlined @click="copyAssetNumber" />
                                <Button size="small" label="Cetak QR Code" icon="pi pi-print" outlined @click="printQrCodes('specific', selectedAsset.id)" />
                                <Link v-if="can_dispose && selectedAsset.status === 'ACTIVE' && !selectedAsset.custodian" :href="route('transaction.asset-management.disposals.create', selectedAsset.id)">
                                    <Button size="small" label="Ajukan Pelepasan" icon="pi pi-ban" severity="warn" outlined />
                                </Link>
                                <Link v-if="can_create_maintenance && selectedAsset.status === 'ACTIVE'" :href="route('transaction.asset-management.maintenance.create', selectedAsset.id)">
                                    <Button size="small" label="Request Maintenance" icon="pi pi-wrench" severity="secondary" outlined />
                                </Link>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center justify-center self-start rounded-2xl border border-purple-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:self-center">
                            <AssetQrCode :value="selectedAsset.number" />
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 p-5 sm:p-7 lg:grid-cols-2">
                    <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-gray-800/40">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-200"><i class="pi pi-id-card" /></span>
                            <div><h3 class="font-semibold">Identitas aset</h3><p class="text-xs text-gray-500 dark:text-gray-400">Klasifikasi dan detail produk</p></div>
                        </div>
                        <dl class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Kategori</dt><dd class="mt-1 text-sm font-medium">{{ selectedAsset.product.category?.name ?? '-' }}</dd></div>
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Tipe aset</dt><dd class="mt-1 text-sm font-medium">{{ selectedAsset.product.type?.name ?? '-' }}</dd></div>
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Merek</dt><dd class="mt-1 text-sm font-medium">{{ selectedAsset.product.brand?.name ?? '-' }}</dd></div>
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Model</dt><dd class="mt-1 text-sm font-medium">{{ selectedAsset.product.model?.name ?? '-' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs text-gray-500 dark:text-gray-400">Serial number</dt><dd class="mt-1 text-sm font-medium">{{ selectedAsset.serial_number || 'Tidak menggunakan serial number' }}</dd></div>
                        </dl>
                        <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Spesifikasi</p>
                            <div v-if="Object.keys(selectedAsset.specifications ?? {}).length" class="flex flex-wrap gap-2">
                                <span v-for="([key, value], index) in Object.entries(selectedAsset.specifications ?? {})" :key="`${key}-${index}`" class="rounded-lg bg-white px-3 py-2 text-xs ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700"><span class="text-gray-500 dark:text-gray-400">{{ key }}</span><span class="mx-1 text-gray-300">·</span><span class="font-semibold">{{ value }}</span></span>
                            </div>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">Belum ada spesifikasi tambahan.</p>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-gray-800/40">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-200"><i class="pi pi-map-marker" /></span>
                            <div><h3 class="font-semibold">Penempatan</h3><p class="text-xs text-gray-500 dark:text-gray-400">Unit organisasi dan lokasi aset</p></div>
                        </div>
                        <dl class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Perusahaan</dt><dd class="mt-1 text-sm font-medium">{{ selectedAsset.company.name }}</dd></div>
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Cabang</dt><dd class="mt-1 text-sm font-medium">{{ selectedAsset.branch?.name ?? '-' }}</dd></div>
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Departemen</dt><dd class="mt-1 text-sm font-medium">{{ selectedAsset.department.name }}</dd></div>
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Lokasi</dt><dd class="mt-1 text-sm font-medium">{{ selectedAsset.location.name }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs text-gray-500 dark:text-gray-400">Penanggung jawab</dt><dd class="mt-1 flex items-center gap-2 text-sm font-medium"><i class="pi pi-user text-gray-400" />{{ selectedAsset.custodian?.name ?? 'Belum ditentukan' }}</dd></div>
                        </dl>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-gray-800/40">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200"><i class="pi pi-wallet" /></span>
                            <div><h3 class="font-semibold">Informasi perolehan</h3><p class="text-xs text-gray-500 dark:text-gray-400">Tanggal, nilai, dan garansi</p></div>
                        </div>
                        <dl class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Tanggal perolehan</dt><dd class="mt-1 text-sm font-medium">{{ date(selectedAsset.acquisition_date) }}</dd></div>
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Nilai perolehan</dt><dd class="mt-1 text-sm font-medium">{{ selectedAsset.acquisition_cost ? `Rp ${Number(selectedAsset.acquisition_cost).toLocaleString('id-ID')}` : '-' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs text-gray-500 dark:text-gray-400">Masa garansi</dt><dd class="mt-1 text-sm font-medium">{{ warrantyLabel(selectedAsset) }}</dd></div>
                        </dl>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-gray-800/40">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-200"><i class="pi pi-file-edit" /></span>
                            <div><h3 class="font-semibold">Catatan</h3><p class="text-xs text-gray-500 dark:text-gray-400">Keterangan tambahan tentang aset</p></div>
                        </div>
                        <p class="whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ selectedAsset.remarks || 'Belum ada catatan untuk aset ini.' }}</p>
                    </section>
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style>
@media print {
    @page { size: A4 landscape; margin: 10mm; }
    body { background: #fff !important; }
    .layout-topbar, .layout-sidebar, .layout-footer, .layout-mask, .p-toast { display: none !important; }
    .layout-main-container { margin: 0 !important; padding: 0 !important; }
    .layout-main { padding: 0 !important; }
    .report-toolbar, .directory-actions { display: none !important; }
    table { width: 100% !important; min-width: 0 !important; font-size: 8pt !important; }
    th, td { padding: 4pt !important; }
    tr { break-inside: avoid; }
}
</style>
