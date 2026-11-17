<script setup lang="ts">
import Button from 'primevue/button'

interface AuditItem {
    id: string
    result: string
    expected_snapshot: { number: string; serial_number: string | null; product: string | null; product_code: string | null; company: string | null; department: string | null; location: string | null; condition: string }
    observed_location: { name: string } | null
    observed_condition: string | null
    remarks: string | null
    resolution_remarks: string | null
    resolved_by: { name: string } | null
}

const props = defineProps<{
    audit: { number: string; status: string; scope_snapshot: Record<string, string | null>; started_at: string; completed_at: string | null; remarks: string | null; created_by: { name: string } | null; completed_by: { name: string } | null }
    items: AuditItem[]
}>()

function date(value: string | null): string {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(value)) : '-'
}

function resultLabel(value: string): string {
    return ({ PENDING: 'Belum Diperiksa', FOUND: 'Sesuai', MOVED: 'Berpindah Lokasi', DAMAGED: 'Rusak', MISSING: 'Tidak Ditemukan' } as Record<string, string>)[value] ?? value
}

function printReport(): void {
    window.print()
}
</script>

<template>
    <main class="mx-auto min-h-screen max-w-6xl bg-white p-6 text-gray-900 print:min-h-0 print:p-0">
        <div class="mb-5 flex justify-end print:hidden"><Button label="Cetak / Simpan PDF" icon="pi pi-print" @click="printReport" /></div>
        <article class="audit-report space-y-5 border border-gray-300 p-8 text-[11px] leading-relaxed">
            <header class="border-b-2 border-purple-900 pb-4 text-center"><p class="text-xs font-bold uppercase tracking-[0.25em] text-purple-900">Asset Management</p><h1 class="mt-2 text-2xl font-bold uppercase">Laporan Audit Aset</h1><p class="mt-1 text-sm font-semibold">{{ audit.number }}</p></header>
            <section class="grid grid-cols-2 gap-x-8 gap-y-2"><p><strong>Status:</strong> {{ audit.status === 'COMPLETED' ? 'Selesai' : 'Sedang Berjalan' }}</p><p><strong>Mulai:</strong> {{ date(audit.started_at) }}</p><p><strong>Selesai:</strong> {{ date(audit.completed_at) }}</p><p><strong>Pelaksana:</strong> {{ audit.created_by?.name ?? '-' }}</p><p class="col-span-2"><strong>Cakupan:</strong> {{ Object.values(audit.scope_snapshot).filter(Boolean).join(' · ') || 'Semua aset aktif' }}</p><p v-if="audit.remarks" class="col-span-2"><strong>Catatan sesi:</strong> {{ audit.remarks }}</p></section>
            <div class="overflow-hidden rounded border border-gray-400"><table class="w-full border-collapse text-left"><thead><tr class="bg-purple-950 text-white"><th class="border border-gray-500 p-2">No.</th><th class="border border-gray-500 p-2">Aset / Serial</th><th class="border border-gray-500 p-2">Lokasi</th><th class="border border-gray-500 p-2">Hasil</th><th class="border border-gray-500 p-2">Catatan / Tinjauan</th></tr></thead><tbody><tr v-for="(item, index) in items" :key="item.id"><td class="border border-gray-500 p-2">{{ index + 1 }}</td><td class="border border-gray-500 p-2"><strong>{{ item.expected_snapshot.number }}</strong><br />{{ item.expected_snapshot.product_code }} · {{ item.expected_snapshot.product }}<br />SN {{ item.expected_snapshot.serial_number ?? '-' }}</td><td class="border border-gray-500 p-2">{{ item.expected_snapshot.location ?? '-' }}<template v-if="item.result === 'MOVED'"> → {{ item.observed_location?.name ?? '-' }}</template></td><td class="border border-gray-500 p-2">{{ resultLabel(item.result) }}<template v-if="item.observed_condition"> · {{ item.observed_condition }}</template></td><td class="border border-gray-500 p-2">{{ item.remarks ?? '-' }}<p v-if="item.resolution_remarks" class="mt-1"><strong>Resolusi:</strong> {{ item.resolution_remarks }} · {{ item.resolved_by?.name ?? '-' }}</p></td></tr></tbody></table></div>
            <footer class="border-t border-gray-300 pt-3 text-[9px] text-gray-500">Laporan dicetak dari Asset Management · {{ audit.number }}</footer>
        </article>
    </main>
</template>

<style>
@page { size: A4 landscape; margin: 10mm; }
@media print {
    body { background: #fff !important; }
    .audit-report { border: 0 !important; padding: 0 !important; }
    tr { break-inside: avoid; }
}
</style>
