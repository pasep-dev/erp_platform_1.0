<script setup lang="ts">
import { Head, usePage } from "@inertiajs/vue3";
import AppLayout from "../../layouts/AppLayout.vue";

const page = usePage();
</script>

<template>
    <Head :title="page.props.app.name" />

    <AppLayout>
        <div class="space-y-6">
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    {{ page.props.app.name }}
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Asset Management & Tracking System
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
            >
                <p class="text-gray-700 dark:text-gray-300">
                    Inertia + Vue + TypeScript berhasil berjalan.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
