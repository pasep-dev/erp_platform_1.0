<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import AppLayout from "@/layout/AppLayout.vue";
import Header from "@/components/content/Header.vue";

defineOptions({
    layout: AppLayout,
});

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
    image_url: string | null;
    group: string;
    category: Category | null;
    type: Type | null;
    brand: Brand | null;
    model: Model | null;
    is_active: boolean;
    created_at: string;
}

const props = defineProps<{
    product: Product;
}>();

function getGroupClass(group: string): string {
    if (group === "ASSET") {
        return "bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400";
    }

    if (group === "CONSUMABLE") {
        return "bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400";
    }

    return "bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-400";
}
</script>

<template>
    <Head :title="`Product - ${product.name}`" />

    <div class="space-y-6">
        <Header title="Product Detail">
            <template #actions>
                <div class="flex gap-2">
                    <Link
                        :href="route('master.product.products.index')"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                    >
                        Back
                    </Link>
                    <Link
                        :href="
                            route('master.product.products.edit', product.id)
                        "
                        class="inline-flex items-center justify-center rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-800"
                    >
                        Edit
                    </Link>
                </div>
            </template>
        </Header>

        <div class="grid gap-6 lg:grid-cols-[1.5fr_0.9fr]">
            <div
                class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400"
                        >
                            Product
                        </p>
                        <h2
                            class="mt-2 text-2xl font-bold text-gray-900 dark:text-white"
                        >
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

                <img
                    v-if="product.image_url"
                    :src="product.image_url"
                    :alt="`Gambar ${product.name}`"
                    class="mt-6 max-h-80 w-full rounded-xl border border-gray-200 bg-gray-50 object-contain dark:border-gray-700 dark:bg-gray-900"
                />

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div
                        class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50"
                    >
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Code
                        </p>
                        <p
                            class="mt-2 text-base font-semibold text-gray-900 dark:text-white"
                        >
                            {{ product.code }}
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50"
                    >
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Status
                        </p>
                        <p
                            class="mt-2 text-base font-semibold"
                            :class="
                                product.is_active
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-gray-600 dark:text-gray-400'
                            "
                        >
                            {{ product.is_active ? "Active" : "Inactive" }}
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50"
                    >
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Category
                        </p>
                        <p
                            class="mt-2 text-base font-semibold text-gray-900 dark:text-white"
                        >
                            {{ product.category?.name || "-" }}
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50"
                    >
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Type
                        </p>
                        <p
                            class="mt-2 text-base font-semibold text-gray-900 dark:text-white"
                        >
                            {{ product.type?.name || "-" }}
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50"
                    >
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Brand
                        </p>
                        <p
                            class="mt-2 text-base font-semibold text-gray-900 dark:text-white"
                        >
                            {{ product.brand?.name || "-" }}
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50"
                    >
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Model
                        </p>
                        <p
                            class="mt-2 text-base font-semibold text-gray-900 dark:text-white"
                        >
                            {{ product.model?.name || "-" }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                    >
                        Description
                    </p>
                    <p
                        class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300"
                    >
                        {{ product.description || "No description provided." }}
                    </p>
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Quick actions
                </h3>

                <div class="mt-4 space-y-3">
                    <Link
                        :href="
                            route('master.product.products.edit', product.id)
                        "
                        class="flex w-full items-center justify-center rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-800"
                    >
                        Edit product
                    </Link>

                    <Link
                        :href="route('master.product.products.index')"
                        class="flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                    >
                        Back to list
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
