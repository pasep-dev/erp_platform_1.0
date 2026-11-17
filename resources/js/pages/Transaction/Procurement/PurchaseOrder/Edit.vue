<script setup lang="ts">
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Message from 'primevue/message'
import ProcurementPageHeader from '@/components/procurement/ProcurementPageHeader.vue'

defineOptions({ layout: AppLayout })

interface OrderItem {
    id: string
    quantity: string | number
    unit_price: string | number | null
    pr_estimated_unit_price: string | number | null
    description?: string | null
    product?: { code: string; name: string } | null
    service_type?: { code: string; name: string } | null
    uom?: { code: string; name: string } | null
}
interface PurchaseOrder {
    id: string
    number: string
    status: string
    purchase_request?: { number: string }
    vendor?: { name: string }
    items: OrderItem[]
}
const props = defineProps<{ purchaseOrder: PurchaseOrder }>()

const form = useForm({
    items: props.purchaseOrder.items.map(item => ({
        id: item.id,
        unit_price: item.unit_price === null ? null : Number(item.unit_price),
        reason: '',
    })),
})
const itemForms = computed(() => form.items.map((entry, index) => ({
    ...entry,
    source: props.purchaseOrder.items[index],
    changed: Number(entry.unit_price ?? -1) !== Number(props.purchaseOrder.items[index].unit_price ?? -1),
})))
const money = (value: number | string | null) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value ?? 0))

function save() {
    form.put(route('transaction.procurement.purchase-orders.update', props.purchaseOrder.id), { preserveScroll: true })
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <ProcurementPageHeader
            title="Edit Harga Purchase Order"
            section="Purchase Order"
            :description="`${purchaseOrder.number} · PR ${purchaseOrder.purchase_request?.number ?? '-'} · ${purchaseOrder.vendor?.name ?? '-'}`"
        >
            <template #actions>
                <Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.get(route('transaction.procurement.purchase-orders.show', purchaseOrder.id))" />
            </template>
        </ProcurementPageHeader>

        <Message severity="info" :closable="false">Hanya harga PO yang dapat diubah. Estimasi PR disimpan sebagai pembanding; setiap perubahan harga wajib memiliki alasan dan tercatat di histori.</Message>

        <form class="space-y-5" @submit.prevent="save">
            <div v-for="(item, index) in itemForms" :key="item.id" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-800 dark:text-white">{{ item.source.product?.name ?? item.source.service_type?.name ?? item.source.description ?? 'Item' }}</h2>
                        <p class="mt-1 text-xs text-gray-500">{{ item.source.product?.code ?? item.source.service_type?.code ?? '' }} · {{ Number(item.source.quantity).toLocaleString('id-ID') }} {{ item.source.uom?.code ?? '' }}</p>
                    </div>
                    <div class="text-right text-sm"><div class="text-gray-500">Estimasi PR</div><strong>{{ money(item.source.pr_estimated_unit_price) }}</strong></div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label :for="`price-${index}`" class="mb-1 block text-sm font-medium">Harga PO per unit</label>
                        <InputNumber :input-id="`price-${index}`" v-model="form.items[index].unit_price" mode="currency" currency="IDR" locale="id-ID" :min="0" :min-fraction-digits="0" :max-fraction-digits="2" class="w-full" input-class="w-full" />
                        <small v-if="form.errors[`items.${index}.unit_price`]" class="text-red-600">{{ form.errors[`items.${index}.unit_price`] }}</small>
                    </div>
                    <div v-if="item.changed">
                        <label :for="`reason-${index}`" class="mb-1 block text-sm font-medium">Alasan perubahan harga <span class="text-red-600">*</span></label>
                        <Textarea :id="`reason-${index}`" v-model="form.items[index].reason" rows="3" auto-resize class="w-full" placeholder="Jelaskan alasan harga PO berbeda dari harga sebelumnya" />
                        <small v-if="form.errors[`items.${index}.reason`]" class="text-red-600">{{ form.errors[`items.${index}.reason`] }}</small>
                    </div>
                    <div v-else class="flex items-center text-sm text-gray-500">Harga sama dengan harga saat ini; alasan perubahan tidak diperlukan.</div>
                </div>
            </div>
            <div v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</div>
            <div class="flex justify-end gap-2">
                <Button label="Batal" severity="secondary" outlined type="button" @click="router.get(route('transaction.procurement.purchase-orders.show', purchaseOrder.id))" />
                <Button label="Simpan Harga" icon="pi pi-save" type="submit" :loading="form.processing" />
            </div>
        </form>
    </div>
</template>
