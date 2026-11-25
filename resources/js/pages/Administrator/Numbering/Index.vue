<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { route } from "ziggy-js";
import { Info } from "lucide-vue-next";

import AppPagination from "@/components/common/AppPagination.vue";
import AppSelect from "@/components/common/AppSelect.vue";
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";
import Header from "@/components/content/Header.vue";
import CrudActions from "@/components/crud/CrudActions.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import Modal from "@/components/common/Modal.vue";
import AppLayout from "@/layout/AppLayout.vue";

defineOptions({
    layout: AppLayout,
});

interface NumberingDocument {
    id: string;
    code: string;
    name: string;
    pattern: string;
    sequence_length: number;
    sequence_start: number;
    reset_period: "monthly" | "yearly" | "never";
    is_active: boolean;
    module: NumberingModule | null;
}

interface NumberingModule {
    code: string;
    name: string;
    category: string;
    placeholders?: string[];
}

interface PaginatedDocuments {
    data: NumberingDocument[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

const props = defineProps<{
    documents: PaginatedDocuments;
    filters: {
        search?: string;
        is_active?: string;
        per_page?: number;
    };
    placeholders: string[];
    modules: Record<string, NumberingModule>;
}>();

const showForm = ref(false);
const selectedDocument = ref<NumberingDocument | null>(null);
const search = ref(props.filters.search ?? "");
const allFilterValue = "ALL";
const selectedStatus = ref(props.filters.is_active ?? allFilterValue);
const perPage = ref(props.filters.per_page ?? 5);

const statusOptions = [
    { label: "All Statuses", value: allFilterValue },
    { label: "Active", value: "1" },
    { label: "Inactive", value: "0" },
];

const columns = [
    { key: "document", label: "Document" },
    { key: "module", label: "Connected Module" },
    { key: "pattern", label: "Format" },
    { key: "sequence", label: "Sequence" },
    { key: "reset_period", label: "Reset Period" },
    { key: "is_active", label: "Status" },
];

const form = useForm({
    code: "",
    name: "",
    pattern: "",
    sequence_length: 6,
    sequence_start: 1,
    reset_period: "yearly" as NumberingDocument["reset_period"],
    is_active: true,
});

const activePlaceholders = computed(() =>
    props.modules[form.code]?.placeholders ?? props.placeholders,
);

const inputClass =
    "h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-gray-700 dark:bg-gray-950 dark:text-white";
const labelClass =
    "mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300";
const errorClass = "mt-1.5 text-xs text-red-600 dark:text-red-400";

function openCreateForm(): void {
    selectedDocument.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function handleModuleChange(): void {
    const module = props.modules[form.code];

    if (!module) {
        return;
    }

    form.name = module.name;
}

function appendPlaceholder(placeholder: string): void {
    const token = formatPlaceholder(placeholder);

    if (form.pattern.includes(token)) {
        return;
    }

    const separator = form.pattern.length > 0 && !/[\-_/]$/.test(form.pattern)
        ? "-"
        : "";

    form.pattern = `${form.pattern}${separator}${token}`;
}

function openEditForm(document: NumberingDocument): void {
    selectedDocument.value = document;
    form.code = document.code;
    form.name = document.name;
    form.pattern = document.pattern;
    form.sequence_length = document.sequence_length;
    form.sequence_start = document.sequence_start;
    form.reset_period = document.reset_period;
    form.is_active = document.is_active;
    form.clearErrors();
    showForm.value = true;
}

function closeForm(): void {
    if (form.processing) {
        return;
    }

    showForm.value = false;
    form.clearErrors();
}

function submit(): void {
    const options = {
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    };

    if (selectedDocument.value) {
        form.put(
            route("administrator.numbering-documents.update", {
                numbering_document: selectedDocument.value.id,
            }),
            options,
        );

        return;
    }

    form.post(route("administrator.numbering-documents.store"), options);
}

function submitSearch(): void {
    router.get(
        route("administrator.numbering-documents.index"),
        {
            search: search.value || undefined,
            is_active:
                selectedStatus.value === allFilterValue
                    ? undefined
                    : selectedStatus.value,
            per_page: perPage.value,
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
    selectedStatus.value = allFilterValue;
    perPage.value = 5;
    submitSearch();
}

function formatPlaceholder(placeholder: string): string {
    return `{${placeholder}}`;
}
</script>

<template>
    <div class="space-y-6">
        <Header
            title="Document Numbering"
            :description="'Kelola format penomoran dokumen transaksi dan master data.'"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                    @click="openCreateForm"
                >
                    Tambah Penomoran
                </button>
            </template>
        </Header>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-800"
            >
                <div class="flex flex-1 flex-wrap items-center gap-3">
                    <SearchFilterBar
                        v-model="search"
                        placeholder="Search document numbering..."
                        class="w-full sm:w-64"
                        @search="submitSearch"
                        @clear="clearSearch"
                    />
                    <AppSelect
                        v-model="selectedStatus"
                        :options="statusOptions"
                        class="w-full sm:w-40"
                        @change="submitSearch"
                    />
                </div>

                <div
                    class="whitespace-nowrap rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-500 dark:bg-gray-900 dark:text-gray-400"
                >
                    Total {{ documents.total }} Rules
                </div>
            </div>

            <CrudTable
                :columns="columns"
                :rows="documents.data"
                empty-message="Tidak ada aturan penomoran."
            >
                <template #cell-document="{ row }">
                    <div class="flex flex-col">
                        <span class="font-semibold text-gray-900 dark:text-white">
                            {{ row.name }}
                        </span>
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            {{ row.code }}
                        </span>
                    </div>
                </template>

                <template #cell-pattern="{ row }">
                    <code class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                        {{ row.pattern }}
                    </code>
                </template>

                <template #cell-module="{ row }">
                    <div v-if="row.module" class="flex flex-col">
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ row.module.name }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ row.module.is_active ? row.module.category : "Integrasi modul nonaktif" }}
                        </span>
                    </div>
                    <span
                        v-else
                        class="inline-flex w-fit items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-400"
                    >
                        Belum terhubung
                    </span>
                </template>

                <template #cell-sequence="{ row }">
                    <div class="flex flex-col">
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ row.sequence_length }} digits
                        </span>
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            Starts at {{ row.sequence_start }}
                        </span>
                    </div>
                </template>

                <template #cell-reset_period="{ row }">
                    <span class="capitalize">{{ row.reset_period }}</span>
                </template>

                <template #cell-is_active="{ row }">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium"
                        :class="
                            row.is_active
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                        "
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="row.is_active ? 'bg-emerald-500' : 'bg-gray-400'"
                        ></span>
                        {{ row.is_active ? "Active" : "Inactive" }}
                    </span>
                </template>

                <template #actions="{ row }">
                    <CrudActions
                        :show-edit="true"
                        :show-delete="false"
                        :processing="form.processing"
                        @edit="openEditForm(row)"
                    />
                </template>
            </CrudTable>

            <AppPagination
                v-model:per-page="perPage"
                :meta="documents"
                @change="submitSearch"
            />
        </div>

        <section class="rounded-xl border border-blue-100 bg-blue-50/70 p-5 dark:border-blue-900/60 dark:bg-blue-950/30">
            <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-200">
                Placeholder yang tersedia
            </h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <code
                    v-for="placeholder in activePlaceholders"
                    :key="placeholder"
                    class="rounded-md border border-blue-100 bg-white px-2 py-1 text-xs text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200"
                >
                    {{ formatPlaceholder(placeholder) }}
                </code>
            </div>
            <p class="mt-3 text-xs text-blue-800 dark:text-blue-300">
                Pilih modul terdaftar saat membuat aturan agar kode cocok dengan pemanggilan aplikasi. Format wajib memiliki <code>{SEQ}</code>. Aturan lama yang tidak terhubung ditandai di tabel dan tetap dapat diedit tanpa mengganti kodenya.
            </p>
        </section>
    </div>

    <Modal
        :show="showForm"
        :title="selectedDocument ? 'Ubah Penomoran' : 'Tambah Penomoran'"
        max-width="2xl"
        @close="closeForm"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="numbering-module" :class="labelClass">
                        Modul
                    </label>
                    <select
                        id="numbering-module"
                        v-model="form.code"
                        :disabled="Boolean(selectedDocument)"
                        :class="inputClass"
                        @change="handleModuleChange"
                    >
                        <option
                            v-if="selectedDocument && !modules[form.code]"
                            :value="form.code"
                        >
                            {{ form.code }} — belum terhubung
                        </option>
                        <option value="" disabled>
                            Pilih modul yang akan menggunakan penomoran
                        </option>
                        <option
                            v-for="(module, code) in modules"
                            :key="code"
                            :value="code"
                        >
                            {{ module.category }} — {{ module.name }} ({{ code }})
                        </option>
                    </select>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                        Pilih modul aplikasi yang sudah terhubung. Kode dokumen akan mengikuti identitas modul tersebut.
                    </p>
                    <p v-if="form.errors.code" :class="errorClass">
                        {{ form.errors.code }}
                    </p>
                </div>

                <div>
                    <label for="numbering-code" :class="labelClass">
                        Kode Dokumen
                    </label>
                    <input
                        id="numbering-code"
                        :value="form.code"
                        type="text"
                        readonly
                        :class="[
                            inputClass,
                            'cursor-not-allowed bg-gray-100 dark:bg-gray-800',
                        ]"
                    />
                </div>

                <div>
                    <label for="numbering-name" :class="labelClass">
                        Nama Dokumen
                    </label>
                    <input
                        id="numbering-name"
                        v-model="form.name"
                        type="text"
                        maxlength="150"
                        :class="inputClass"
                    />
                    <p v-if="form.errors.name" :class="errorClass">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <label for="numbering-pattern" :class="labelClass">
                        Format Nomor
                    </label>
                    <div class="mb-2 flex flex-wrap gap-2">
                        <button
                            v-for="placeholder in activePlaceholders"
                            :key="placeholder"
                            type="button"
                            class="rounded-md border border-blue-200 bg-blue-50 px-2 py-1 text-xs text-blue-800 transition hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200 dark:hover:bg-blue-900"
                            @click="appendPlaceholder(placeholder)"
                        >
                            + {{ formatPlaceholder(placeholder) }}
                        </button>
                    </div>
                    <input
                        id="numbering-pattern"
                        v-model="form.pattern"
                        type="text"
                        maxlength="255"
                        placeholder="Contoh: VND-{YYYY}-{SEQ}"
                        :class="inputClass"
                    />
                    <p v-if="form.errors.pattern" :class="errorClass">
                        {{ form.errors.pattern }}
                    </p>
                </div>

                <div>
                    <label for="sequence-length" :class="labelClass">
                        Panjang Sequence
                    </label>
                    <input
                        id="sequence-length"
                        v-model.number="form.sequence_length"
                        type="number"
                        min="1"
                        max="12"
                        :class="inputClass"
                    />
                    <p v-if="form.errors.sequence_length" :class="errorClass">
                        {{ form.errors.sequence_length }}
                    </p>
                </div>

                <div>
                    <label for="sequence-start" :class="labelClass">
                        Mulai Dari
                    </label>
                    <input
                        id="sequence-start"
                        v-model.number="form.sequence_start"
                        type="number"
                        min="1"
                        max="2147483647"
                        :class="inputClass"
                    />
                    <p v-if="form.errors.sequence_start" :class="errorClass">
                        {{ form.errors.sequence_start }}
                    </p>
                </div>

                <div>
                    <label for="reset-period" :class="labelClass">
                        Reset Sequence
                    </label>
                    <select
                        id="reset-period"
                        v-model="form.reset_period"
                        :class="inputClass"
                    >
                        <option value="never">Tidak pernah</option>
                        <option value="yearly">Setiap tahun</option>
                        <option value="monthly">Setiap bulan</option>
                    </select>
                    <p v-if="form.errors.reset_period" :class="errorClass">
                        {{ form.errors.reset_period }}
                    </p>
                </div>

                <label class="flex items-center gap-3 self-end pb-2 text-sm text-gray-700 dark:text-gray-300">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
                    />
                    Aturan aktif
                </label>
            </div>

            <p v-if="form.errors.is_active" :class="errorClass">
                {{ form.errors.is_active }}
            </p>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                <button
                    type="button"
                    class="h-10 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                    :disabled="form.processing"
                    @click="closeForm"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="h-10 rounded-lg bg-brand-600 px-4 text-sm font-medium text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ form.processing ? "Menyimpan..." : "Simpan" }}
                </button>
            </div>
        </form>

        <template #footer>
            <p class="mr-auto text-xs text-gray-500 dark:text-gray-400">
                Nilai mulai berlaku untuk sequence baru; sequence yang sudah berjalan tidak direset.
            </p>
        </template>
    </Modal>
</template>
