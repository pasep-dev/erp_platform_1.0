<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AppPagination from '@/components/common/AppPagination.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import DataTableToolbar from '@/components/common/DataTableToolbar.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import CrudTable from '@/components/crud/CrudTable.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'

defineOptions({ layout: AppLayout })

interface MaintenanceRecord {
    id: string
    number: string
    maintenance_type: string
    priority: string
    status: string
    execution_mode: string
    reported_condition: string
    requested_at: string
    estimated_cost: string | null
    started_at: string | null
    rejected_at: string | null
    asset: { id: string; number: string; product: { code: string; name: string } | null } | null
    vendor: { code: string; name: string } | null
    requested_by: { name: string } | null
}

const props = defineProps<{
    records: { data: MaintenanceRecord[]; total: number; per_page: number; links: { url: string | null; label: string; active: boolean }[] }
    filters: { search: string; status: string }
    can_create: boolean
    can_manage: boolean
    condition_options: Record<string, string>
}>()

const search = ref(props.filters.search)
const status = ref(props.filters.status || 'ALL')
const perPage = ref(props.records.per_page)
const finishDialogVisible = ref(false)
const finishRecord = ref<MaintenanceRecord | null>(null)
const today = new Date()
today.setMinutes(today.getMinutes() - today.getTimezoneOffset())
const finishForm = useForm({
    work_summary: '',
    completed_condition: '',
    actual_cost: '',
    completed_at: today.toISOString().slice(0, 10),
    next_service_date: '',
})

const columns = [
    { key: 'number', label: 'Nomor' },
    { key: 'asset', label: 'Aset' },
    { key: 'maintenance_type', label: 'Jenis' },
    { key: 'requested_at', label: 'Tanggal Permintaan' },
    { key: 'vendor', label: 'Vendor' },
    { key: 'estimated_cost', label: 'Estimasi Biaya' },
    { key: 'status', label: 'Status' },
]

const rows = computed(() => props.records.data.map((record) => ({ ...record, id: record.id, record })))

function applyFilters(): void {
    router.get(route('transaction.asset-management.maintenance.index'), {
        search: search.value || undefined,
        status: status.value !== 'ALL' ? status.value : undefined,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function statusSeverity(value: string): string {
    return ({ REQUESTED: 'warn', WAITING_APPROVAL: 'warn', WAITING_PARTS: 'info', READY_TO_START: 'success', IN_PROGRESS: 'info', COMPLETED: 'success', CANCELLED: 'danger' } as Record<string, string>)[value] ?? 'secondary'
}

function date(value: string | null): string {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(`${value.slice(0, 10)}T00:00:00`)) : '-'
}

function money(value: string | null): string {
    return value === null ? '-' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value))
}

function statusLabel(record: MaintenanceRecord): string {
    if (record.status === 'CANCELLED' && record.rejected_at) {
        return 'Ditolak'
    }

    return ({ REQUESTED: 'Pemeriksaan', WAITING_APPROVAL: 'Pengajuan', WAITING_PARTS: 'Dalam Pengerjaan', READY_TO_START: 'Dalam Pengerjaan', IN_PROGRESS: 'Dalam Pengerjaan', COMPLETED: 'Selesai', CANCELLED: 'Dibatalkan' } as Record<string, string>)[record.status] ?? record.status
}

function openFinish(record: MaintenanceRecord): void {
    finishRecord.value = record
    finishForm.reset()
    finishForm.completed_condition = record.reported_condition
    finishForm.actual_cost = record.estimated_cost ?? '0'
    finishForm.completed_at = today.toISOString().slice(0, 10)
    finishDialogVisible.value = true
}

function completeMaintenance(): void {
    if (!finishRecord.value) {
        return
    }

    finishForm.post(route('transaction.asset-management.maintenance.complete', finishRecord.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            finishDialogVisible.value = false
            finishRecord.value = null
            finishForm.reset()
        },
    })
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader title="Maintenance &amp; Service" section="Maintenance" description="Catat permintaan, ajukan estimasi, putuskan persetujuan, dan simpan hasil pengerjaan.">
            <template #actions><Link v-if="can_create" :href="route('transaction.asset-management.maintenance.create')"><Button label="Request Maintenance" icon="pi pi-plus" /></Link></template>
        </AssetManagementPageHeader>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <DataTableToolbar v-model="search" :total="records.total" total-label="Maintenance" placeholder="Cari nomor, aset, produk, atau vendor..." @search="applyFilters">
                <AppSelect v-model="status" :options="[{ label: 'Semua Status', value: 'ALL' }, { label: 'Pemeriksaan', value: 'REQUESTED' }, { label: 'Pengajuan', value: 'WAITING_APPROVAL' }, { label: 'Dalam Pengerjaan', value: 'IN_PROGRESS' }, { label: 'Selesai', value: 'COMPLETED' }, { label: 'Dibatalkan / Ditolak', value: 'CANCELLED' }]" class="w-full sm:w-56" aria-label="Filter status maintenance" @change="applyFilters" />
            </DataTableToolbar>
            <CrudTable :columns="columns" :rows="rows" row-key="id" empty-message="Belum ada permintaan maintenance.">
                <template #cell-number="{ row }"><span class="font-semibold text-gray-900 dark:text-white">{{ row.record.number }}</span><div class="text-xs text-gray-500">oleh {{ row.record.requested_by?.name ?? '-' }}</div></template>
                <template #cell-asset="{ row }"><div class="font-medium text-gray-900 dark:text-white">{{ row.record.asset?.number ?? 'Aset tidak tersedia' }}</div><div class="text-xs text-gray-500">{{ row.record.asset?.product?.name ?? 'Produk tidak tersedia' }}<template v-if="row.record.asset?.product?.code"> · {{ row.record.asset.product.code }}</template></div></template>
                <template #cell-maintenance_type="{ row }"><span>{{ row.record.maintenance_type === 'PREVENTIVE' ? 'Preventive' : 'Corrective' }}</span><div class="text-xs text-gray-500">{{ row.record.execution_mode === 'INTERNAL' ? 'Tim Internal' : 'Vendor' }} · Prioritas {{ row.record.priority }}</div></template>
                <template #cell-requested_at="{ row }">{{ date(row.record.requested_at) }}</template>
                <template #cell-vendor="{ row }">{{ row.record.vendor?.name ?? 'Belum ditentukan' }}</template>
                <template #cell-estimated_cost="{ row }">{{ money(row.record.estimated_cost) }}</template>
                <template #cell-status="{ row }"><Tag :value="statusLabel(row.record)" :severity="statusSeverity(row.record.status)" /></template>
                <template #actions="{ row }"><Button v-if="can_manage && ['IN_PROGRESS', 'WAITING_PARTS', 'READY_TO_START'].includes(row.record.status)" icon="pi pi-check-circle" severity="success" text rounded aria-label="Selesaikan maintenance" v-tooltip.top="'Selesaikan maintenance'" @click="openFinish(row.record)" /><Link :href="route('transaction.asset-management.maintenance.show', row.record.id)" class="inline-flex"><Button icon="pi pi-eye" severity="info" text rounded aria-label="View maintenance" v-tooltip.top="'View detail maintenance'" /></Link></template>
            </CrudTable>
            <AppPagination v-if="records.total > 0" v-model:per-page="perPage" :meta="records" @change="applyFilters" />
        </section>

        <Dialog v-model:visible="finishDialogVisible" modal header="Selesaikan Maintenance" :style="{ width: 'min(42rem, 94vw)' }">
            <form class="space-y-4" @submit.prevent="completeMaintenance">
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ finishRecord?.number }} · {{ finishRecord?.asset?.number ?? 'Aset tidak tersedia' }} · {{ finishRecord?.asset?.product?.name ?? 'Produk tidak tersedia' }}</p>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Ringkasan pekerjaan <span class="text-red-600">*</span><textarea v-model="finishForm.work_summary" rows="3" required maxlength="5000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Catat tindakan yang dilakukan dan hasilnya..." /><small v-if="finishForm.errors.work_summary" class="font-normal text-red-600">{{ finishForm.errors.work_summary }}</small></label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Kondisi aset setelah maintenance <span class="text-red-600">*</span><select v-model="finishForm.completed_condition" required class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"><option v-for="(label, value) in condition_options" :key="value" :value="value">{{ label }}</option></select><small v-if="finishForm.errors.completed_condition" class="font-normal text-red-600">{{ finishForm.errors.completed_condition }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Biaya aktual (Rp) <span class="text-red-600">*</span><input v-model="finishForm.actual_cost" type="number" min="0" step="0.01" required class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="finishForm.errors.actual_cost" class="font-normal text-red-600">{{ finishForm.errors.actual_cost }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal selesai <span class="text-red-600">*</span><input v-model="finishForm.completed_at" type="date" required class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="finishForm.errors.completed_at" class="font-normal text-red-600">{{ finishForm.errors.completed_at }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Jadwal service berikutnya<input v-model="finishForm.next_service_date" type="date" class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="finishForm.errors.next_service_date" class="font-normal text-red-600">{{ finishForm.errors.next_service_date }}</small></label>
                </div>
                <div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" text type="button" @click="finishDialogVisible = false" /><Button label="Simpan & Selesaikan" icon="pi pi-check" severity="success" type="submit" :loading="finishForm.processing" /></div>
            </form>
        </Dialog>
    </div>
</template>
