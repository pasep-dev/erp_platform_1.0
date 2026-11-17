<script setup lang="ts">
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface Department { id: string; code: string; name: string }
interface Warehouse { id: string; code: string; name: string }

const props = defineProps<{ departments: Department[]; departmentId: string | null; warehouses: Warehouse[] }>();
const form = useForm({ department_id: props.departmentId ?? "", location_id: "", remarks: "" });

function changeDepartment(event: Event): void {
    router.get(route("transaction.inventory.stocktakes.create"), { department_id: (event.target as HTMLSelectElement).value });
}
</script>

<template>
    <Head title="Buat Stock Opname" />
    <div class="procurement-ui space-y-6">
        <InventoryPageHeader title="Buat Sesi Stock Opname" section="Stock Opname" description="Sistem menyimpan snapshot saldo dan nomor seri saat sesi dibuat. Selesaikan penghitungan sebelum memposting." />
        <form class="space-y-5 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" @submit.prevent="form.post(route('transaction.inventory.stocktakes.store'))">
            <div><label for="department" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Departemen</label><select id="department" :value="departmentId ?? ''" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" @change="changeDepartment"><option value="">Pilih departemen</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.code }} · {{ department.name }}</option></select><p v-if="form.errors.department_id" class="mt-1 text-sm text-red-600">{{ form.errors.department_id }}</p></div>
            <div><label for="warehouse" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Gudang</label><select id="warehouse" v-model="form.location_id" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"><option value="">Pilih gudang</option><option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.code }} · {{ warehouse.name }}</option></select><p v-if="form.errors.location_id" class="mt-1 text-sm text-red-600">{{ form.errors.location_id }}</p><p v-if="warehouses.length === 0" class="mt-1 text-sm text-amber-700">Belum ada gudang dengan stok untuk departemen ini.</p></div>
            <div><label for="remarks" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan</label><textarea id="remarks" v-model="form.remarks" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="Opsional" /></div>
            <div class="flex justify-end gap-2"><Link :href="route('transaction.inventory.stocktakes.index', { department_id: departmentId ?? undefined })" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">Kembali</Link><button type="submit" :disabled="form.processing || !warehouses.length" class="rounded-lg bg-violet-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">Buat Snapshot</button></div>
        </form>
    </div>
</template>
