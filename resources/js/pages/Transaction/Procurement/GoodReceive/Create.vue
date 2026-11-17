<script setup lang="ts">
import AppLayout from '@/layout/AppLayout.vue'
import GoodsReceiptForm from './components/GoodsReceiptForm.vue'
import ProcurementPageHeader from '@/components/procurement/ProcurementPageHeader.vue'

defineOptions({ layout: AppLayout })

interface Source {
    source_type: 'PURCHASE_ORDER' | 'PURCHASE_REQUEST'
    source_id: string
    source_number: string
    purchase_request_number?: string | null
    vendor_id?: string | null
    vendor?: string | null
    company?: string | null
    department?: string | null
    status: string
}

interface Item {
    source_item_id: string
    item_type: 'PRODUCT' | 'SERVICE'
    item_name: string
    item_code?: string | null
    description?: string | null
    quantity_ordered: number
    quantity_previously_accepted: number
    remaining_quantity: number
    uom?: string | null
    uom_code?: string | null
    quantity_received?: number | string | null
    quantity_accepted?: number | string | null
    quantity_rejected?: number | string | null
    remarks?: string | null
}

interface LocationOption {
    id: string
    code: string
    name: string
    branch_id?: string | null
    parent_id?: string | null
    location_type?: string | null
}

defineProps<{ source: Source; items: Item[]; branches: LocationOption[]; warehouses: LocationOption[]; storageLocations: LocationOption[] }>()
</script>

<template>
    <div class="procurement-ui space-y-6">
        <ProcurementPageHeader
            title="Catat Penerimaan"
            section="Goods Receipt"
            :description="`Sumber ${source.source_number}${source.vendor ? ` · ${source.vendor}` : ''}`"
        />
        <GoodsReceiptForm :source="source" :items="items" :branches="branches" :warehouses="warehouses" :storage-locations="storageLocations" />
    </div>
</template>
