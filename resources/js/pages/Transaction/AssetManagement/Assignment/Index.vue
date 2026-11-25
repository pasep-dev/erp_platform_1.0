<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
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

interface Asset {
    id: string
    number: string
    serial_number: string | null
    status: string
    product: { code: string; name: string } | null
    company: { code: string; name: string } | null
    department: { code: string; name: string } | null
    location: { code: string; name: string } | null
    custodian: { name: string; email: string } | null
}

const props = defineProps<{
    assets: { data: Asset[]; total: number; per_page: number; links: { url: string | null; label: string; active: boolean }[] }
    filters: { search: string; assignment_status: string }
    can_manage: boolean
}>()

const search = ref(props.filters.search)
const assignmentStatus = ref(props.filters.assignment_status || 'ALL')
const perPage = ref(props.assets.per_page)

const columns = [
    { key: 'number', label: 'Kode Aset' },
    { key: 'product', label: 'Produk' },
    { key: 'placement', label: 'Penempatan' },
    { key: 'custodian', label: 'Penanggung Jawab' },
    { key: 'assignment_status', label: 'Penugasan' },
]

const rows = computed(() => props.assets.data.map((asset) => ({
    id: asset.id,
    asset,
    number: asset.number,
    product: asset.product,
    placement: [asset.company?.name, asset.department?.name, asset.location?.name].filter(Boolean).join(' · ') || 'Penempatan tidak tersedia',
    custodian: asset.custodian,
    assignment_status: asset.custodian ? 'Assigned' : 'Available',
})))

function applyFilters(): void {
    router.get(route('transaction.asset-management.assignments.index'), {
        search: search.value || undefined,
        assignment_status: assignmentStatus.value !== 'ALL' ? assignmentStatus.value : undefined,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function changePerPage(): void {
    applyFilters()
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader title="Asset Assignment" section="Assignment" description="Catat penugasan aset kepada pengguna, perpindahan penanggung jawab, dan pengembalian aset." />

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <DataTableToolbar v-model="search" :total="assets.total" total-label="Aset" placeholder="Kode, produk, atau penanggung jawab..." @search="applyFilters">
                <AppSelect v-model="assignmentStatus" :options="[{ label: 'Semua Penugasan', value: 'ALL' }, { label: 'Available', value: 'AVAILABLE' }, { label: 'Assigned', value: 'ASSIGNED' }]" class="w-full sm:w-48" aria-label="Filter status penugasan" @change="applyFilters" />
            </DataTableToolbar>

            <CrudTable :columns="columns" :rows="rows" row-key="id" empty-message="Belum ada aset di Asset Directory.">
                <template #cell-number="{ row }"><span class="font-semibold text-gray-900 dark:text-white">{{ row.number }}</span><div v-if="row.asset.serial_number" class="text-xs text-gray-500">SN {{ row.asset.serial_number }}</div></template>
                <template #cell-product="{ row }"><div class="font-medium text-gray-900 dark:text-white">{{ row.product?.name ?? 'Produk tidak tersedia' }}</div><div v-if="row.product?.code" class="text-xs text-gray-500">{{ row.product.code }}</div></template>
                <template #cell-placement="{ row }"><span class="text-xs">{{ row.placement }}</span></template>
                <template #cell-custodian="{ row }"><div v-if="row.custodian" class="font-medium">{{ row.custodian.name }}</div><span v-else class="text-sm text-gray-500">Belum ditugaskan</span><div v-if="row.custodian" class="text-xs text-gray-500">{{ row.custodian.email }}</div></template>
                <template #cell-assignment_status="{ row }"><Tag :value="row.assignment_status" :severity="row.assignment_status === 'Assigned' ? 'info' : 'success'" /></template>
                <template #actions="{ row }">
                    <Link :href="route('transaction.asset-management.assignments.show', row.asset.id)" class="inline-flex" aria-label="Riwayat assignment aset" v-tooltip.top="'View detail dan riwayat assignment'">
                        <Button icon="pi pi-eye" severity="info" text rounded aria-label="View" />
                    </Link>
                    <Link v-if="can_manage && row.asset.status === 'ACTIVE'" :href="route('transaction.asset-management.assignments.create', row.asset.id)" class="inline-flex" :aria-label="row.custodian ? 'Transfer aset' : 'Assign aset'" v-tooltip.top="row.custodian ? 'Transfer penanggung jawab' : 'Assign aset'">
                        <Button :icon="row.custodian ? 'pi pi-sync' : 'pi pi-user-plus'" severity="success" text rounded :aria-label="row.custodian ? 'Transfer' : 'Assign'" />
                    </Link>
                </template>
            </CrudTable>
            <AppPagination v-if="assets.total > 0" v-model:per-page="perPage" :meta="assets" @change="changePerPage" />
        </section>
    </div>
</template>
