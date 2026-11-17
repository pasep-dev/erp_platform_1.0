<script setup lang="ts">
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AppPagination from '@/components/common/AppPagination.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import DataTableToolbar from '@/components/common/DataTableToolbar.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

interface Disposal {
    id: string
    number: string
    disposal_type: string
    status: string
    disposal_date: string
    amount: string | null
    reason: string
    asset: { id: string; number: string; product: { code: string; name: string } }
    requested_by: { name: string } | null
    decided_by: { name: string } | null
}

const props = defineProps<{
    disposals: { data: Disposal[]; total: number; per_page: number; links: { url: string | null; label: string; active: boolean }[] }
    filters: { search: string; status: string }
    counts: { pending: number; approved: number; rejected: number }
    can_create: boolean
}>()

const search = ref(props.filters.search)
const status = ref(props.filters.status || 'ALL')
const perPage = ref(props.disposals.per_page)

function applyFilters(): void {
    router.get(route('transaction.asset-management.disposals.index'), {
        search: search.value || undefined,
        status: status.value !== 'ALL' ? status.value : undefined,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function typeLabel(type: string): string {
    return ({ SOLD: 'Dijual', DONATED: 'Dihibahkan', WRITTEN_OFF: 'Dihapuskan' } as Record<string, string>)[type] ?? type
}

function statusLabel(value: string): string {
    return ({ PENDING_APPROVAL: 'Menunggu Keputusan', APPROVED: 'Disetujui', REJECTED: 'Ditolak' } as Record<string, string>)[value] ?? value
}

function statusSeverity(value: string): 'warn' | 'success' | 'danger' {
    return value === 'APPROVED' ? 'success' : value === 'REJECTED' ? 'danger' : 'warn'
}

function date(value: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(`${value}T00:00:00`))
}

function printRequest(disposal: Disposal): void {
    const printUrl = new URL(route('transaction.asset-management.disposals.print', disposal.id), window.location.origin)
    printUrl.searchParams.set('print', '1')
    printUrl.searchParams.set('closeAfterPrint', '1')
    window.open(printUrl.toString(), '_blank', 'noopener')
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader title="Pelepasan Aset" section="Disposal" description="Kelola pengajuan penjualan, hibah, atau penghapusan aset dengan jejak keputusan.">
            <template #actions><Link v-if="can_create" :href="route('transaction.asset-management.disposals.create')"><Button label="Ajukan Pelepasan" icon="pi pi-plus" severity="warn" /></Link></template>
        </AssetManagementPageHeader>

        <section class="grid gap-3 sm:grid-cols-3"><article class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30"><p class="text-xs text-amber-800 dark:text-amber-300">Menunggu Keputusan</p><p class="mt-2 text-2xl font-semibold text-amber-700">{{ counts.pending }}</p></article><article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30"><p class="text-xs text-emerald-800 dark:text-emerald-300">Disetujui</p><p class="mt-2 text-2xl font-semibold text-emerald-700">{{ counts.approved }}</p></article><article class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30"><p class="text-xs text-red-800 dark:text-red-300">Ditolak</p><p class="mt-2 text-2xl font-semibold text-red-700">{{ counts.rejected }}</p></article></section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <DataTableToolbar v-model="search" :total="disposals.total" total-label="Pelepasan" placeholder="Cari nomor, aset, produk, atau alasan..." @search="applyFilters">
                <AppSelect v-model="status" :options="[{ label: 'Semua Status', value: 'ALL' }, { label: 'Menunggu Keputusan', value: 'PENDING_APPROVAL' }, { label: 'Disetujui', value: 'APPROVED' }, { label: 'Ditolak', value: 'REJECTED' }]" class="w-full sm:w-52" aria-label="Filter status pelepasan" @change="applyFilters" />
            </DataTableToolbar>
            <div class="overflow-x-auto"><table class="w-full min-w-[850px] text-left text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-4 py-3">Nomor</th><th class="px-4 py-3">Aset</th><th class="px-4 py-3">Jenis</th><th class="px-4 py-3">Tanggal Pelepasan</th><th class="px-4 py-3">Nilai</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Diajukan oleh</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800"><tr v-for="disposal in disposals.data" :key="disposal.id" class="text-gray-700 dark:text-gray-200"><td class="px-4 py-3"><Link :href="route('transaction.asset-management.disposals.show', disposal.id)" class="font-semibold text-purple-700 hover:underline dark:text-purple-300">{{ disposal.number }}</Link></td><td class="px-4 py-3"><div class="font-medium">{{ disposal.asset.number }}</div><div class="text-xs text-gray-500">{{ disposal.asset.product.name }} · {{ disposal.asset.product.code }}</div></td><td class="px-4 py-3">{{ typeLabel(disposal.disposal_type) }}</td><td class="px-4 py-3">{{ date(disposal.disposal_date) }}</td><td class="px-4 py-3">{{ disposal.amount ? `Rp ${Number(disposal.amount).toLocaleString('id-ID')}` : '-' }}</td><td class="px-4 py-3"><Tag :value="statusLabel(disposal.status)" :severity="statusSeverity(disposal.status)" /></td><td class="px-4 py-3">{{ disposal.requested_by?.name ?? '-' }}</td><td class="px-4 py-3 text-right"><Button v-if="disposal.status === 'PENDING_APPROVAL'" icon="pi pi-print" severity="secondary" text rounded aria-label="Cetak pengajuan" v-tooltip.top="'Cetak pengajuan'" @click="printRequest(disposal)" /><Link :href="route('transaction.asset-management.disposals.show', disposal.id)"><Button icon="pi pi-eye" severity="secondary" text rounded aria-label="Lihat detail" v-tooltip.top="'Lihat detail pelepasan'" /></Link></td></tr><tr v-if="disposals.data.length === 0"><td colspan="8" class="px-4 py-12 text-center text-sm text-gray-500">Belum ada pengajuan pelepasan aset.</td></tr></tbody></table></div>
            <AppPagination v-if="disposals.total > 0" v-model:per-page="perPage" :meta="disposals" @change="applyFilters" />
        </section>
    </div>
</template>
