<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

interface Snapshot { company?: string; department?: string; branch?: string | null; location?: string; custodian?: string | null }
interface Transfer {
    id: string; number: string; effective_at: string; reason: string; from_snapshot: Snapshot; to_snapshot: Snapshot
    asset: { id: string; number: string; product: { code: string; name: string } }; performed_by: { name: string } | null
}
const props = defineProps<{ transfer: Transfer }>()

function date(value: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(new Date(`${value}T00:00:00`))
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader :title="transfer.number" section="Transfer" :description="`Tanggal efektif ${date(transfer.effective_at)}`">
            <template #actions><Link :href="route('transaction.asset-management.assignments.show', transfer.asset.id)"><Button label="Detail Aset" severity="secondary" outlined icon="pi pi-box" /></Link><Link :href="route('transaction.asset-management.transfers.index')"><Button label="Kembali" severity="secondary" outlined /></Link></template>
        </AssetManagementPageHeader>
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><p class="text-xs font-medium uppercase tracking-wide text-gray-500">Aset yang dipindahkan</p><p class="mt-1 text-lg font-semibold text-purple-800 dark:text-purple-300">{{ transfer.asset.number }}</p><p class="text-sm text-gray-600 dark:text-gray-300">{{ transfer.asset.product.name }} · {{ transfer.asset.product.code }}</p></section>
        <section class="grid gap-4 md:grid-cols-2">
            <article class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Penempatan asal</p><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-xs text-gray-500">Perusahaan</dt><dd class="font-medium">{{ transfer.from_snapshot.company }}</dd></div><div><dt class="text-xs text-gray-500">Departemen</dt><dd class="font-medium">{{ transfer.from_snapshot.department }}</dd></div><div><dt class="text-xs text-gray-500">Cabang</dt><dd class="font-medium">{{ transfer.from_snapshot.branch ?? 'Tidak terkait cabang' }}</dd></div><div><dt class="text-xs text-gray-500">Lokasi</dt><dd class="font-medium">{{ transfer.from_snapshot.location }}</dd></div></dl></article>
            <article class="rounded-xl border border-purple-200 bg-purple-50/50 p-5 dark:border-purple-900 dark:bg-purple-950/20"><p class="text-xs font-semibold uppercase tracking-wide text-purple-700 dark:text-purple-300">Penempatan tujuan</p><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-xs text-gray-500">Perusahaan</dt><dd class="font-medium">{{ transfer.to_snapshot.company }}</dd></div><div><dt class="text-xs text-gray-500">Departemen</dt><dd class="font-medium">{{ transfer.to_snapshot.department }}</dd></div><div><dt class="text-xs text-gray-500">Cabang</dt><dd class="font-medium">{{ transfer.to_snapshot.branch ?? 'Tidak terkait cabang' }}</dd></div><div><dt class="text-xs text-gray-500">Lokasi</dt><dd class="font-medium">{{ transfer.to_snapshot.location }}</dd></div></dl></article>
        </section>
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><h2 class="font-semibold text-gray-900 dark:text-white">Alasan dan pencatatan</h2><p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-200">{{ transfer.reason }}</p><div class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-gray-800">Dicatat oleh {{ transfer.performed_by?.name ?? '-' }} · Penanggung jawab tetap: {{ transfer.to_snapshot.custodian ?? 'Available' }}</div></section>
    </div>
</template>
