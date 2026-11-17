<script setup lang="ts">
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AppPagination from '@/components/common/AppPagination.vue'
import DataTableToolbar from '@/components/common/DataTableToolbar.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

interface Transfer {
    id: string
    number: string
    effective_at: string
    reason: string
    from_snapshot: Record<string, string | number | null>
    to_snapshot: Record<string, string | number | null>
    asset: { id: string; number: string; product: { code: string; name: string } }
    performed_by: { name: string } | null
}

const props = defineProps<{
    transfers: { data: Transfer[]; total: number; per_page: number; links: { url: string | null; label: string; active: boolean }[] }
    filters: { search: string }
    can_manage: boolean
}>()

const search = ref(props.filters.search)
const perPage = ref(props.transfers.per_page)

function applyFilters(): void {
    router.get(route('transaction.asset-management.transfers.index'), {
        search: search.value || undefined,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function date(value: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(`${value}T00:00:00`))
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader title="Perpindahan Aset" section="Transfer" description="Riwayat perubahan perusahaan, departemen, cabang, dan lokasi aset.">
            <template #actions><Link v-if="can_manage" :href="route('transaction.asset-management.transfers.create')"><Button label="Pindahkan Aset" icon="pi pi-arrows-alt" /></Link></template>
        </AssetManagementPageHeader>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <DataTableToolbar v-model="search" :total="transfers.total" total-label="Perpindahan" placeholder="Cari nomor, aset, produk, atau alasan..." @search="applyFilters" />
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-4 py-3">Nomor / Tanggal</th><th class="px-4 py-3">Aset</th><th class="px-4 py-3">Dari</th><th class="px-4 py-3">Ke</th><th class="px-4 py-3">Alasan</th><th class="px-4 py-3">Dicatat oleh</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="transfer in transfers.data" :key="transfer.id" class="text-gray-700 dark:text-gray-200">
                            <td class="px-4 py-3"><Link :href="route('transaction.asset-management.transfers.show', transfer.id)" class="font-semibold text-purple-700 hover:underline dark:text-purple-300">{{ transfer.number }}</Link><div class="mt-1 text-xs text-gray-500">{{ date(transfer.effective_at) }}</div></td>
                            <td class="px-4 py-3"><div class="font-medium">{{ transfer.asset.number }}</div><div class="text-xs text-gray-500">{{ transfer.asset.product.name }} · {{ transfer.asset.product.code }}</div></td>
                            <td class="px-4 py-3 text-xs">{{ transfer.from_snapshot.company }}<div class="mt-1 text-gray-500">{{ transfer.from_snapshot.department }} · {{ transfer.from_snapshot.location }}</div></td>
                            <td class="px-4 py-3 text-xs">{{ transfer.to_snapshot.company }}<div class="mt-1 text-gray-500">{{ transfer.to_snapshot.department }} · {{ transfer.to_snapshot.location }}</div></td>
                            <td class="max-w-64 px-4 py-3"><p class="line-clamp-2 text-xs">{{ transfer.reason }}</p></td>
                            <td class="px-4 py-3 text-xs">{{ transfer.performed_by?.name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right"><Link :href="route('transaction.asset-management.transfers.show', transfer.id)"><Button icon="pi pi-eye" text rounded severity="secondary" aria-label="Lihat detail" v-tooltip.top="'Lihat detail perpindahan'" /></Link></td>
                        </tr>
                        <tr v-if="transfers.data.length === 0"><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500">Belum ada riwayat perpindahan aset.</td></tr>
                    </tbody>
                </table>
            </div>
            <AppPagination v-if="transfers.total > 0" v-model:per-page="perPage" :meta="transfers" @change="applyFilters" />
        </section>
    </div>
</template>
