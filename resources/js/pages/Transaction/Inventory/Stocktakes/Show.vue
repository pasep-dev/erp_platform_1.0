<script setup lang="ts">
import { computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface StocktakeItem {
    id: string;
    product: { id: string; code: string; name: string; inventory_tracking_mode: string };
    snapshot_quantity: number;
    snapshot_serial_numbers: string[];
    counted_quantity: number | null;
    counted_serial_numbers: string[];
    remarks: string | null;
}
interface Stocktake {
    id: string; number: string; status: string; remarks: string | null; created_at: string; posted_at: string | null;
    department: { id: string; code: string; name: string } | null; location: { code: string; name: string } | null; initiated_by: string | null; items: StocktakeItem[];
}

const props = defineProps<{ stocktake: Stocktake; canManage: boolean }>();
const form = useForm({ items: props.stocktake.items.map((item) => ({
    id: item.id,
    counted_quantity: item.counted_quantity,
    serial_text: item.counted_serial_numbers.join("\n"),
    remarks: item.remarks ?? "",
})) });
const hasUncountedRows = computed(() => form.items.some((item) => item.counted_quantity === null));

function serialsFor(index: number): string[] {
    return form.items[index].serial_text.split(/\r?\n/).map((serial) => serial.trim()).filter(Boolean);
}

function saveCounts(): void {
    form.transform((data) => ({
        items: data.items.map((item, index) => {
            const original = props.stocktake.items[index];
            const serialNumbers = original.product.inventory_tracking_mode === "SERIAL" ? serialsFor(index) : [];
            return {
                id: item.id,
                counted_quantity: original.product.inventory_tracking_mode === "SERIAL" ? serialNumbers.length : Number(item.counted_quantity ?? 0),
                serial_numbers: serialNumbers,
                remarks: item.remarks,
            };
        }),
    })).put(route("transaction.inventory.stocktakes.update", props.stocktake.id), { preserveScroll: true });
}

function postStocktake(): void {
    if (window.confirm("Posting akan membuat pergerakan koreksi permanen sesuai selisih. Lanjutkan?")) {
        form.post(route("transaction.inventory.stocktakes.post", props.stocktake.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="stocktake.number" />
    <div class="procurement-ui space-y-6">
        <InventoryPageHeader :title="stocktake.number" section="Stock Opname" :description="`${stocktake.department?.code} · ${stocktake.department?.name} / ${stocktake.location?.code} · ${stocktake.location?.name}`">
            <template #badge>
                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="stocktake.status === 'POSTED' ? 'bg-emerald-100 text-emerald-800' : stocktake.status === 'COUNTED' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800'">{{ stocktake.status }}</span>
            </template>
            <template #actions>
                <Link :href="route('transaction.inventory.stocktakes.index', { department_id: stocktake.department?.id })" class="inline-flex min-h-10 items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Kembali ke Opname</Link>
            </template>
        </InventoryPageHeader>

        <div v-if="form.errors.stocktake" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ form.errors.stocktake }}</div>
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800"><div><h2 class="font-semibold text-gray-900 dark:text-white">Daftar Hitung</h2><p class="mt-1 text-sm text-gray-500">Snapshot saat sesi dibuat dibandingkan dengan jumlah fisik.</p></div><div v-if="canManage && stocktake.status !== 'POSTED'" class="flex gap-2"><button type="button" :disabled="form.processing" class="rounded-lg border border-violet-300 px-3 py-2 text-sm font-medium text-violet-700 disabled:opacity-50 dark:border-violet-800 dark:text-violet-300" @click="saveCounts">Simpan Hasil Hitung</button><button type="button" :disabled="form.processing || stocktake.status !== 'COUNTED' || hasUncountedRows" class="rounded-lg bg-violet-700 px-3 py-2 text-sm font-medium text-white disabled:opacity-50" @click="postStocktake">Posting Koreksi</button></div></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[1000px] text-left text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-4 py-3">Produk</th><th class="px-4 py-3 text-right">Saldo Snapshot</th><th class="px-4 py-3">Nomor Seri Snapshot</th><th class="px-4 py-3">Hitung Fisik</th><th class="px-4 py-3">Nomor Seri Fisik</th><th class="px-4 py-3">Catatan</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800"><tr v-for="(item, index) in stocktake.items" :key="item.id" class="align-top"><td class="px-4 py-4"><p class="font-medium text-gray-900 dark:text-white">{{ item.product.code }}</p><p class="text-gray-500">{{ item.product.name }}</p><span class="mt-1 inline-block text-xs text-gray-400">{{ item.product.inventory_tracking_mode === 'SERIAL' ? 'Nomor seri' : 'Kuantitas' }}</span></td><td class="px-4 py-4 text-right font-medium">{{ item.snapshot_quantity.toLocaleString('id-ID') }}</td><td class="px-4 py-4 text-xs text-gray-600 dark:text-gray-300">{{ item.snapshot_serial_numbers.join(', ') || '—' }}</td><td class="px-4 py-4"><input v-if="item.product.inventory_tracking_mode !== 'SERIAL'" v-model.number="form.items[index].counted_quantity" type="number" min="0" step="0.001" :disabled="stocktake.status === 'POSTED'" class="h-9 w-28 rounded-md border border-gray-300 px-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="Jumlah" /><span v-else class="text-sm text-gray-700 dark:text-gray-300">{{ serialsFor(index).length }} unit</span></td><td class="px-4 py-4"><textarea v-if="item.product.inventory_tracking_mode === 'SERIAL'" v-model="form.items[index].serial_text" rows="3" :disabled="stocktake.status === 'POSTED'" class="w-56 rounded-md border border-gray-300 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="Satu nomor seri per baris" /><span v-else class="text-gray-400">—</span></td><td class="px-4 py-4"><input v-model="form.items[index].remarks" :disabled="stocktake.status === 'POSTED'" class="h-9 w-48 rounded-md border border-gray-300 px-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="Catatan opsional" /></td></tr><tr v-if="stocktake.items.length === 0"><td colspan="6" class="px-5 py-10 text-center text-gray-500">Tidak ada produk consumable aktif.</td></tr></tbody></table></div>
            <p v-if="form.errors.items" class="border-t border-red-100 px-5 py-3 text-sm text-red-700">{{ form.errors.items }}</p>
        </section>
        <p v-if="stocktake.status === 'POSTED'" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">Opname diposting {{ stocktake.posted_at ? new Date(stocktake.posted_at).toLocaleString('id-ID') : '' }}. Koreksi tercatat di Kartu Stok dan Riwayat Operasi.</p>
    </div>
</template>
