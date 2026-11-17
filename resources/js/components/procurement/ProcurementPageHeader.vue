<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { Plus, ShoppingCart } from "lucide-vue-next";

withDefaults(
    defineProps<{
        title: string;
        section?: string;
        description?: string;
        actionUrl?: string;
        actionLabel?: string;
    }>(),
    {
        section: "Procurement",
        description: "",
    },
);
</script>

<template>
    <header
        v-if="section === 'Master Data'"
        class="app-page-header flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="space-y-1">
            <h2 class="app-page-title text-3xl font-bold tracking-tight text-gray-800 dark:text-white">
                {{ title }}
            </h2>
            <div class="m-0 h-1.5 w-25 rounded-full bg-brand-700"></div>
        </div>

        <div v-if="$slots.actions || (actionUrl && actionLabel)">
            <slot name="actions" />
            <Link
                v-if="actionUrl && actionLabel && !$slots.actions"
                :href="actionUrl"
                class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 bg-brand-700 text-white hover:bg-brand-800 focus:ring-brand-500"
            >
                <Plus class="h-4 w-4" />
                {{ actionLabel }}
            </Link>
        </div>
    </header>

    <header
        v-else
        class="app-page-header flex flex-col gap-5 border-b border-gray-200 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-gray-800"
    >
        <div class="flex min-w-0 items-start gap-3.5">
            <div
                class="mt-1 flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700 ring-1 ring-brand-100 dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-400/20"
                aria-hidden="true"
            >
                <ShoppingCart class="size-5" />
            </div>

            <div class="min-w-0">
                <div
                    class="mb-1.5 flex flex-wrap items-center gap-x-2 text-xs font-semibold uppercase tracking-[0.12em] text-brand-700 dark:text-brand-300"
                >
                    <span>Procurement</span>
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

        <div v-if="$slots.actions || (actionUrl && actionLabel)" class="flex shrink-0 flex-wrap items-center gap-2 sm:pb-0.5">
            <slot name="actions" />
            <Link
                v-if="actionUrl && actionLabel && !$slots.actions"
                :href="actionUrl"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-700/15 transition hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-950"
            >
                <span class="text-lg leading-none" aria-hidden="true">+</span>
                {{ actionLabel }}
            </Link>
        </div>
    </header>
</template>

<style>
.procurement-ui .p-button:not(.p-button-secondary):not(.p-button-info):not(.p-button-success):not(.p-button-warn):not(.p-button-danger):not(.p-button-help):not(.p-button-contrast):not(.p-button-text):not(.p-button-outlined) {
    border-color: var(--color-brand-700) !important;
    background-color: var(--color-brand-700) !important;
    color: #ffffff !important;
}

.procurement-ui .p-button:not(.p-button-secondary):not(.p-button-info):not(.p-button-success):not(.p-button-warn):not(.p-button-danger):not(.p-button-help):not(.p-button-contrast):not(.p-button-text):not(.p-button-outlined):not(:disabled):hover {
    border-color: var(--color-brand-800) !important;
    background-color: var(--color-brand-800) !important;
}

.procurement-ui .p-button:not(.p-button-secondary):not(.p-button-info):not(.p-button-success):not(.p-button-warn):not(.p-button-danger):not(.p-button-help):not(.p-button-contrast):not(.p-button-text):not(.p-button-outlined):focus-visible {
    outline: 2px solid var(--color-brand-400);
    outline-offset: 2px;
}

.procurement-ui .p-button.procurement-primary-action {
    color: var(--color-brand-700) !important;
}

.procurement-ui .p-button.procurement-primary-action:not(:disabled):hover {
    color: var(--color-brand-800) !important;
}
</style>
