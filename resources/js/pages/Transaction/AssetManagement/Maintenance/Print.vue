<script setup lang="ts">
import { onMounted } from 'vue'
import { route } from 'ziggy-js'
import Button from 'primevue/button'
import PrintDocumentHeader from '@/components/print/PrintDocumentHeader.vue'

interface MaintenanceComponent {
    id: string
    component_name: string
    quantity: string
    estimated_unit_cost: string
    needed_at: string
}

interface MaintenancePrintRecord {
    number: string
    maintenance_type: string
    execution_mode: string
    priority: string
    requested_at: string
    issue_description: string
    reported_condition: string
    inspection_result: string | null
    estimated_cost: string | null
    components: MaintenanceComponent[]
    scheduled_at: string | null
    asset: {
        number: string
        serial_number: string | null
        product: { code: string; name: string }
        company: { code: string; name: string }
        department: { code: string; name: string }
        location: { code: string; name: string } | null
        branch: { code: string; name: string } | null
    }
    vendor: { code: string; name: string; phone: string | null; email: string | null; address: string | null } | null
    requested_by: { name: string } | null
}

interface LatestService {
    id: string
    number: string
    maintenance_type: string
    completed_at: string
    work_summary: string | null
}

const props = defineProps<{
    maintenance: MaintenancePrintRecord
    latest_service: LatestService | null
    auto_print: boolean
    close_after_print: boolean
}>()

function date(value: string | null): string {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(new Date(`${value.slice(0, 10)}T00:00:00`)) : '-'
}

function money(value: string | null): string {
    return value === null ? 'Rp 0' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value))
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

    if (parameters.get('print') === '1') {
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

        <article class="print-document space-y-3 border border-gray-300 p-5 text-[11px] leading-snug">
            <PrintDocumentHeader domain="asset-management" title="Maintenance Request" :number="maintenance.number" :company-name="maintenance.asset.company.name" :date-label="`Tanggal pengajuan · ${date(maintenance.requested_at)}`" />

            <section class="grid grid-cols-2 gap-x-8 gap-y-2">
                <p><strong>Tanggal pengajuan:</strong> {{ date(maintenance.requested_at) }}</p>
                <p><strong>Pelaksana:</strong> {{ maintenance.execution_mode === 'INTERNAL' ? 'Tim Internal IT / GA' : 'Vendor Eksternal' }}</p>
                <p><strong>Pemohon:</strong> {{ maintenance.requested_by?.name ?? '-' }}</p>
                <p><strong>Jenis:</strong> {{ maintenance.maintenance_type === 'PREVENTIVE' ? 'Preventive Maintenance' : 'Corrective / Perbaikan' }}</p>
                <p><strong>Prioritas:</strong> {{ maintenance.priority }}</p>
                <p><strong>Jadwal yang diusulkan:</strong> {{ date(maintenance.scheduled_at) }}</p>
            </section>

            <section class="overflow-hidden rounded border border-gray-400">
                <h2 class="border-b border-gray-300 px-3 py-1.5 text-xs font-bold text-purple-900">Identitas aset</h2>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1.5 p-2">
                    <p><strong>Kode aset:</strong> {{ maintenance.asset.number }}</p>
                    <p><strong>Produk:</strong> {{ maintenance.asset.product.code }} · {{ maintenance.asset.product.name }}</p>
                    <p><strong>Serial number:</strong> {{ maintenance.asset.serial_number ?? 'Tidak ada' }}</p>
                    <p><strong>Perusahaan:</strong> {{ maintenance.asset.company.code }} · {{ maintenance.asset.company.name }}</p>
                    <p><strong>Departemen:</strong> {{ maintenance.asset.department.code }} · {{ maintenance.asset.department.name }}</p>
                    <p><strong>Lokasi / cabang:</strong> {{ maintenance.asset.location?.name ?? '-' }} / {{ maintenance.asset.branch?.name ?? '-' }}</p>
                </div>
            </section>

            <section class="break-inside-avoid rounded border border-gray-400 px-3 py-2">
                <h2 class="text-xs font-bold text-purple-900">Maintenance / service terakhir</h2>
                <div v-if="latest_service" class="mt-1 grid grid-cols-3 gap-2">
                    <p><strong>Nomor:</strong> {{ latest_service.number }}</p>
                    <p><strong>Tanggal selesai:</strong> {{ date(latest_service.completed_at) }}</p>
                    <p><strong>Jenis:</strong> {{ latest_service.maintenance_type === 'PREVENTIVE' ? 'Preventive' : 'Corrective' }}</p>
                    <p class="col-span-3"><strong>Hasil:</strong> {{ latest_service.work_summary || '-' }}</p>
                </div>
                <p v-else class="mt-1">-</p>
            </section>

            <section class="space-y-3">
                <div><h2 class="text-xs font-bold text-purple-900">Keluhan / kebutuhan</h2><p class="mt-1 whitespace-pre-line">{{ maintenance.issue_description }}</p></div>
                <div><h2 class="text-xs font-bold text-purple-900">Hasil pemeriksaan awal</h2><p class="mt-1 whitespace-pre-line">{{ maintenance.inspection_result || 'Belum dicatat.' }}</p></div>
                <div class="grid grid-cols-2 gap-2">
                    <p><strong>Kondisi saat diperiksa:</strong> {{ maintenance.reported_condition }}</p>
                    <p><strong>Pelaksana:</strong> {{ maintenance.execution_mode === 'INTERNAL' ? 'Tim Internal IT / GA' : 'Vendor Eksternal' }}</p>
                </div>
            </section>

            <section class="overflow-hidden rounded border border-gray-400">
                <h2 class="border-b border-gray-300 px-3 py-1.5 text-xs font-bold text-purple-900">{{ maintenance.execution_mode === 'INTERNAL' ? 'Estimasi biaya' : 'Vendor dan estimasi biaya' }}</h2>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1.5 p-2">
                    <p v-if="maintenance.execution_mode === 'VENDOR'"><strong>Vendor:</strong> {{ maintenance.vendor?.name ?? '-' }}</p>
                    <p v-if="maintenance.execution_mode === 'VENDOR'"><strong>Kontak vendor:</strong> {{ maintenance.vendor?.phone ?? maintenance.vendor?.email ?? '-' }}</p>
                    <p v-if="maintenance.execution_mode === 'VENDOR'"><strong>Alamat vendor:</strong> {{ maintenance.vendor?.address ?? '-' }}</p>
                    <p><strong>Total estimasi:</strong> {{ money(maintenance.estimated_cost) }}</p>
                </div>
            </section>

            <section v-if="maintenance.execution_mode === 'INTERNAL'" class="overflow-hidden rounded border border-gray-400">
                <h2 class="border-b border-gray-300 px-3 py-1.5 text-xs font-bold text-purple-900">Komponen yang dibutuhkan</h2>
                <table v-if="maintenance.components.length" class="w-full border-collapse text-left">
                    <thead><tr class="bg-gray-100"><th class="border border-gray-500 p-1">Komponen</th><th class="border border-gray-500 p-1">Jumlah</th><th class="border border-gray-500 p-1">Harga Satuan</th><th class="border border-gray-500 p-1">Tanggal Dibutuhkan</th><th class="border border-gray-500 p-1">Subtotal</th></tr></thead>
                    <tbody><tr v-for="component in maintenance.components" :key="component.id"><td class="border border-gray-500 p-1">{{ component.component_name }}</td><td class="border border-gray-500 p-1">{{ Number(component.quantity) }}</td><td class="border border-gray-500 p-1">{{ money(component.estimated_unit_cost) }}</td><td class="border border-gray-500 p-1">{{ date(component.needed_at) }}</td><td class="border border-gray-500 p-1">{{ money(String(Number(component.quantity) * Number(component.estimated_unit_cost))) }}</td></tr></tbody>
                </table>
                <p v-else class="p-3">Tidak ada komponen pembelian yang dicatat untuk request ini.</p>
            </section>

            <section class="break-inside-avoid">
                <h2 class="mb-2 text-center text-xs font-bold text-purple-900">Keputusan persetujuan</h2>
                <table class="w-full border-collapse text-center">
                    <thead><tr><th class="border border-gray-700 bg-gray-100 p-2">Petugas Maintenance</th><th class="border border-gray-700 bg-gray-100 p-2">Management / Pemberi Persetujuan</th></tr></thead>
                    <tbody><tr><td class="h-16 border border-gray-700 p-2 align-bottom"><span class="font-semibold">{{ maintenance.requested_by?.name ?? '-' }}</span><br /><span class="text-[9px] text-gray-500">Pemohon / pencatat</span></td><td class="h-16 border border-gray-700 p-2 align-bottom"><span class="font-semibold">Tanda tangan dan tanggal persetujuan</span></td></tr></tbody>
                </table>
            </section>

            <footer class="border-t border-gray-300 pt-3 text-[9px] text-gray-500">Dokumen ini digunakan untuk persetujuan manual atas rencana perbaikan dan estimasi biaya. Hasil pengerjaan dan biaya aktual dicatat setelah service selesai.</footer>
        </article>
    </main>
</template>

<style>
@page { size: A4; margin: 8mm; }
@media print {
    body { background: #fff !important; }
    .print-document { border: 0 !important; padding: 0 !important; }
    .print-document > * { break-inside: avoid-page; }
}
</style>
