<script setup lang="ts">
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'
import AssetManagementPageHeader from '@/components/asset/AssetManagementPageHeader.vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'

defineOptions({ layout: AppLayout })

interface AssetSnapshot { [key: string]: string | number | null }
interface Disposal {
    id: string; number: string; disposal_type: string; status: string; disposal_date: string
    amount: string | null; reason: string; asset_snapshot: AssetSnapshot; evidence_path: string | null
    decision_notes: string | null; decided_at: string | null
    asset: { id: string; number: string; status: string; product: { code: string; name: string } }
    requested_by: { name: string } | null; decided_by: { name: string } | null
}
const props = defineProps<{ disposal: Disposal; can_decide: boolean }>()
const approveVisible = ref(false)
const rejectVisible = ref(false)
const approveForm = useForm({ decision_notes: '' })
const rejectForm = useForm({ decision_notes: '' })

function typeLabel(type: string): string {
    return ({ SOLD: 'Dijual', DONATED: 'Dihibahkan', WRITTEN_OFF: 'Dihapuskan' } as Record<string, string>)[type] ?? type
}

function statusLabel(value: string): string {
    return ({ PENDING_APPROVAL: 'Menunggu Keputusan', APPROVED: 'Disetujui', REJECTED: 'Ditolak' } as Record<string, string>)[value] ?? value
}

function printRequest(): void {
    const printUrl = new URL(route('transaction.asset-management.disposals.print', props.disposal.id), window.location.origin)
    printUrl.searchParams.set('print', '1')
    printUrl.searchParams.set('closeAfterPrint', '1')
    window.open(printUrl.toString(), '_blank', 'noopener')
}

function date(value: string | null): string {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(new Date(`${value.slice(0, 10)}T00:00:00`)) : '-'
}

function approve(): void {
    approveForm.post(route('transaction.asset-management.disposals.approve', props.disposal.id), {
        preserveScroll: true,
        onSuccess: () => { approveVisible.value = false },
    })
}

function reject(): void {
    rejectForm.post(route('transaction.asset-management.disposals.reject', props.disposal.id), {
        preserveScroll: true,
        onSuccess: () => { rejectVisible.value = false },
    })
}
</script>

<template>
    <div class="procurement-ui space-y-6">
        <AssetManagementPageHeader :title="disposal.number" section="Disposal" :description="`${disposal.asset.number} · ${disposal.asset.product.name}`">
            <template #badge><Tag :value="statusLabel(disposal.status)" :severity="disposal.status === 'APPROVED' ? 'success' : disposal.status === 'REJECTED' ? 'danger' : 'warn'" /></template>
            <template #actions><Button v-if="disposal.status === 'PENDING_APPROVAL'" label="Cetak Pengajuan" icon="pi pi-print" severity="secondary" outlined @click="printRequest" /><Button v-if="can_decide" label="Tolak" icon="pi pi-times" severity="danger" outlined @click="rejectVisible = true" /><Button v-if="can_decide" label="Tandai Disetujui" icon="pi pi-check" @click="approveVisible = true" /><Link :href="route('transaction.asset-management.assignments.show', disposal.asset.id)"><Button label="Detail Aset" severity="secondary" outlined icon="pi pi-box" /></Link><Link :href="route('transaction.asset-management.disposals.index')"><Button label="Kembali" severity="secondary" outlined /></Link></template>
        </AssetManagementPageHeader>

        <section class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]"><article class="space-y-5 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Informasi pengajuan</p><dl class="mt-4 grid gap-4 sm:grid-cols-2"><div><dt class="text-xs text-gray-500">Jenis</dt><dd class="mt-1 text-sm font-medium">{{ typeLabel(disposal.disposal_type) }}</dd></div><div><dt class="text-xs text-gray-500">Tanggal pelepasan</dt><dd class="mt-1 text-sm font-medium">{{ date(disposal.disposal_date) }}</dd></div><div><dt class="text-xs text-gray-500">Nilai hasil pelepasan</dt><dd class="mt-1 text-sm font-medium">{{ disposal.amount ? `Rp ${Number(disposal.amount).toLocaleString('id-ID')}` : '-' }}</dd></div><div><dt class="text-xs text-gray-500">Diajukan oleh</dt><dd class="mt-1 text-sm font-medium">{{ disposal.requested_by?.name ?? '-' }}</dd></div></dl></div><div class="border-t border-gray-100 pt-4 dark:border-gray-800"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Alasan dan keterangan</h2><p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-200">{{ disposal.reason }}</p></div><div v-if="disposal.evidence_path" class="border-t border-gray-100 pt-4 dark:border-gray-800"><a :href="route('transaction.asset-management.disposals.evidence', disposal.id)" class="inline-flex items-center gap-2 text-sm font-medium text-purple-700 hover:underline dark:text-purple-300"><i class="pi pi-download" /> Unduh dokumen pendukung</a></div></article>
            <aside class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900"><h2 class="font-semibold text-gray-900 dark:text-white">Snapshot aset saat pengajuan</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-xs text-gray-500">Kode aset</dt><dd class="font-medium">{{ disposal.asset_snapshot.number }}</dd></div><div><dt class="text-xs text-gray-500">Produk / serial number</dt><dd class="font-medium">{{ disposal.asset_snapshot.product_name }} · {{ disposal.asset_snapshot.serial_number ?? 'Tanpa serial number' }}</dd></div><div><dt class="text-xs text-gray-500">Kondisi</dt><dd class="font-medium">{{ disposal.asset_snapshot.condition }}</dd></div><div><dt class="text-xs text-gray-500">Perusahaan / departemen</dt><dd class="font-medium">{{ disposal.asset_snapshot.company }} · {{ disposal.asset_snapshot.department }}</dd></div><div><dt class="text-xs text-gray-500">Cabang / lokasi</dt><dd class="font-medium">{{ disposal.asset_snapshot.branch || 'Tanpa cabang' }} · {{ disposal.asset_snapshot.location }}</dd></div><div><dt class="text-xs text-gray-500">Nilai perolehan</dt><dd class="font-medium">{{ disposal.asset_snapshot.acquisition_cost ? `Rp ${Number(disposal.asset_snapshot.acquisition_cost).toLocaleString('id-ID')}` : '-' }}</dd></div></dl></aside>
        </section>

        <section v-if="disposal.status !== 'PENDING_APPROVAL'" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><h2 class="font-semibold text-gray-900 dark:text-white">Keputusan</h2><p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ statusLabel(disposal.status) }} oleh {{ disposal.decided_by?.name ?? '-' }} pada {{ date(disposal.decided_at) }}</p><p v-if="disposal.decision_notes" class="mt-2 whitespace-pre-line text-sm">{{ disposal.decision_notes }}</p></section>

        <Dialog v-model:visible="approveVisible" modal header="Setujui Pelepasan Aset" :style="{ width: 'min(34rem, 94vw)' }"><form class="space-y-4" @submit.prevent="approve"><p class="text-sm text-gray-600 dark:text-gray-300">Aset akan berubah menjadi Disposed dan tidak dapat digunakan pada proses aktif. Riwayat serta data aset tetap tersimpan.</p><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Catatan keputusan (opsional)<textarea v-model="approveForm.decision_notes" rows="3" maxlength="5000" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Catatan persetujuan..." /><small v-if="approveForm.errors.decision_notes" class="font-normal text-red-600">{{ approveForm.errors.decision_notes }}</small></label><div class="flex justify-end gap-2"><Button label="Batal" severity="secondary" text type="button" @click="approveVisible = false" /><Button label="Tandai Disetujui" icon="pi pi-check" type="submit" :loading="approveForm.processing" /></div></form></Dialog>
        <Dialog v-model:visible="rejectVisible" modal header="Tolak Pengajuan Pelepasan" :style="{ width: 'min(34rem, 94vw)' }"><form class="space-y-4" @submit.prevent="reject"><p class="text-sm text-gray-600 dark:text-gray-300">Aset akan dikembalikan ke status Aktif. Alasan penolakan akan disimpan di riwayat.</p><label class="grid gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Alasan penolakan <span class="text-red-600">*</span><textarea v-model="rejectForm.decision_notes" rows="3" minlength="5" maxlength="5000" required class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Jelaskan alasan pengajuan ditolak..." /><small v-if="rejectForm.errors.decision_notes" class="font-normal text-red-600">{{ rejectForm.errors.decision_notes }}</small></label><div class="flex justify-end gap-2"><Button label="Batal" severity="secondary" text type="button" @click="rejectVisible = false" /><Button label="Konfirmasi Tolak" icon="pi pi-times" severity="danger" type="submit" :loading="rejectForm.processing" /></div></form></Dialog>
    </div>
</template>
