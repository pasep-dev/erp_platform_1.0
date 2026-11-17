<script setup lang="ts">
import { Warehouse } from "lucide-vue-next";

withDefaults(
    defineProps<{
        title: string;
        section?: string;
        description?: string;
    }>(),
    {
        section: "Transactions",
        description: "",
    },
);
</script>

<template>
    <header
        class="app-page-header flex flex-col gap-5 border-b border-gray-200 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-gray-800"
    >
        <div class="flex min-w-0 items-start gap-3.5">
            <div
                class="mt-1 flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700 ring-1 ring-brand-100 dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-400/20"
                aria-hidden="true"
            >
                <Warehouse class="size-5" />
            </div>

            <div class="min-w-0">
                <div
                    class="mb-1.5 flex flex-wrap items-center gap-x-2 text-xs font-semibold uppercase tracking-[0.12em] text-brand-700 dark:text-brand-300"
                >
                    <span>Inventory Management</span>
                    <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">/</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ section }}</span>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <slot name="back" />
                    <h1
                        class="app-page-title min-w-0 text-2xl font-semibold tracking-tight text-gray-900 sm:text-3xl dark:text-white"
                    >
                        {{ title }}
                    </h1>
                    <slot name="badge" />
                </div>

                <p
                    v-if="description"
                    class="mt-1.5 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400"
                >
                    {{ description }}
                </p>
            </div>
        </div>

        <div
            v-if="$slots.actions"
            class="flex shrink-0 flex-wrap items-center gap-2 sm:pb-0.5"
        >
            <slot name="actions" />
        </div>
    </header>
</template>
