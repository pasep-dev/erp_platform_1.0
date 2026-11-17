<script setup lang="ts">
import { computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Select from 'primevue/select'

defineOptions({ layout: AppLayout })

interface OrganizationOption { id: string; company_id?: string | null; code: string; name: string }

const props = defineProps<{
    companies: OrganizationOption[]
    departments: OrganizationOption[]
    locations: OrganizationOption[]
    categories: OrganizationOption[]
}>()

const form = useForm({ company_id: '', department_id: '', location_id: '', category_id: '', remarks: '' })
const departments = computed(() => props.departments.filter((department) => !form.company_id || department.company_id === form.company_id))
const locations = computed(() => props.locations.filter((location) => !form.company_id || location.company_id === form.company_id))

watch(() => form.company_id, () => {
    if (!departments.value.some((department) => department.id === form.department_id)) form.department_id = ''
    if (!locations.value.some((location) => location.id === form.location_id)) form.location_id = ''
})

function submit(): void {
    form.post(route('transaction.asset-management.audits.store'), { preserveScroll: true })
}

function cancel(): void {
    router.get(route('transaction.asset-management.audits.index'))
}

function scopeError(): string | undefined {
    return (form.errors as Record<string, string>).scope
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader title="Buat Sesi Stock Opname" section="Audit" description="Pilih cakupan. Semua aset aktif yang cocok akan masuk ke daftar pemeriksaan sesi ini." />

        <form class="space-y-5" @submit.prevent="submit">
            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div><h2 class="font-semibold text-gray-900 dark:text-white">Cakupan Audit</h2><p class="mt-1 text-xs text-gray-500">Kosongkan filter untuk mencakup seluruh aset aktif.</p></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Perusahaan<Select v-model="form.company_id" :options="companies.map((option) => ({ label: `${option.code} · ${option.name}`, value: option.id }))" option-label="label" option-value="value" show-clear filter placeholder="Semua perusahaan" fluid /><small v-if="form.errors.company_id" class="font-normal text-red-600">{{ form.errors.company_id }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Departemen<Select v-model="form.department_id" :options="departments.map((option) => ({ label: `${option.code} · ${option.name}`, value: option.id }))" option-label="label" option-value="value" show-clear filter placeholder="Semua departemen" fluid /><small v-if="form.errors.department_id" class="font-normal text-red-600">{{ form.errors.department_id }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Lokasi<Select v-model="form.location_id" :options="locations.map((option) => ({ label: `${option.code} · ${option.name}`, value: option.id }))" option-label="label" option-value="value" show-clear filter placeholder="Semua lokasi" fluid /><small v-if="form.errors.location_id" class="font-normal text-red-600">{{ form.errors.location_id }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Kategori<Select v-model="form.category_id" :options="categories.map((option) => ({ label: `${option.code} · ${option.name}`, value: option.id }))" option-label="label" option-value="value" show-clear filter placeholder="Semua kategori" fluid /><small v-if="form.errors.category_id" class="font-normal text-red-600">{{ form.errors.category_id }}</small></label>
                </div>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Catatan sesi<textarea v-model="form.remarks" rows="3" maxlength="5000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Contoh: Stock opname semester 2 area kantor pusat" /><small v-if="form.errors.remarks" class="font-normal text-red-600">{{ form.errors.remarks }}</small></label>
                <p v-if="scopeError()" class="text-sm text-red-600">{{ scopeError() }}</p>
            </section>
            <div class="flex justify-end gap-2"><Button label="Kembali" severity="secondary" outlined type="button" @click="cancel" /><Button label="Buat Sesi" icon="pi pi-check" type="submit" :loading="form.processing" /></div>
        </form>
    </div>
</template>
