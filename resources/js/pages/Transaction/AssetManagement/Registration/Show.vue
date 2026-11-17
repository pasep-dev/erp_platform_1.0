<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

interface Asset {
    id: string
    number: string
    registration_method: 'PROCUREMENT' | 'EXISTING'
    status: string
    serial_number: string | null
    specifications: Record<string, string> | null
    warranty_start_date: string | null
    warranty_end_date: string | null
    warranty_duration_months: number | null
    acquisition_date: string
    acquisition_cost: string | null
    remarks: string | null
    product: { code: string; name: string; category?: { name: string } | null; type?: { name: string } | null; brand?: { name: string } | null; model?: { name: string } | null }
    company: { code: string; name: string }
    department: { code: string; name: string }
    location: { code: string; name: string }
    branch?: { code: string; name: string } | null
    custodian?: { name: string; email: string } | null
    goods_receipt_item?: { goods_receipt: { number: string; purchase_order?: { number: string } | null; purchase_request?: { number: string } | null } } | null
    registered_by: { name: string }
    created_at: string
}

const props = defineProps<{ asset: Asset }>()
const sourceReceipt = props.asset.goods_receipt_item?.goods_receipt

function warrantyLabel(): string {
    const months = props.asset.warranty_duration_months
    if (!months) {
        return 'Tidak dicatat'
    }

    const duration = months % 12 === 0 ? `${months / 12} tahun` : `${months} bulan`
    return `${duration} · ${props.asset.warranty_start_date} sampai ${props.asset.warranty_end_date}`
}

function back(): void {
    router.get(route('transaction.asset-management.registrations.index'))
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader :title="asset.number" section="Registration">
            <template #badge><Tag :value="asset.status" severity="success" /><Tag :value="asset.registration_method === 'PROCUREMENT' ? 'Procurement' : 'Existing'" severity="secondary" /></template>
            <template #actions><Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined @click="back" /></template>
        </AssetManagementPageHeader>

        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Identitas Aset</h2></div>
            <dl class="grid gap-x-8 gap-y-5 p-5 sm:grid-cols-2">
                <div><dt class="text-sm text-gray-500">Produk</dt><dd class="mt-1 font-medium">{{ asset.product.name }} <span class="text-sm text-gray-500">({{ asset.product.code }})</span></dd></div>
                <div><dt class="text-sm text-gray-500">Kategori / Tipe</dt><dd class="mt-1">{{ asset.product.category?.name ?? '-' }} · {{ asset.product.type?.name ?? '-' }}</dd></div>
                <div><dt class="text-sm text-gray-500">Merek / Model</dt><dd class="mt-1">{{ asset.product.brand?.name ?? '-' }} · {{ asset.product.model?.name ?? '-' }}</dd></div>
                <div><dt class="text-sm text-gray-500">Serial number</dt><dd class="mt-1">{{ asset.serial_number || '-' }}</dd></div>
                <div><dt class="text-sm text-gray-500">Spesifikasi</dt><dd class="mt-1">{{ Object.keys(asset.specifications ?? {}).length ? Object.entries(asset.specifications ?? {}).map(([name, value]) => `${name}: ${value}`).join(' · ') : '-' }}</dd></div>
                <div><dt class="text-sm text-gray-500">Masa garansi</dt><dd class="mt-1">{{ warrantyLabel() }}</dd></div>
                <div><dt class="text-sm text-gray-500">Perusahaan / Departemen</dt><dd class="mt-1">{{ asset.company.name }} · {{ asset.department.name }}</dd></div>
                <div><dt class="text-sm text-gray-500">Lokasi / Cabang</dt><dd class="mt-1">{{ asset.location.name }} · {{ asset.branch?.name ?? '-' }}</dd></div>
                <div><dt class="text-sm text-gray-500">Penanggung jawab</dt><dd class="mt-1">{{ asset.custodian?.name ?? 'Belum ditentukan' }}</dd></div>
                <div><dt class="text-sm text-gray-500">Tanggal perolehan</dt><dd class="mt-1">{{ asset.acquisition_date }}</dd></div>
                <div><dt class="text-sm text-gray-500">Nilai perolehan</dt><dd class="mt-1">{{ asset.acquisition_cost ? Number(asset.acquisition_cost).toLocaleString('id-ID') : '-' }}</dd></div>
                <div v-if="sourceReceipt"><dt class="text-sm text-gray-500">Goods Receipt</dt><dd class="mt-1">{{ sourceReceipt.number }} · {{ sourceReceipt.purchase_order?.number ?? sourceReceipt.purchase_request?.number ?? '-' }}</dd></div>
                <div><dt class="text-sm text-gray-500">Diregistrasi oleh</dt><dd class="mt-1">{{ asset.registered_by.name }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm text-gray-500">Catatan</dt><dd class="mt-1 whitespace-pre-line">{{ asset.remarks || '-' }}</dd></div>
            </dl>
        </section>
    </div>
</template>
