// 1. State
const showDetailModal = ref(false);
const detailData = ref<YourModelType | null>(null);

// 2. Fungsi Trigger
function viewDetail(item: YourModelType): void {
    detailData.value = item;
    showDetailModal.value = true;
}

function closeDetailModal(): void {
    showDetailModal.value = false;
    detailData.value = null;
}

<!-- contoh: @/components/category/CategoryDetailContent.vue -->
<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { route } from "ziggy-js";

interface Category {
    id: string;
    code: string;
    name: string;
    description?: string | null;
    is_active: boolean;
}

defineProps<{ item: Category }>();
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[1.5fr_0.8fr]">
        <!-- Sisi Kiri: Form Read-Only Ringkas -->
        <div class="space-y-4">
            <div class="border-b border-gray-100 pb-3 dark:border-gray-800">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                    Category Name
                </p>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                    {{ item.name }}
                </h2>
            </div>

            <div class="grid gap-3.5 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Code
                    </label>
                    <input
                        type="text"
                        readonly
                        :value="item.code"
                        class="h-9 w-full rounded-lg border border-gray-200 bg-gray-50/70 px-3 text-sm font-medium text-gray-900 outline-none dark:border-gray-700 dark:bg-gray-800/50 dark:text-white"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Status
                    </label>
                    <div
                        class="flex h-9 w-full items-center rounded-lg border border-gray-200 bg-gray-50/70 px-3 text-sm font-semibold dark:border-gray-700 dark:bg-gray-800/50"
                        :class="item.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500'"
                    >
                        {{ item.is_active ? 'Active' : 'Inactive' }}
                    </div>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Description
                </label>
                <textarea
                    readonly
                    rows="2"
                    :value="item.description || 'No description provided.'"
                    class="w-full resize-none rounded-lg border border-gray-200 bg-gray-50/70 p-2.5 text-sm font-medium text-gray-800 outline-none dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-200"
                ></textarea>
            </div>
        </div>

        <!-- Sisi Kanan: Panel Quick Actions -->
        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-900/30">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                Quick actions
            </h3>
            <div class="mt-3 space-y-2">
                <Link
                    :href="route('master.product.categories.edit', item.id)"
                    class="flex w-full items-center justify-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-800"
                >
                    Edit category
                </Link>
            </div>
        </div>
    </div>
</template>

<!-- Di template index.vue -->
<Modal
    :show="showDetailModal"
    title="Category Detail"
    max-width="4xl"
    @close="closeDetailModal"
>
    <CategoryDetailContent v-if="detailData" :item="detailData" />

    <template #footer>
        <button
            type="button"
            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
            @click="closeDetailModal"
        >
            Close
        </button>
    </template>
</Modal>