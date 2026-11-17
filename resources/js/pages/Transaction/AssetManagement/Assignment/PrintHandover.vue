<script setup lang="ts">
import { onMounted } from 'vue'
import Button from 'primevue/button'

interface Snapshot {
    custodian_name?: string | null
    custodian_email?: string | null
    company?: string | null
    department?: string | null
    location?: string | null
    branch?: string | null
}

interface History {
    id: string
    event_type: string
    from_snapshot: Snapshot | null
    to_snapshot: Snapshot | null
    from_condition: string | null
    to_condition: string | null
    effective_at: string
    remarks: string | null
    performed_by: { name: string } | null
    asset: {
        number: string
        serial_number: string | null
        product: { code: string; name: string }
        company: { code: string; name: string }
        department: { code: string; name: string }
        location: { code: string; name: string }
    }
}

const props = defineProps<{ history: History }>()

const conditionLabels: Record<string, string> = {
    GOOD: 'Baik',
    FAIR: 'Cukup',
    NEEDS_REPAIR: 'Perlu Perbaikan',
    DAMAGED: 'Rusak',
}

const eventTitle = props.history.event_type === 'RETURNED'
    ? 'BUKTI PENGEMBALIAN ASET'
    : props.history.event_type === 'TRANSFERRED'
        ? 'BUKTI TRANSFER ASET'
        : 'BUKTI SERAH TERIMA ASET'

function date(value: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(new Date(`${value}T00:00:00`))
}

function condition(value: string | null): string {
    return value ? conditionLabels[value] ?? value : '-'
}

function printDocument(): void {
    window.print()
}

function closePrintTab(): void {
    window.close()
    window.setTimeout(() => {
        if (!window.closed) window.location.replace('about:blank')
    }, 100)
}

onMounted(() => {
    const parameters = new URLSearchParams(window.location.search)
    if (parameters.get('print') === '1') {
        if (parameters.get('closeAfterPrint') === '1') {
            window.addEventListener('afterprint', closePrintTab, { once: true })
        }
        window.setTimeout(() => window.print(), 300)
    }
})
</script>

<template>
    <main class="handover-page">
        <div class="screen-actions">
            <Button label="Cetak / Simpan PDF" icon="pi pi-print" @click="printDocument" />
        </div>

        <article class="handover-document">
            <header class="document-header">
                <div>
                    <p class="eyebrow">ASSET MANAGEMENT</p>
                    <h1>{{ eventTitle }}</h1>
                </div>
                <div class="document-date"><span>Tanggal berlaku</span><strong>{{ date(history.effective_at) }}</strong></div>
            </header>

            <section class="asset-section">
                <h2>Informasi Aset</h2>
                <dl class="details-grid">
                    <div><dt>Kode Aset</dt><dd>{{ history.asset.number }}</dd></div>
                    <div><dt>Nama Produk</dt><dd>{{ history.asset.product.name }} · {{ history.asset.product.code }}</dd></div>
                    <div><dt>Serial Number</dt><dd>{{ history.asset.serial_number || 'Tidak memiliki serial number' }}</dd></div>
                    <div><dt>Perusahaan</dt><dd>{{ history.asset.company.name }}</dd></div>
                    <div><dt>Departemen</dt><dd>{{ history.asset.department.name }}</dd></div>
                    <div><dt>Lokasi</dt><dd>{{ history.asset.location.name }}</dd></div>
                </dl>
            </section>

            <section class="movement-section">
                <h2>Detail Serah Terima</h2>
                <table>
                    <thead><tr><th>Proses</th><th>Penanggung Jawab</th><th>Kondisi Aset</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>Sebelum</td>
                            <td>{{ history.from_snapshot?.custodian_name || 'Asset Management / Available' }}</td>
                            <td>{{ condition(history.from_condition) }}</td>
                        </tr>
                        <tr>
                            <td>Sesudah</td>
                            <td>{{ history.to_snapshot?.custodian_name || 'Asset Management / Available' }}</td>
                            <td>{{ condition(history.to_condition) }}</td>
                        </tr>
                    </tbody>
                </table>
                <dl class="details-grid placement-grid">
                    <div><dt>Perusahaan</dt><dd>{{ history.to_snapshot?.company || history.from_snapshot?.company || '-' }}</dd></div>
                    <div><dt>Departemen</dt><dd>{{ history.to_snapshot?.department || history.from_snapshot?.department || '-' }}</dd></div>
                    <div><dt>Lokasi</dt><dd>{{ history.to_snapshot?.location || history.from_snapshot?.location || '-' }}</dd></div>
                    <div><dt>Cabang</dt><dd>{{ history.to_snapshot?.branch || history.from_snapshot?.branch || '-' }}</dd></div>
                </dl>
            </section>

            <section v-if="history.remarks" class="remarks-section">
                <h2>Catatan</h2>
                <p>{{ history.remarks }}</p>
            </section>

            <p class="declaration">Dokumen ini menjadi bukti pencatatan serah terima atau pengembalian aset pada sistem Asset Management.</p>

            <section class="signatures">
                <div><p>{{ history.event_type === 'RETURNED' ? 'Dikembalikan oleh' : 'Diserahkan oleh' }}</p><strong>{{ history.from_snapshot?.custodian_name || 'Asset Management' }}</strong><span>Nama dan tanda tangan</span></div>
                <div><p>{{ history.event_type === 'RETURNED' ? 'Diterima oleh' : 'Diterima oleh' }}</p><strong>{{ history.to_snapshot?.custodian_name || 'Asset Management' }}</strong><span>Nama dan tanda tangan</span></div>
            </section>

            <footer>Dicatat oleh {{ history.performed_by?.name || '-' }} · Referensi {{ history.id }}</footer>
        </article>
    </main>
</template>

<style>
@page { size: A4; margin: 16mm; }
.handover-page { min-height: 100vh; padding: 24px; background: #f3f4f6; color: #111827; }
.screen-actions { display: flex; justify-content: flex-end; margin: 0 auto 16px; max-width: 850px; }
.handover-document { box-sizing: border-box; max-width: 850px; min-height: 1100px; margin: 0 auto; padding: 48px; background: white; box-shadow: 0 1px 8px #0002; }
.document-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 20px; border-bottom: 3px solid #4c1d95; }
.eyebrow { margin: 0 0 8px; color: #6b21a8; font-size: 10px; font-weight: 700; letter-spacing: .16em; }
.document-header h1 { margin: 0; font-size: 20px; letter-spacing: .04em; }
.document-date { display: grid; gap: 5px; text-align: right; font-size: 12px; }
.document-date span, dt { color: #6b7280; }
.asset-section, .movement-section, .remarks-section { margin-top: 28px; }
.asset-section h2, .movement-section h2, .remarks-section h2 { margin: 0 0 14px; font-size: 13px; }
.details-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 24px; margin: 0; }
.details-grid dt { margin-bottom: 4px; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
.details-grid dd { margin: 0; font-size: 12px; font-weight: 600; }
.movement-section table { width: 100%; border-collapse: collapse; font-size: 11px; }
.movement-section th, .movement-section td { padding: 10px; border: 1px solid #d1d5db; text-align: left; }
.movement-section th { background: #f3f0f8; font-weight: 700; }
.placement-grid { margin-top: 18px; }
.remarks-section p { margin: 0; white-space: pre-line; font-size: 12px; }
.declaration { margin-top: 36px; color: #4b5563; font-size: 11px; line-height: 1.6; }
.signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; margin-top: 56px; text-align: center; }
.signatures div { display: flex; min-height: 120px; flex-direction: column; align-items: center; }
.signatures p { margin: 0; font-size: 11px; }
.signatures strong { margin-top: auto; min-width: 180px; padding-top: 8px; border-top: 1px solid #374151; font-size: 12px; }
.signatures span { margin-top: 4px; color: #6b7280; font-size: 10px; }
.handover-document footer { margin-top: 36px; padding-top: 10px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 9px; }
@media print {
    html, body, #app { min-height: 0; margin: 0; padding: 0; background: white; }
    .handover-page { min-height: 0; padding: 0; background: white; }
    .screen-actions { display: none; }
    .handover-document { max-width: none; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
}
</style>
