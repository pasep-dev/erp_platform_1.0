<script setup lang="ts">
import { computed, nextTick, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Select from 'primevue/select'

defineOptions({ layout: AppLayout })

interface Company { id: string; code: string; name: string }
interface Department { id: string; company_id: string; code: string; name: string }
interface BranchOption {
    id: string
    company_id: string
    code: string
    name: string
    warehouse_location_id: string | null
    warehouse_code: string | null
    warehouse_name: string | null
    warehouse_count: number
}
interface Product { id: string; category_id: string; type_id: string; brand_id: string; model_id: string | null; code: string; name: string; department_ids: string[]; category?: { name: string } | null; type?: { name: string } | null; brand?: { name: string } | null; model?: { name: string } | null }
interface ProcurementSource {
    goods_receipt_item_id: string
    goods_receipt_number: string
    source_number: string
    received_at: string
    company_id: string
    company_name: string
    branch_id: string | null
    branch_code: string | null
    branch_name: string | null
    department_id: string
    department_name: string
    product_id: string
    product_code: string
    product_name: string
    category_name: string | null
    type_name: string | null
    brand_name: string | null
    model_name: string | null
    pending_count: number
    acquisition_cost: string | null
}
interface UnitInput {
    serial_number: string
    specifications: { name: string; value: string }[]
    warranty_years: number | null
}

const props = defineProps<{
    source: ProcurementSource | null
    companies: Company[]
    departments: Department[]
    branches: BranchOption[]
    products: Product[]
}>()

function createUnit(): UnitInput {
    return { serial_number: '', specifications: [], warranty_years: null }
}

const form = useForm({
    goods_receipt_item_id: props.source?.goods_receipt_item_id ?? '',
    product_id: '',
    company_id: props.source?.company_id ?? '',
    department_id: props.source?.department_id ?? '',
    branch_id: props.source?.branch_id ?? '',
    units: Array.from({ length: props.source?.pending_count ?? 1 }, createUnit) as UnitInput[],
    acquisition_date: (() => { const today = new Date(); today.setMinutes(today.getMinutes() - today.getTimezoneOffset()); return today.toISOString().slice(0, 10) })(),
    acquisition_cost: null as number | null,
    remarks: '',
})

const filteredDepartments = computed(() => props.departments.filter((item) => item.company_id === form.company_id))
const filteredBranches = computed(() => props.branches.filter((item) => item.company_id === form.company_id))
const filteredProducts = computed(() => props.products.filter((product) => product.department_ids.includes(form.department_id)))
const productOptions = computed(() => filteredProducts.value.map((product) => ({
    ...product,
    label: `${product.code} · ${product.name}`,
})))
const selectedProduct = computed(() => props.products.find((item) => item.id === form.product_id))
const selectedBranch = computed(() => filteredBranches.value.find((item) => item.id === form.branch_id) ?? null)
const validationMessages = computed(() => Object.values(form.errors).filter((message): message is string => Boolean(message)))

watch(() => form.department_id, () => {
    if (form.product_id && !filteredProducts.value.some((product) => product.id === form.product_id)) {
        form.product_id = ''
    }
})

watch(() => form.company_id, (companyId, previousCompanyId) => {
    if (previousCompanyId && companyId !== previousCompanyId) {
        form.branch_id = props.source?.branch_id ?? ''
    }
})

function cancel(): void {
    router.get(route('transaction.asset-management.registrations.index'))
}

function addUnit(): void {
    if (form.units.length >= 500) {
        return
    }

    if (!props.source || form.units.length < props.source.pending_count) {
        form.units.push(createUnit())
    }
}

function removeUnit(index: number): void {
    if (form.units.length > 1) {
        form.units.splice(index, 1)
    }
}

function addSpecification(unit: UnitInput): void {
    unit.specifications.push({ name: '', value: '' })
}

function removeSpecification(unit: UnitInput, index: number): void {
    unit.specifications.splice(index, 1)
}

function unitError(index: number, field: string): string | undefined {
    return form.errors[`units.${index}.${field}`]
}

function specificationError(unitIndex: number, specificationIndex: number): string | undefined {
    return form.errors[`units.${unitIndex}.specifications.${specificationIndex}.value`]
}

function submit(): void {
    if (props.source) {
        form.transform(({ company_id, department_id, product_id, acquisition_date, acquisition_cost, ...data }) => data)
    }

    form.post(route('transaction.asset-management.registrations.store'), {
        preserveState: true,
        preserveScroll: true,
        onError: async () => {
            await nextTick()
            document.getElementById('registration-validation-summary')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
        },
    })
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader :title="source ? 'Registrasi Aset dari Goods Receipt' : 'Registrasi Aset Existing'" section="Registration" description="Aset baru didaftarkan tanpa penanggung jawab dan otomatis ditempatkan di warehouse branch yang dipilih." />

        <section
            v-if="validationMessages.length"
            id="registration-validation-summary"
            role="alert"
            class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
        >
            <h2 class="font-semibold">Registrasi belum bisa disimpan. Periksa bagian berikut:</h2>
            <ul class="mt-2 list-inside list-disc space-y-1">
                <li v-for="(message, index) in validationMessages" :key="`${index}-${message}`">{{ message }}</li>
            </ul>
        </section>

        <form class="space-y-5" @submit.prevent="submit">
            <section v-if="source" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-900 dark:text-white">Informasi Sumber</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Goods Receipt</dt><dd class="mt-1 font-medium">{{ source.goods_receipt_number }}</dd></div>
                    <div><dt class="text-gray-500">PR / PO</dt><dd class="mt-1 font-medium">{{ source.source_number }}</dd></div>
                    <div><dt class="text-gray-500">Produk</dt><dd class="mt-1 font-medium">{{ source.product_name }} ({{ source.product_code }})</dd></div>
                    <div><dt class="text-gray-500">Identitas master</dt><dd class="mt-1">{{ source.category_name ?? '-' }} · {{ source.type_name ?? '-' }} · {{ source.brand_name ?? '-' }} · {{ source.model_name ?? '-' }}</dd></div>
                    <div><dt class="text-gray-500">Perusahaan / Departemen</dt><dd class="mt-1">{{ source.company_name }} · {{ source.department_name }}</dd></div>
                    <div><dt class="text-gray-500">Unit belum diregistrasi</dt><dd class="mt-1 font-semibold">{{ source.pending_count }}</dd></div>
                    <div><dt class="text-gray-500">Tanggal perolehan</dt><dd class="mt-1">{{ source.received_at }}</dd></div>
                    <div><dt class="text-gray-500">Harga PO per unit</dt><dd class="mt-1">{{ source.acquisition_cost ? Number(source.acquisition_cost).toLocaleString('id-ID') : 'Belum tersedia (PR tanpa PO)' }}</dd></div>
                </dl>
            </section>

            <section v-else class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-900 dark:text-white">Informasi Aset Existing</h2>
                <p class="mt-1 text-sm text-gray-500">Gunakan jalur ini untuk aset yang sudah dimiliki sebelum proses procurement dicatat di sistem.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="space-y-1 text-sm"><span class="font-medium">Perusahaan <b class="text-red-500">*</b></span><select v-model="form.company_id" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 dark:border-gray-700 dark:bg-gray-900"><option value="">Pilih perusahaan</option><option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }} ({{ company.code }})</option></select><small v-if="form.errors.company_id" class="text-red-600">{{ form.errors.company_id }}</small></label>
                    <label class="space-y-1 text-sm"><span class="font-medium">Departemen <b class="text-red-500">*</b></span><select v-model="form.department_id" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 dark:border-gray-700 dark:bg-gray-900"><option value="">Pilih departemen</option><option v-for="department in filteredDepartments" :key="department.id" :value="department.id">{{ department.name }}</option></select><small v-if="form.errors.department_id" class="text-red-600">{{ form.errors.department_id }}</small></label>
                    <label class="space-y-1 text-sm sm:col-span-2"><span class="font-medium">Produk ASSET <b class="text-red-500">*</b></span><Select v-model="form.product_id" :options="productOptions" option-label="label" option-value="id" filter show-clear :disabled="!form.department_id" placeholder="Cari kode atau nama produk" class="w-full" /><small v-if="selectedProduct" class="block text-gray-500">{{ selectedProduct.category?.name }} · {{ selectedProduct.type?.name }} · {{ selectedProduct.brand?.name }} · {{ selectedProduct.model?.name }}</small><small v-if="form.errors.product_id" class="text-red-600">{{ form.errors.product_id }}</small></label>
                    <label class="space-y-1 text-sm"><span class="font-medium">Tanggal perolehan <b class="text-red-500">*</b></span><input v-model="form.acquisition_date" type="date" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 dark:border-gray-700 dark:bg-gray-900"><small v-if="form.errors.acquisition_date" class="text-red-600">{{ form.errors.acquisition_date }}</small></label>
                    <label class="space-y-1 text-sm"><span class="font-medium">Nilai perolehan</span><input v-model.number="form.acquisition_cost" type="number" min="0" step="0.01" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 dark:border-gray-700 dark:bg-gray-900"><small v-if="form.errors.acquisition_cost" class="text-red-600">{{ form.errors.acquisition_cost }}</small></label>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-900 dark:text-white">Identitas dan Penempatan</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="space-y-1 text-sm"><span class="font-medium">Branch penempatan <b class="text-red-500">*</b></span><select v-model="form.branch_id" required :disabled="!form.company_id || Boolean(source?.branch_id)" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 dark:border-gray-700 dark:bg-gray-900"><option value="">Pilih branch</option><option v-for="branch in filteredBranches" :key="branch.id" :value="branch.id" :disabled="branch.warehouse_count !== 1">{{ branch.code }} · {{ branch.name }}{{ branch.warehouse_count === 1 ? '' : branch.warehouse_count === 0 ? ' — warehouse belum disiapkan' : ' — pastikan hanya satu warehouse aktif' }}</option></select><small v-if="source?.branch_id" class="text-gray-500">Branch mengikuti warehouse Goods Receipt: {{ source.branch_code }} · {{ source.branch_name }}</small><small v-if="form.errors.branch_id" class="text-red-600">{{ form.errors.branch_id }}</small></label>
                    <div class="space-y-1 text-sm"><span class="font-medium">Warehouse otomatis</span><div class="flex h-11 items-center rounded-lg border border-gray-200 bg-gray-50 px-3 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ selectedBranch?.warehouse_location_id ? `${selectedBranch.warehouse_code} · ${selectedBranch.warehouse_name}` : 'Pilih branch dengan satu warehouse aktif' }}</div></div>
                    <label class="space-y-1 text-sm sm:col-span-2"><span class="font-medium">Catatan</span><textarea v-model="form.remarks" rows="3" maxlength="5000" class="w-full rounded-lg border border-gray-300 bg-white p-3 dark:border-gray-700 dark:bg-gray-900" placeholder="Catatan kondisi atau informasi aset"></textarea><small v-if="form.errors.remarks" class="text-red-600">{{ form.errors.remarks }}</small></label>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <div><h2 class="font-semibold text-gray-900 dark:text-white">Identitas Setiap Unit</h2><p class="mt-1 text-sm text-gray-500">Masukkan serial number, masa garansi, dan spesifikasi masing-masing aset. Awal perhitungan garansi mengikuti tanggal perolehan: {{ source?.received_at ?? form.acquisition_date }}.</p></div>
                    <Button v-if="!source" type="button" label="Tambah Unit" icon="pi pi-plus" severity="secondary" outlined @click="addUnit" />
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/[0.02] dark:text-gray-400"><tr><th class="px-5 py-3">Unit</th><th class="min-w-56 px-5 py-3">Serial Number</th><th class="min-w-48 px-5 py-3">Masa Garansi (Tahun)</th><th class="min-w-[28rem] px-5 py-3">Spesifikasi Fleksibel</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <tr v-for="(unit, index) in form.units" :key="index" class="align-top">
                                <td class="whitespace-nowrap px-5 py-4 font-medium">Unit {{ index + 1 }}</td>
                                <td class="px-5 py-4"><input v-model="unit.serial_number" maxlength="150" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 dark:border-gray-700 dark:bg-gray-900" placeholder="Serial number"><small v-if="unitError(index, 'serial_number')" class="mt-1 block text-red-600">{{ unitError(index, 'serial_number') }}</small></td>
                                <td class="px-5 py-4"><input v-model.number="unit.warranty_years" type="number" min="0.5" max="100" step="0.5" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 dark:border-gray-700 dark:bg-gray-900" placeholder="Contoh: 1 atau 2"><small v-if="unitError(index, 'warranty_years')" class="mt-1 block text-red-600">{{ unitError(index, 'warranty_years') }}</small></td>
                                <td class="px-5 py-4">
                                    <div v-if="unit.specifications.length" class="space-y-2">
                                        <div v-for="(specification, specIndex) in unit.specifications" :key="specIndex" class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <input v-model="specification.name" maxlength="100" class="h-9 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 dark:border-gray-700 dark:bg-gray-900" placeholder="Nama, mis. RAM">
                                                <input v-model="specification.value" maxlength="500" class="h-9 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 dark:border-gray-700 dark:bg-gray-900" placeholder="Nilai, mis. 16 GB">
                                                <Button type="button" icon="pi pi-times" severity="danger" text rounded aria-label="Hapus spesifikasi" v-tooltip.top="'Hapus spesifikasi'" @click="removeSpecification(unit, specIndex)" />
                                            </div>
                                            <small v-if="specificationError(index, specIndex)" class="block text-red-600">{{ specificationError(index, specIndex) }}</small>
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-500">Belum ada spesifikasi.</span>
                                    <Button type="button" label="Tambah spesifikasi" icon="pi pi-plus" severity="secondary" text size="small" class="mt-2 !px-0" @click="addSpecification(unit)" />
                                </td>
                                <td class="px-5 py-4 text-right"><Button v-if="form.units.length > 1" type="button" icon="pi pi-trash" severity="danger" text rounded aria-label="Hapus unit" v-tooltip.top="'Hapus unit'" @click="removeUnit(index)" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="form.errors.units" class="border-t border-red-200 bg-red-50 px-5 py-3 text-sm text-red-700">{{ form.errors.units }}</div>
            </section>

            <div v-if="form.errors.goods_receipt_item_id" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ form.errors.goods_receipt_item_id }}</div>
            <div class="flex justify-end gap-2"><Button type="button" label="Kembali" severity="secondary" outlined @click="cancel" /><Button type="submit" label="Simpan Registrasi" icon="pi pi-check" :loading="form.processing" /></div>
        </form>
    </div>
</template>
