<script setup lang="ts">
import { onMounted } from 'vue'
import { route } from 'ziggy-js'
import Button from 'primevue/button'

interface DisposalSnapshot {
    number: string
    product_code: string | null
    product_name: string | null
    serial_number: string | null
    condition: string | null
    company: string | null
    department: string | null
    branch: string | null
    location: string | null
    acquisition_date: string | null
    acquisition_cost: string | number | null
}

interface DisposalPrintRecord {
    id: string
    number: string
    disposal_type: string
    status: string
    disposal_date: string
    amount: string | number | null
    reason: string
    evidence_path: string | null
    created_at: string
    asset_snapshot: DisposalSnapshot
    requested_by: { name: string } | null
}

const props = defineProps<{
    disposal: DisposalPrintRecord
    auto_print: boolean
    close_after_print: boolean
}>()

function typeLabel(value: string): string {
    return ({ SOLD: 'Dijual', DONATED: 'Dihibahkan', WRITTEN_OFF: 'Dihapuskan' } as Record<string, string>)[value] ?? value
}

function date(value: string | null): string {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(new Date(`${value.slice(0, 10)}T00:00:00`)) : '-'
}

function money(value: string | number | null): string {
    return value === null ? '-' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value))
}

function printDocument(): void {
    window.print()
}

function goBack(): void {
    window.history.back()
}

function closePrintTab(): void {
    if (window.opener) {
        window.close()
    }

    window.setTimeout(() => {
        if (!window.closed) {
            window.location.replace('about:blank')
        }
    }, 250)
}

onMounted(() => {
    const parameters = new URLSearchParams(window.location.search)

    if (props.auto_print || parameters.get('print') === '1') {
        if (props.close_after_print || parameters.get('closeAfterPrint') === '1') {
            window.addEventListener('afterprint', closePrintTab, { once: true })
        }

        window.setTimeout(printDocument, 300)
    }
})
</script>

<template>
    <main class="mx-auto min-h-screen max-w-4xl bg-white p-6 text-gray-900 print:min-h-0 print:p-0">
        <div class="mb-5 flex justify-end gap-2 print:hidden">
            <Button label="Kembali" severity="secondary" outlined @click="goBack" />
            <Button label="Cetak / Simpan PDF" icon="pi pi-print" @click="printDocument" />
        </div>

        <article class="disposal-document space-y-5 border border-gray-300 p-8 text-[11px] leading-relaxed">
            <header class="border-b-2 border-purple-900 pb-4 text-center">
                <p class="text-xs font-bold uppercase tracking-[0.25em] text-purple-900">Asset Management</p>
                <h1 class="mt-2 text-2xl font-bold uppercase tracking-wide">Pengajuan Pelepasan Aset</h1>
                <p class="mt-1 text-sm font-semibold">{{ disposal.number }}</p>
                <p class="mt-1 text-[10px] text-gray-500">Dokumen pengajuan untuk ditinjau dan ditandatangani oleh management / direktur.</p>
            </header>

            <section class="grid grid-cols-2 gap-x-8 gap-y-2">
                <p><strong>Status:</strong> Menunggu Keputusan</p>
                <p><strong>Jenis pelepasan:</strong> {{ typeLabel(disposal.disposal_type) }}</p>
                <p><strong>Tanggal pengajuan:</strong> {{ date(disposal.created_at ?? disposal.disposal_date) }}</p>
                <p><strong>Tanggal pelepasan yang diusulkan:</strong> {{ date(disposal.disposal_date) }}</p>
                <p><strong>Diajukan oleh:</strong> {{ disposal.requested_by?.name ?? '-' }}</p>
                <p><strong>Nilai hasil pelepasan:</strong> {{ money(disposal.amount) }}</p>
            </section>

            <section class="overflow-hidden rounded border border-gray-400">
                <h2 class="bg-purple-950 px-3 py-2 font-bold uppercase text-white">Identitas Aset</h2>
                <div class="grid grid-cols-2 gap-x-8 gap-y-2 p-3">
                    <p><strong>Kode aset:</strong> {{ disposal.asset_snapshot.number }}</p>
                    <p><strong>Produk:</strong> {{ disposal.asset_snapshot.product_code ?? '-' }} · {{ disposal.asset_snapshot.product_name ?? '-' }}</p>
                    <p><strong>Serial number:</strong> {{ disposal.asset_snapshot.serial_number ?? 'Tidak ada' }}</p>
                    <p><strong>Kondisi:</strong> {{ disposal.asset_snapshot.condition ?? '-' }}</p>
                    <p><strong>Perusahaan:</strong> {{ disposal.asset_snapshot.company ?? '-' }}</p>
                    <p><strong>Departemen:</strong> {{ disposal.asset_snapshot.department ?? '-' }}</p>
                    <p><strong>Cabang:</strong> {{ disposal.asset_snapshot.branch ?? '-' }}</p>
                    <p><strong>Lokasi:</strong> {{ disposal.asset_snapshot.location ?? '-' }}</p>
                    <p><strong>Tanggal perolehan:</strong> {{ date(disposal.asset_snapshot.acquisition_date) }}</p>
                    <p><strong>Nilai perolehan:</strong> {{ money(disposal.asset_snapshot.acquisition_cost) }}</p>
                </div>
            </section>

            <section class="space-y-2">
                <h2 class="font-bold uppercase text-purple-950">Alasan dan keterangan pengajuan</h2>
                <p class="min-h-20 whitespace-pre-line rounded border border-gray-400 p-3">{{ disposal.reason }}</p>
                <p v-if="disposal.evidence_path" class="text-[10px] text-gray-600">Dokumen pendukung telah dilampirkan pada sistem untuk referensi peninjau.</p>
            </section>

            <section class="break-inside-avoid pt-4">
                <h2 class="mb-2 text-center font-bold uppercase text-purple-950">Keputusan Pelepasan Aset</h2>
                <table class="w-full border-collapse text-center">
                    <thead><tr><th class="border border-gray-700 bg-gray-100 p-2">Pemohon</th><th class="border border-gray-700 bg-gray-100 p-2">Management / Direktur</th></tr></thead>
                    <tbody><tr><td class="h-28 border border-gray-700 p-2 align-bottom"><span class="font-semibold">{{ disposal.requested_by?.name ?? '-' }}</span><br /><span class="text-[9px] text-gray-500">Nama dan tanggal</span></td><td class="h-28 border border-gray-700 p-2 align-bottom"><span class="font-semibold">Setuju / Tidak Setuju</span><br /><span class="text-[9px] text-gray-500">Nama, tanda tangan, dan tanggal</span></td></tr></tbody>
                </table>
            </section>

            <footer class="border-t border-gray-300 pt-3 text-[9px] text-gray-500">Dokumen ini merupakan lembar pengajuan. Keputusan final dicatat kembali di sistem agar status aset dan riwayat pelepasan diperbarui.</footer>
        </article>
    </main>
</template>

<style>
@page { size: A4; margin: 12mm; }
@media print {
    body { background: #fff !important; }
    .disposal-document { border: 0 !important; padding: 0 !important; }
}
</style>
