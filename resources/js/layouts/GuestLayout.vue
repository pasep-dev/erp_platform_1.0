<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";

const page = usePage();
</script>

<template>
    <div class="min-h-screen bg-gray-50 px-4 py-10 dark:bg-gray-950">
        <div
            class="mx-auto flex min-h-[calc(100vh-5rem)] max-w-md items-center"
        >
            <div class="w-full">
                <div class="mb-8 text-center">
                    <div
                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-900 text-white dark:bg-white dark:text-gray-900"
                    >
                        <span class="text-lg font-bold"> CAA </span>
                    </div>

                    <h1
                        class="mt-4 text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ page.props.app.name }}
                    </h1>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Asset Management & Tracking System
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:p-8"
                >
                    <slot />
                </div>

                <p
                    class="mt-6 text-center text-xs text-gray-400 dark:text-gray-500"
                >
                    &copy; 2026 {{ page.props.app.name }}. Development
                </p>
            </div>
        </div>
    </div>
</template>
