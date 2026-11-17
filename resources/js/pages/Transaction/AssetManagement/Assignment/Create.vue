<script setup lang="ts">
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

interface UserOption { id: number; company_id: string | null; name: string; email: string }
interface Asset {
    id: string
    number: string
    serial_number: string | null
    condition: string
    product: { code: string; name: string }
    company: { code: string; name: string }
    department: { code: string; name: string }
    location: { code: string; name: string }
    custodian: { id: number; name: string; email: string } | null
}

const props = defineProps<{ asset: Asset; users: UserOption[]; is_transfer: boolean; condition_options: Record<string, string> }>()

const today = new Date()
today.setMinutes(today.getMinutes() - today.getTimezoneOffset())

const form = useForm({
    assignee_id: null as number | null,
    condition: props.asset.condition || 'GOOD',
    effective_at: today.toISOString().slice(0, 10),
    remarks: '',
})

const validationMessages = computed(() => Object.values(form.errors).filter((message): message is string => Boolean(message)))

function cancel(): void {
    router.get(route('transaction.asset-management.assignments.show', props.asset.id))
}

function submit(): void {
    form.post(route('transaction.asset-management.assignments.assign', props.asset.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader :title="is_transfer ? 'Transfer Penanggung Jawab' : 'Assign Aset'" section="Assignment" :description="is_transfer ? 'Perpindahan penanggung jawab akan dicatat sebagai riwayat baru.' : 'Pilih pengguna yang akan menerima tanggung jawab atas aset ini.'" />

        <section v-if="validationMessages.length" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <h2 class="font-semibold">Assignment belum bisa disimpan. Periksa bagian berikut:</h2>
            <ul class="mt-2 list-inside list-disc space-y-1"><li v-for="(message, index) in validationMessages" :key="`${index}-${message}`">{{ message }}</li></ul>
        </section>

        <form class="space-y-5" @submit.prevent="submit">
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-purple-700 dark:text-purple-300">{{ asset.product.code }} · {{ asset.product.name }}</p><h2 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ asset.number }}</h2><p v-if="asset.serial_number" class="mt-1 text-sm text-gray-500">SN {{ asset.serial_number }}</p></div>
                    <Tag :value="is_transfer ? `Assigned · ${asset.custodian?.name}` : 'Available'" :severity="is_transfer ? 'info' : 'success'" />
                </div>
                <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-4 text-sm dark:border-gray-800 sm:grid-cols-3">
                    <div><dt class="text-xs text-gray-500">Perusahaan</dt><dd class="mt-1 font-medium">{{ asset.company.name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Departemen</dt><dd class="mt-1 font-medium">{{ asset.department.name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Lokasi fisik</dt><dd class="mt-1 font-medium">{{ asset.location.name }}</dd></div>
                </dl>
                <p class="mt-4 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-200">Assignment mengubah penanggung jawab aset. Departemen dan lokasi fisik tetap mengikuti penempatan saat ini.</p>
            </section>

            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-900 dark:text-white">Informasi penugasan</h2>
                <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">
                    Penanggung jawab baru <span class="text-red-600">*</span>
                    <select v-model="form.assignee_id" required class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                        <option :value="null" disabled>Pilih pengguna</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} · {{ user.email }}</option>
                    </select>
                    <small v-if="form.errors.assignee_id" class="font-normal text-red-600">{{ form.errors.assignee_id }}</small>
                    <small v-else class="font-normal text-gray-500">Hanya pengguna aktif pada perusahaan ini yang ditampilkan.</small>
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Kondisi saat diserahkan <span class="text-red-600">*</span><select v-model="form.condition" required class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"><option v-for="(label, value) in condition_options" :key="value" :value="value">{{ label }}</option></select><small v-if="form.errors.condition" class="font-normal text-red-600">{{ form.errors.condition }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal berlaku <span class="text-red-600">*</span><input v-model="form.effective_at" type="date" required class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" /><small v-if="form.errors.effective_at" class="font-normal text-red-600">{{ form.errors.effective_at }}</small></label>
                    <label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Catatan <span class="font-normal text-gray-500">(opsional)</span><textarea v-model="form.remarks" rows="3" maxlength="2000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Contoh: serah terima laptop untuk pekerjaan..." /><small v-if="form.errors.remarks" class="font-normal text-red-600">{{ form.errors.remarks }}</small></label>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <Button label="Kembali" severity="secondary" outlined type="button" @click="cancel" />
                <Button :label="is_transfer ? 'Simpan Transfer' : 'Simpan Assignment'" icon="pi pi-check" type="submit" :loading="form.processing" :disabled="form.assignee_id === null" />
            </div>
        </form>
    </div>
</template>
