<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, router, Head } from "@inertiajs/vue3";
import { Plus, Search, CreditCard } from "lucide-vue-next";
import { route } from "ziggy-js";

import AppLayout from "@/layout/AppLayout.vue";
import CrudActions from "@/components/crud/CrudActions.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import DeleteConfirm from "@/components/crud/DeleteConfirm.vue";
import { useCrud } from "@/composables/useCrud";
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";
import ProcurementPageHeader from "@/components/procurement/ProcurementPageHeader.vue";

defineOptions({
    layout: AppLayout,
});

interface PaymentTerm {
    id: string;
    code: string;
    name: string;
    due_days: number;
    description: string | null;
    is_active: boolean;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedPaymentTerms {
    data: PaymentTerm[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    paymentTerms: PaginatedPaymentTerms;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search);

const hasPaymentTerms = computed(() => props.paymentTerms.data.length > 0);

const columns = [
    {
        key: "code",
        label: "Code",
    },
    {
        key: "name",
        label: "Payment Term",
    },
    {
        key: "due_days",
        label: "Due Days",
    },
    {
        key: "description",
        label: "Description",
    },
    {
        key: "is_active",
        label: "Status",
    },
];

const showDeleteConfirm = ref(false);
const selectedPaymentTerm = ref<PaymentTerm | null>(null);

const { processing, destroy } = useCrud({
    indexRoute: route("master.procurement.payment-terms.index"),

    onSuccess: () => {
        showDeleteConfirm.value = false;
        selectedPaymentTerm.value = null;
    },
});

function submitSearch(): void {
    router.get(
        route("master.procurement.payment-terms.index"),
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function clearSearch(): void {
    search.value = "";
    submitSearch();
}

function deletePaymentTerm(paymentTerm: PaymentTerm): void {
    selectedPaymentTerm.value = paymentTerm;
    showDeleteConfirm.value = true;
}

function cancelDelete(): void {
    if (processing.value) {
        return;
    }

    showDeleteConfirm.value = false;
    selectedPaymentTerm.value = null;
}

function confirmDelete(): void {
    if (!selectedPaymentTerm.value) {
        return;
    }

    destroy(
        route(
            "master.procurement.payment-terms.destroy",
            selectedPaymentTerm.value.id,
        ),
    );
}
</script>

<template>
    <Head title="Payment Terms" />
    <div class="procurement-ui space-y-6">
        <ProcurementPageHeader
            title="Payment Terms"
            section="Master Data"
            description="Kelola ketentuan pembayaran yang berlaku untuk transaksi vendor."
            :action-url="route('master.procurement.payment-terms.create')"
            action-label="Tambah Payment Term"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <SearchFilterBar
                    v-model="search"
                    :total="paymentTerms.total"
                    placeholder="Cari data..."
                    @search="submitSearch"
                    @clear="clearSearch"
                />
            </div>

            <CrudTable
                :columns="columns"
                :rows="paymentTerms.data"
                :empty-message="
                    search
                        ? 'Tidak ada payment term yang sesuai dengan pencarian.'
                        : 'Belum ada data payment term.'
                "
            >
                <template #cell-due_days="{ row }">
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        {{ row.due_days }} hari
                    </span>
                </template>

                <template #cell-description="{ row }">
                    <span
                        class="block max-w-md truncate text-gray-600 dark:text-gray-400"
                        :title="row.description || ''"
                    >
                        {{ row.description || "-" }}
                    </span>
                </template>

                <template #cell-is_active="{ row }">
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            row.is_active
                                ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400'
                        "
                    >
                        {{ row.is_active ? "Active" : "Inactive" }}
                    </span>
                </template>

                <template #actions="{ row }">
                    <CrudActions
                        variant="procurement"
                        :processing="processing"
                        @edit="
                            router.visit(
                                route(
                                    'master.procurement.payment-terms.edit',
                                    row.id,
                                ),
                            )
                        "
                        @delete="deletePaymentTerm(row)"
                    />
                </template>
            </CrudTable>

            <div
                v-if="paymentTerms.last_page > 1"
                class="flex flex-col gap-3 border-t border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ paymentTerms.from }}–{{
                        paymentTerms.to
                    }}
                    dari {{ paymentTerms.total }} data
                </p>

                <div class="flex flex-wrap gap-1">
                    <template
                        v-for="link in paymentTerms.links"
                        :key="link.label"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="rounded-lg px-3 py-2 text-xs font-medium"
                            :class="
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/[0.05]'
                            "
                            v-html="link.label"
                        />

                        <span
                            v-else
                            class="rounded-lg px-3 py-2 text-xs text-gray-300 dark:text-gray-600"
                            v-html="link.label"
                        ></span>
                    </template>
                </div>
            </div>
        </div>

        <DeleteConfirm
            :show="showDeleteConfirm"
            :processing="processing"
            title="Hapus Payment Term"
            :message="
                selectedPaymentTerm
                    ? `Apakah Anda yakin ingin menghapus payment term '${selectedPaymentTerm.name}'?`
                    : 'Apakah Anda yakin ingin menghapus data ini?'
            "
            @close="cancelDelete"
            @confirm="confirmDelete"
        />
    </div>
</template>
