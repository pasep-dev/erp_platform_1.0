<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface Movement {
    id: string;
    occurred_at: string;
    movement_type: string;
    direction: "IN" | "OUT";
    quantity: number;
    serial_number: string | null;
    reference_number: string | null;
    source_type: string;
    balance: number;
    performed_by: string | null;
}

const props = defineProps<{
    stockCard: {
        product: { id: string; code: string; name: string; inventory_tracking_mode: string };
        warehouse: { id: string; code: string; name: string };
        department: { id: string; code: string; name: string };
        balance: number;
        movements: Movement[];
    };
}>();

function sourceLabel(sourceType: string): string {
    if (sourceType === "GOODS_RECEIPT") return "Penerimaan procurement";
    if (sourceType.startsWith("INVENTORY_")) return "Transaksi inventory";
    return sourceType.replaceAll("_", " ");
}
</script>

<template>
    <Head :title="`Kartu Stok · ${stockCard.product.code}`" />
    <div class="procurement-ui space-y-6">
        <InventoryPageHeader title="Kartu Stok" section="Saldo Stok" :description="`${stockCard.product.code} · ${stockCard.product.name} / ${stockCard.department.code} · ${stockCard.department.name} / ${stockCard.warehouse.code} · ${stockCard.warehouse.name}`">
            <template #back>
                <Link :href="route('transaction.inventory.stocks.index', { department_id: stockCard.department.id })" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">← Kembali ke Stok Barang</Link>
            </template>
            <template #actions>
                <div class="rounded-xl border border-gray-200 bg-white px-5 py-3 dark:border-gray-800 dark:bg-gray-900"><p class="text-xs text-gray-500">Saldo saat ini</p><p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ stockCard.balance.toLocaleString('id-ID') }}</p></div>
            </template>
        </InventoryPageHeader>
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto"><table class="w-full min-w-[1050px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Referensi</th><th class="px-5 py-3">Jenis Mutasi</th><th class="px-5 py-3">Sumber</th><th class="px-5 py-3">Nomor Seri</th><th class="px-5 py-3 text-right">Masuk</th><th class="px-5 py-3 text-right">Keluar</th><th class="px-5 py-3 text-right">Saldo</th><th class="px-5 py-3">Petugas</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="movement in stockCard.movements" :key="movement.id"><td class="whitespace-nowrap px-5 py-3 text-gray-600 dark:text-gray-300">{{ new Date(movement.occurred_at).toLocaleString('id-ID') }}</td><td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ movement.reference_number ?? '—' }}</td><td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ movement.movement_type.replaceAll('_', ' ') }}</td><td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ sourceLabel(movement.source_type) }}</td><td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ movement.serial_number ?? '—' }}</td><td class="px-5 py-3 text-right text-emerald-700">{{ movement.direction === 'IN' ? movement.quantity.toLocaleString('id-ID') : '—' }}</td><td class="px-5 py-3 text-right text-red-700">{{ movement.direction === 'OUT' ? movement.quantity.toLocaleString('id-ID') : '—' }}</td><td class="px-5 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ movement.balance.toLocaleString('id-ID') }}</td><td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ movement.performed_by ?? '—' }}</td></tr>
                    <tr v-if="stockCard.movements.length === 0"><td colspan="9" class="px-5 py-12 text-center text-gray-500">Belum ada pergerakan stok.</td></tr>
                </tbody>
            </table></div>
        </section>
    </div>
</template>
