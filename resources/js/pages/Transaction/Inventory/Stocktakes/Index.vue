<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import AppPagination from "@/components/common/AppPagination.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface Department { id: string; code: string; name: string }
interface Stocktake { id: string; number: string; status: string; created_at: string; location: { code: string; name: string } | null; initiated_by: { name: string } | null }

const props = defineProps<{
    departments: Department[];
    selectedDepartmentId: string | null;
    canManage: boolean;
    stocktakes: { data: Stocktake[]; current_page: number; last_page: number; per_page: number; total: number; links: Array<{ url: string | null; label: string; active: boolean }> };
}>();
const perPage = ref(props.stocktakes.per_page);

function selectDepartment(event: Event): void {
    router.get(route("transaction.inventory.stocktakes.index"), { department_id: (event.target as HTMLSelectElement).value, per_page: perPage.value }, { preserveState: true });
}

function applyPagination(): void {
    router.get(route("transaction.inventory.stocktakes.index"), {
        department_id: props.selectedDepartmentId ?? undefined,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <Head title="Stock Opname" />
    <div class="procurement-ui space-y-6">
        <InventoryPageHeader title="Stock Opname" section="Stock Opname" description="Cocokkan stok fisik dengan saldo buku. Selisih diposting sebagai pergerakan koreksi baru.">
            <template #actions>
                <Link v-if="canManage" :href="route('transaction.inventory.stocktakes.create', { department_id: selectedDepartmentId ?? undefined })" class="inline-flex min-h-10 items-center rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800">Buat Sesi Opname</Link>
            </template>
        </InventoryPageHeader>

        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <label for="department" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Departemen</label>
            <select id="department" :value="selectedDepartmentId ?? ''" class="h-10 w-full max-w-md rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" @change="selectDepartment">
                <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.code }} · {{ department.name }}</option>
            </select>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-5 py-3">Nomor Opname</th><th class="px-5 py-3">Gudang</th><th class="px-5 py-3">Dibuat</th><th class="px-5 py-3">Petugas</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="item in stocktakes.data" :key="item.id">
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ item.number }}</td><td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ item.location?.code }} · {{ item.location?.name }}</td><td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ new Date(item.created_at).toLocaleDateString('id-ID') }}</td><td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ item.initiated_by?.name ?? '—' }}</td>
                            <td class="px-5 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="item.status === 'POSTED' ? 'bg-emerald-100 text-emerald-800' : item.status === 'COUNTED' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800'">{{ item.status }}</span></td>
                            <td class="px-5 py-3 text-right"><Link :href="route('transaction.inventory.stocktakes.show', item.id)" class="font-medium text-violet-700 hover:underline dark:text-violet-300">View</Link></td>
                        </tr>
                        <tr v-if="stocktakes.data.length === 0"><td colspan="6" class="px-5 py-10 text-center text-gray-500">Belum ada sesi opname untuk departemen ini.</td></tr>
                    </tbody>
                </table>
            </div>
            <AppPagination v-model:per-page="perPage" :meta="stocktakes" @change="applyPagination" />
        </section>
    </div>
</template>
