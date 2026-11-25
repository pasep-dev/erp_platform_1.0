<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AppPagination from '@/components/common/AppPagination.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import DataTableToolbar from '@/components/common/DataTableToolbar.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import CrudTable from '@/components/crud/CrudTable.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

interface Incident {
    id: string
    number: string
    incident_type: string
    severity: string
    status: string
    occurred_at: string
    description: string
    asset: { id: string; number: string; product: { code: string; name: string } | null } | null
    reported_by: { name: string } | null
}

const props = defineProps<{
    incidents: { data: Incident[]; total: number; per_page: number; links: { url: string | null; label: string; active: boolean }[] }
    filters: { search: string; status: string }
    counts: { reported: number; investigating: number; resolved: number }
    can_create: boolean
}>()

const search = ref(props.filters.search)
const status = ref(props.filters.status || 'ALL')
const perPage = ref(props.incidents.per_page)
const rows = computed(() => props.incidents.data.map((incident) => ({ ...incident, id: incident.id, incident })))
const columns = [
    { key: 'number', label: 'Nomor Insiden' },
    { key: 'asset', label: 'Aset' },
    { key: 'incident_type', label: 'Jenis' },
    { key: 'occurred_at', label: 'Tanggal Kejadian' },
    { key: 'reported_by', label: 'Pelapor' },
    { key: 'status', label: 'Status' },
]

function applyFilters(): void {
    router.get(route('transaction.asset-management.incidents.index'), {
        search: search.value || undefined,
        status: status.value !== 'ALL' ? status.value : undefined,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function date(value: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function typeLabel(value: string): string {
    return ({ DAMAGED: 'Rusak', LOST: 'Hilang', STOLEN: 'Dicuri', OTHER: 'Lainnya' } as Record<string, string>)[value] ?? value
}

function statusLabel(value: string): string {
    return ({ REPORTED: 'Dilaporkan', INVESTIGATING: 'Investigasi', RESOLVED: 'Selesai', DISMISSED: 'Ditutup' } as Record<string, string>)[value] ?? value
}

function severity(value: string): string {
    return ({ LOW: 'secondary', NORMAL: 'info', HIGH: 'warn', URGENT: 'danger' } as Record<string, string>)[value] ?? 'secondary'
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader title="Insiden Aset" section="Incident" description="Catat kejadian aset dan tindak lanjuti tanpa mengubah data aset sebelum hasil pemeriksaan dikonfirmasi.">
            <template #actions><Link v-if="can_create" :href="route('transaction.asset-management.incidents.create')"><Button label="Laporkan Insiden" icon="pi pi-plus" /></Link></template>
        </AssetManagementPageHeader>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"><p class="text-xs text-gray-500">Menunggu pemeriksaan</p><p class="mt-1 text-2xl font-semibold text-amber-600">{{ counts.reported }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"><p class="text-xs text-gray-500">Dalam investigasi</p><p class="mt-1 text-2xl font-semibold text-blue-600">{{ counts.investigating }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"><p class="text-xs text-gray-500">Selesai ditangani</p><p class="mt-1 text-2xl font-semibold text-emerald-600">{{ counts.resolved }}</p></div>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <DataTableToolbar v-model="search" :total="incidents.total" total-label="Insiden" placeholder="Cari nomor atau aset..." @search="applyFilters">
                <AppSelect v-model="status" :options="[{ label: 'Semua Status', value: 'ALL' }, { label: 'Dilaporkan', value: 'REPORTED' }, { label: 'Investigasi', value: 'INVESTIGATING' }, { label: 'Selesai', value: 'RESOLVED' }, { label: 'Ditutup', value: 'DISMISSED' } ]" class="w-full sm:w-48" aria-label="Filter status insiden" @change="applyFilters" />
            </DataTableToolbar>
            <CrudTable :columns="columns" :rows="rows" row-key="id" empty-message="Belum ada laporan insiden aset.">
                <template #cell-number="{ row }"><span class="font-semibold text-gray-900 dark:text-white">{{ row.incident.number }}</span><div class="text-xs text-gray-500">{{ row.incident.description }}</div></template>
                <template #cell-asset="{ row }"><span class="font-medium text-gray-900 dark:text-white">{{ row.incident.asset?.number ?? 'Aset tidak tersedia' }}</span><div class="text-xs text-gray-500">{{ row.incident.asset?.product?.name ?? 'Produk tidak tersedia' }}<template v-if="row.incident.asset?.product?.code"> · {{ row.incident.asset.product.code }}</template></div></template>
                <template #cell-incident_type="{ row }"><span>{{ typeLabel(row.incident.incident_type) }}</span><div class="mt-1"><Tag :value="row.incident.severity" :severity="severity(row.incident.severity)" /></div></template>
                <template #cell-occurred_at="{ row }">{{ date(row.incident.occurred_at) }}</template>
                <template #cell-reported_by="{ row }">{{ row.incident.reported_by?.name ?? '-' }}</template>
                <template #cell-status="{ row }"><Tag :value="statusLabel(row.incident.status)" :severity="row.incident.status === 'RESOLVED' ? 'success' : row.incident.status === 'DISMISSED' ? 'secondary' : row.incident.status === 'INVESTIGATING' ? 'info' : 'warn'" /></template>
                <template #actions="{ row }"><Link :href="route('transaction.asset-management.incidents.show', row.incident.id)" class="inline-flex" v-tooltip.top="'View detail insiden'"><Button icon="pi pi-eye" severity="info" text rounded aria-label="View incident" /></Link></template>
            </CrudTable>
            <AppPagination v-if="incidents.total > 0" v-model:per-page="perPage" :meta="incidents" @change="applyFilters" />
        </section>
    </div>
</template>
