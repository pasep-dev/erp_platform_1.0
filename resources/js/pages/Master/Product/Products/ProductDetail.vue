<!-- resources/js/components/product/ProductDetailContent.vue -->
<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { route } from "ziggy-js";

interface Category {
    id: string;
    name: string;
}
interface Type {
    id: string;
    name: string;
}
interface Brand {
    id: string;
    name: string;
}
interface Model {
    id: string;
    name: string;
}

interface Product {
    id: string;
    code: string;
    name: string;
    description: string | null;
    group: string;
    category: Category | null;
    type: Type | null;
    brand: Brand | null;
    model: Model | null;
    is_active: boolean;
}

defineProps<{ product: Product }>();

function getGroupClass(group: string): string {
    if (group === "ASSET")
        return "bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400";
    if (group === "CONSUMABLE")
        return "bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400";
    return "bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-400";
}
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[1.5fr_0.8fr]">
        <!-- Sisi Kiri: Form Read-Only Ringkas -->
        <div class="space-y-4">
            <!-- Header Ringkas -->
            <div
                class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800"
            >
                <div>
                    <p
                        class="text-[11px] font-semibold uppercase tracking-wider text-gray-400"
                    >
                        Product Name
                    </p>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ product.name }}
                    </h2>
                </div>
                <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="getGroupClass(product.group)"
                >
                    {{ product.group }}
                </span>
            </div>

            <!-- Field Inputs Ringkas (2 Kolom) -->
            <div class="grid gap-3.5 sm:grid-cols-2">
                <div>
                    <label
                        class="mb-1 block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                    >
                        Code
                    </label>
                    <input
                        type="text"
                        readonly
                        :value="product.code"
                        class="h-9 w-full rounded-lg border border-gray-200 bg-gray-50/70 px-3 text-sm font-medium text-gray-900 outline-none dark:border-gray-700 dark:bg-gray-800/50 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                    >
                        Status
                    </label>
                    <div
                        class="flex h-9 w-full items-center rounded-lg border border-gray-200 bg-gray-50/70 px-3 text-sm font-semibold dark:border-gray-700 dark:bg-gray-800/50"
                        :class="
                            product.is_active
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-gray-500'
                        "
                    >
                        {{ product.is_active ? "Active" : "Inactive" }}
                    </div>
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                    >
                        Category
                    </label>
                    <input
                        type="text"
                        readonly
                        :value="product.category?.name || '-'"
                        class="h-9 w-full rounded-lg border border-gray-200 bg-gray-50/70 px-3 text-sm font-medium text-gray-900 outline-none dark:border-gray-700 dark:bg-gray-800/50 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                    >
                        Type
                    </label>
                    <input
                        type="text"
                        readonly
                        :value="product.type?.name || '-'"
                        class="h-9 w-full rounded-lg border border-gray-200 bg-gray-50/70 px-3 text-sm font-medium text-gray-900 outline-none dark:border-gray-700 dark:bg-gray-800/50 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                    >
                        Brand
                    </label>
                    <input
                        type="text"
                        readonly
                        :value="product.brand?.name || '-'"
                        class="h-9 w-full rounded-lg border border-gray-200 bg-gray-50/70 px-3 text-sm font-medium text-gray-900 outline-none dark:border-gray-700 dark:bg-gray-800/50 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                    >
                        Model
                    </label>
                    <input
                        type="text"
                        readonly
                        :value="product.model?.name || '-'"
                        class="h-9 w-full rounded-lg border border-gray-200 bg-gray-50/70 px-3 text-sm font-medium text-gray-900 outline-none dark:border-gray-700 dark:bg-gray-800/50 dark:text-white"
                    />
                </div>
            </div>

            <!-- Description Field -->
            <div>
                <label
                    class="mb-1 block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                >
                    Description
                </label>
                <textarea
                    readonly
                    rows="2"
                    :value="product.description || 'No description provided.'"
                    class="w-full resize-none rounded-lg border border-gray-200 bg-gray-50/70 p-2.5 text-sm font-medium text-gray-800 outline-none dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-200"
                ></textarea>
            </div>
        </div>

        <!-- Sisi Kanan: Panel Quick Actions -->
        <div
            class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-900/30"
        >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                Quick actions
            </h3>

            <div class="mt-3 space-y-2">
                <Link
                    :href="route('master.product.products.edit', product.id)"
                    class="flex w-full items-center justify-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-800"
                >
                    Edit product
                </Link>
            </div>
        </div>
    </div>
</template>
