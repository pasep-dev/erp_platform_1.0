<script setup lang="ts">
import { ref } from 'vue'
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

interface AuditSession {
    id: string
    number: string
    status: string
    scope_snapshot: Record<string, string | null>
    started_at: string
    completed_at: string | null
    items_count: number
    pending_count: number
    discrepancy_count: number
    created_by: { id: number; name: string } | null
}

const props = defineProps<{
    sessions: { data: AuditSession[]; total: number; per_page: number; links: { url: string | null; label: string; active: boolean }[] }
    filters: { search: string; status: string }
    can_create: boolean
}>()

const search = ref(props.filters.search)
const status = ref(props.filters.status || 'ALL')
const perPage = ref(props.sessions.per_page)

function applyFilters(): void {
    router.get(route('transaction.asset-management.audits.index'), {
        search: search.value || undefined,
        status: status.value !== 'ALL' ? status.value : undefined,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function date(value: string | null): string {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value)) : '-'
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader title="Audit / Stock Opname" section="Audit" description="Periksa keberadaan, kondisi, dan penempatan aset melalui QR atau pencarian kode aset.">
            <template #actions><Link v-if="can_create" :href="route('transaction.asset-management.audits.create')"><Button label="Buat Sesi Audit" icon="pi pi-plus" /></Link></template>
        </AssetManagementPageHeader>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <DataTableToolbar v-model="search" :total="sessions.total" total-label="Sesi Audit" placeholder="Cari nomor sesi audit..." @search="applyFilters">
                <AppSelect v-model="status" :options="[{ label: 'Semua Status', value: 'ALL' }, { label: 'Sedang Berjalan', value: 'IN_PROGRESS' }, { label: 'Selesai', value: 'COMPLETED' }]" class="w-full sm:w-48" aria-label="Filter status audit" @change="applyFilters" />
            </DataTableToolbar>

            <CrudTable :columns="[{ key: 'number', label: 'Nomor Audit' }, { key: 'scope', label: 'Cakupan' }, { key: 'progress', label: 'Progres' }, { key: 'started_at', label: 'Mulai' }, { key: 'status', label: 'Status' }]" :rows="sessions.data.map((session) => ({ id: session.id, session, number: session.number, scope: Object.values(session.scope_snapshot).filter(Boolean).join(' · ') || 'Semua aset aktif', progress: `${session.items_count - session.pending_count} / ${session.items_count}`, started_at: date(session.started_at), status: session.status }))" row-key="id" empty-message="Belum ada sesi audit.">
                <template #cell-number="{ row }"><span class="font-semibold text-gray-900 dark:text-white">{{ row.number }}</span><div class="text-xs text-gray-500">Dibuat {{ row.session.created_by?.name ?? '-' }}</div></template>
                <template #cell-progress="{ row }"><span>{{ row.progress }}</span><span v-if="row.session.discrepancy_count" class="ml-2 text-xs font-medium text-amber-700">{{ row.session.discrepancy_count }} selisih</span></template>
                <template #cell-status="{ row }"><Tag :value="row.session.status === 'IN_PROGRESS' ? 'Sedang Berjalan' : 'Selesai'" :severity="row.session.status === 'IN_PROGRESS' ? 'info' : 'success'" /></template>
                <template #actions="{ row }"><Link :href="route('transaction.asset-management.audits.show', row.session.id)" class="inline-flex" v-tooltip.top="'Lihat dan proses audit'"><Button icon="pi pi-eye" severity="info" text rounded aria-label="View audit" /></Link></template>
            </CrudTable>
            <AppPagination v-if="sessions.total > 0" v-model:per-page="perPage" :meta="sessions" @change="applyFilters" />
        </section>
    </div>
</template>
