<script setup lang="ts">
import { onMounted } from 'vue'
import AssetQrCode from '@/components/asset/AssetQrCode.vue'
import AssetBarcode from '@/components/asset/AssetBarcode.vue'

const props = defineProps<{ assets: { id: string; number: string; company_code: string; company_name: string; department_code: string }[] }>()

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
    <main class="qr-code-sheet">
        <article v-for="asset in props.assets" :key="asset.id" class="qr-code-label">
            <div class="asset-identity">
                <p class="asset-label-title">ASSET DIRECTORY</p>
                <p class="asset-company-name">{{ asset.company_name }}</p>
                <h2>{{ asset.number }}</h2>
                <div class="asset-org-codes">
                    <span><small>COMPANY</small>{{ asset.company_code }}</span>
                    <span><small>DEPARTMENT</small>{{ asset.department_code }}</span>
                </div>
            </div>
            <div class="asset-code-panel">
                <AssetQrCode :value="asset.number" :show-label="false" />
                <AssetBarcode :value="asset.number" />
            </div>
        </article>
        <p v-if="props.assets.length === 0" class="p-8 text-center text-sm text-gray-500">Tidak ada QR code untuk dicetak.</p>
    </main>
</template>

<style>
@page { size: A4; margin: 10mm; }
.qr-code-sheet { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); align-content: start; gap: 3mm; padding: 10mm; }
.qr-code-label { display: grid; width: 100%; min-height: 28mm; grid-template-columns: minmax(0, 1fr) 27mm; align-items: center; gap: 1.5mm; border: 1px solid #d8d5e2; border-left: 1.5mm solid #4c1d95; border-radius: 1.5mm; padding: 2mm; }
.asset-identity { min-width: 0; }
.asset-label-title { margin: 0 0 1.2mm; color: #6b21a8; font-size: 5pt; font-weight: 700; letter-spacing: .1em; }
.asset-company-name { overflow-wrap: anywhere; margin: 0 0 1mm; color: #374151; font-size: 6pt; font-weight: 600; }
.asset-identity h2 { overflow-wrap: anywhere; margin: 0; color: #111827; font-size: 8pt; font-weight: 700; line-height: 1.2; }
.asset-org-codes { display: flex; flex-wrap: wrap; gap: 1mm 3mm; margin-top: 2mm; }
.asset-org-codes span { display: grid; color: #374151; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 6.5pt; font-weight: 700; }
.asset-org-codes small { margin-bottom: .3mm; color: #6b7280; font-family: sans-serif; font-size: 4pt; font-weight: 600; letter-spacing: .06em; }
.asset-code-panel { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5mm; border-left: 1px solid #e5e7eb; padding-left: 2mm; }
.asset-code-panel .asset-qr-code { padding: 0; }
.asset-code-panel .asset-qr-code svg { width: 16mm; height: 16mm; }
.asset-code-panel .asset-barcode { height: 7mm; }
@media print {
    html, body, #app { width: 100%; min-height: 0; margin: 0; padding: 0; background: #fff; }
    .qr-code-sheet { padding: 0; gap: 3mm; }
    .qr-code-label { min-height: 28mm; break-inside: avoid; }
}
</style>
