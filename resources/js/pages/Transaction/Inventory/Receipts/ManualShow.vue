<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AppLayout from "@/layout/AppLayout.vue";
import InventoryPageHeader from "@/components/inventory/InventoryPageHeader.vue";

defineOptions({ layout: AppLayout });

interface ReceiptItem {
    id: string;
    product: { id: string; code: string; name: string; inventory_tracking_mode: string } | null;
    quantity: number;
    serial_numbers: string[];
    reason: string | null;
}

const props = defineProps<{
    receipt: {
        id: string;
        number: string;
        status: string;
        approval_mode: string;
        occurred_at: string;
        submitted_at: string | null;
        posted_at: string | null;
        remarks: string | null;
        department: { id: string; code: string; name: string } | null;
        location: { id: string; code: string; name: string } | null;
        submitted_by: { id: number; name: string } | null;
        posted_by: { id: number; name: string } | null;
        manual_approval_remarks: string | null;
        manual_approved_at: string | null;
        manual_approver: { id: number; name: string } | null;
        has_manual_approval_document: boolean;
        items: ReceiptItem[];
    };
    approval: {
        workflow_name: string | null;
        status: string;
        current_level: number | null;
        levels: Array<{ level: number; status: string; approver: string | null; acted_at: string | null; remarks: string | null }>;
        manual_approval_remarks: string | null;
        manual_approved_at: string | null;
        manual_approver: { id: number; name: string } | null;
        has_manual_approval_document: boolean;
    } | null;
    canManualApprove: boolean;
    canActInWorkflow: boolean;
}>();

const approvalFile = ref<File | null>(null);
const form = useForm({ remarks: "", signed_document: null as File | null });

function selectFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.signed_document = input.files?.[0] ?? null;
    approvalFile.value = input.files?.[0] ?? null;
}

function markApproved(): void {
    form.post(route("transaction.inventory.receipts.manual.mark-approved", props.receipt.id), {
        forceFormData: true,
        preserveScroll: true,
    });
}

function statusTone(status: string): string {
    if (status === "POSTED" || status === "APPROVED") return "bg-emerald-100 text-emerald-800";
    if (status === "REJECTED") return "bg-red-100 text-red-800";
    return "bg-amber-100 text-amber-800";
}
</script>

<template>
    <Head :title="`Penerimaan Manual ${receipt.number}`" />

    <div class="procurement-ui space-y-6">
        <InventoryPageHeader :title="receipt.number" section="Penerimaan Manual" description="Saldo barang existing hanya dibukukan setelah persetujuan final.">
            <template #badge>
                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusTone(receipt.status)">{{ receipt.status.replaceAll('_', ' ') }}</span>
            </template>
            <template #actions>
                <Link :href="route('transaction.inventory.receipts.index', { department_id: receipt.department?.id })" class="inline-flex min-h-10 items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Kembali ke Penerimaan</Link>
            </template>
        </InventoryPageHeader>

        <section class="grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-2 xl:grid-cols-4">
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-500">Departemen</p><p class="mt-1 font-medium text-gray-900 dark:text-white">{{ receipt.department?.code }} · {{ receipt.department?.name }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-500">Gudang tujuan</p><p class="mt-1 font-medium text-gray-900 dark:text-white">{{ receipt.location?.code }} · {{ receipt.location?.name }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-500">Tanggal cut-off</p><p class="mt-1 font-medium text-gray-900 dark:text-white">{{ new Date(receipt.occurred_at).toLocaleDateString('id-ID') }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-500">Diajukan oleh</p><p class="mt-1 font-medium text-gray-900 dark:text-white">{{ receipt.submitted_by?.name ?? '—' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-500">Metode persetujuan</p><p class="mt-1 font-medium text-gray-900 dark:text-white">{{ receipt.approval_mode === 'WORKFLOW' ? 'Workflow approval' : 'Mark Approval manual' }}</p></div>
            <div class="sm:col-span-2 xl:col-span-4"><p class="text-xs font-medium uppercase tracking-wide text-gray-500">Alasan / referensi</p><p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ receipt.remarks ?? '—' }}</p></div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Rincian Saldo Awal</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-5 py-3">Produk</th><th class="px-5 py-3">Pelacakan</th><th class="px-5 py-3 text-right">Kuantitas</th><th class="px-5 py-3">Nomor Seri</th><th class="px-5 py-3">Catatan</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="item in receipt.items" :key="item.id">
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ item.product?.code }} · {{ item.product?.name }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ item.product?.inventory_tracking_mode === 'SERIAL' ? 'Nomor seri' : 'Kuantitas' }}</td>
                            <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">{{ item.quantity.toLocaleString('id-ID') }}</td>
                            <td class="max-w-sm whitespace-pre-line px-5 py-3 text-gray-600 dark:text-gray-400">{{ item.serial_numbers.join(', ') || '—' }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ item.reason ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="approval" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="font-semibold text-gray-900 dark:text-white">Persetujuan</h2><p class="mt-1 text-sm text-gray-500">{{ approval.workflow_name ?? 'Workflow approval' }}</p></div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusTone(approval.status)">{{ approval.status }}</span>
            </div>
            <ol class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <li v-for="level in approval.levels" :key="level.level" class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-2"><p class="font-medium text-gray-900 dark:text-white">Level {{ level.level }} · {{ level.approver ?? 'Approver' }}</p><span class="text-xs font-semibold" :class="level.status === 'APPROVED' ? 'text-emerald-700' : level.status === 'REJECTED' ? 'text-red-700' : 'text-amber-700'">{{ level.status }}</span></div>
                    <p v-if="level.acted_at" class="mt-1 text-xs text-gray-500">{{ new Date(level.acted_at).toLocaleString('id-ID') }}</p>
                    <p v-if="level.remarks" class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ level.remarks }}</p>
                </li>
            </ol>
            <p v-if="canActInWorkflow" class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:bg-blue-950/30 dark:text-blue-200">Dokumen menunggu tindakan Anda di menu My Approval.</p>
        </section>

        <section v-if="receipt.approval_mode === 'MANUAL' && receipt.manual_approver" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="font-semibold text-gray-900 dark:text-white">Persetujuan manual</h2>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Disetujui oleh {{ receipt.manual_approver.name }}<span v-if="receipt.manual_approved_at"> · {{ new Date(receipt.manual_approved_at).toLocaleString('id-ID') }}</span></p>
            <p class="mt-1 whitespace-pre-line text-sm text-gray-600 dark:text-gray-400">{{ receipt.manual_approval_remarks }}</p>
            <a v-if="receipt.has_manual_approval_document" :href="route('transaction.inventory.receipts.manual.approval-document', receipt.id)" class="mt-3 inline-flex text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">Unduh bukti persetujuan</a>
        </section>

        <section v-if="canManualApprove" class="space-y-4 rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/20">
            <div><h2 class="font-semibold text-amber-950 dark:text-amber-100">Catat persetujuan manual</h2><p class="mt-1 text-sm text-amber-800 dark:text-amber-200">Unggah bukti bahwa saldo awal telah disetujui di luar aplikasi. Setelah dicatat, stok langsung dibukukan.</p></div>
            <form class="space-y-4" @submit.prevent="markApproved">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Bukti persetujuan (PDF/JPG/PNG)
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" required class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white p-2 text-sm dark:border-gray-700 dark:bg-gray-950" @change="selectFile" />
                    <small v-if="form.errors.signed_document" class="mt-1 block font-normal text-red-600">{{ form.errors.signed_document }}</small>
                </label>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Catatan persetujuan
                    <textarea v-model="form.remarks" rows="3" maxlength="2000" required class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950"></textarea>
                    <small v-if="form.errors.remarks" class="mt-1 block font-normal text-red-600">{{ form.errors.remarks }}</small>
                </label>
                <div class="flex justify-end"><button type="submit" :disabled="form.processing || !approvalFile" class="rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Memproses…' : 'Mark Approval & Posting Stok' }}</button></div>
            </form>
        </section>

        <section v-if="receipt.status === 'POSTED'" class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm dark:border-emerald-900 dark:bg-emerald-950/30">
            <p class="font-semibold text-emerald-900 dark:text-emerald-100">Saldo sudah masuk ke kartu stok</p>
            <p class="mt-1 text-emerald-800 dark:text-emerald-200">Diposting oleh {{ receipt.posted_by?.name ?? '—' }}<span v-if="receipt.posted_at"> · {{ new Date(receipt.posted_at).toLocaleString('id-ID') }}</span>.</p>
        </section>
    </div>
</template>
