<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { Plus } from "lucide-vue-next";
import Tag from "primevue/tag";
import AppLayout from "@/layout/AppLayout.vue";
import AppPagination from "@/components/common/AppPagination.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface InventoryRequestRow {
    id: string;
    number: string;
    status: string;
    requested_at: string;
    required_at: string | null;
    department: { id: string; code: string; name: string };
    requester: { id: number; name: string };
    items: Array<{ id: string; requested_quantity: string; fulfilled_quantity: string; product: { code: string; name: string } }>;
}

const props = defineProps<{
    requests: {
        data: InventoryRequestRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();
const perPage = ref(props.requests.per_page);

function applyPagination(): void {
    router.get(route("transaction.inventory.requests.index"), { per_page: perPage.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const statusText: Record<string, string> = {
    DRAFT: "Draft",
    PENDING_APPROVAL: "Menunggu Persetujuan",
    APPROVED: "Disetujui",
    PARTIALLY_FULFILLED: "Dipenuhi Sebagian",
    FULFILLED: "Terpenuhi",
    REJECTED: "Ditolak",
    CANCELLED: "Dibatalkan",
};

function severity(status: string): "secondary" | "warn" | "success" | "danger" | "info" {
    if (status === "REJECTED" || status === "CANCELLED") return "danger";
    if (status === "PENDING_APPROVAL") return "warn";
    if (["APPROVED", "PARTIALLY_FULFILLED"].includes(status)) return "info";
    if (status === "FULFILLED") return "success";
    return "secondary";
}
</script>

<template>
    <Head title="Permintaan Barang" />

    <div class="procurement-ui space-y-6">
        <InventoryPageHeader title="Permintaan Barang" section="Permintaan Barang" description="Pantau persetujuan dan pemenuhan barang consumable per departemen.">
            <template #actions>
                <Link :href="route('transaction.inventory.requests.create')" class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-700/15 transition hover:bg-brand-800">
                    <Plus class="size-4" />
                    Buat Permintaan
                </Link>
            </template>
        </InventoryPageHeader>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-5 py-3">Nomor</th><th class="px-5 py-3">Departemen / Pemohon</th><th class="px-5 py-3">Barang</th><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="item in requests.data" :key="item.id">
                            <td class="px-5 py-4 font-semibold text-gray-900 dark:text-white">{{ item.number }}</td>
                            <td class="px-5 py-4"><p class="font-medium text-gray-800 dark:text-gray-200">{{ item.department.code }} · {{ item.department.name }}</p><p class="mt-1 text-xs text-gray-500">{{ item.requester.name }}</p></td>
                            <td class="px-5 py-4"><p v-for="line in item.items.slice(0, 2)" :key="line.id" class="text-gray-700 dark:text-gray-300">{{ line.product.code }} · {{ line.product.name }} <span class="text-xs text-gray-500">({{ line.fulfilled_quantity }}/{{ line.requested_quantity }})</span></p><p v-if="item.items.length > 2" class="mt-1 text-xs text-gray-500">dan {{ item.items.length - 2 }} item lainnya</p></td>
                            <td class="px-5 py-4 text-gray-600 dark:text-gray-400"><p>{{ item.requested_at }}</p><p v-if="item.required_at" class="mt-1 text-xs">Dibutuhkan {{ item.required_at }}</p></td>
                            <td class="px-5 py-4"><Tag :value="statusText[item.status] ?? item.status" :severity="severity(item.status)" /></td>
                            <td class="px-5 py-4 text-right"><Link :href="route('transaction.inventory.requests.show', item.id)" class="font-medium text-violet-700 hover:underline dark:text-violet-300">View</Link></td>
                        </tr>
                        <tr v-if="requests.data.length === 0"><td colspan="6" class="px-5 py-12 text-center text-gray-500">Belum ada permintaan barang.</td></tr>
                    </tbody>
                </table>
            </div>
            <AppPagination v-model:per-page="perPage" :meta="requests" @change="applyPagination" />
        </section>
    </div>
</template>
