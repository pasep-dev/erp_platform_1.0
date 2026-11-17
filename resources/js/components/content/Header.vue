<script setup lang="ts">
import type { Component } from "vue";
import { computed } from "vue";
import { Link, Head } from "@inertiajs/vue3"; // 1. Import Head dari Inertia
import { Plus, ChevronRight } from "lucide-vue-next";

type ButtonColor = "brand" | "green" | "red" | "gray";

export interface BreadcrumbItem {
    label: string;
    url?: string;
}

interface Props {
    title: string;
    description?: string;
    icon?: Component;
    actionUrl?: string;
    actionLabel?: string;
    actionColor?: ButtonColor;
    breadcrumbs?: BreadcrumbItem[];
}

const props = withDefaults(defineProps<Props>(), {
    actionColor: "brand",
    breadcrumbs: () => [],
});

const buttonColorClasses = computed(() => {
    switch (props.actionColor) {
        case "green":
            return "bg-emerald-600 hover:bg-emerald-700 text-white focus:ring-emerald-500";
        case "red":
            return "bg-rose-600 hover:bg-rose-700 text-white focus:ring-rose-500";
        case "gray":
            return "bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-200 focus:ring-gray-400";
        case "brand":
        default:
            return "bg-brand-700 hover:bg-brand-800 text-white focus:ring-brand-500";
    }
});
</script>

<template>
    <!-- 2. Tambahkan Head di sini. Judul tab browser otomatis sesuai prop 'title' -->
    <Head :title="title" />

    <div
        class="app-page-header flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="space-y-1">
            <div class="flex items-center">
                <h2
                    class="app-page-title text-3xl font-bold tracking-tight text-gray-800 dark:text-white"
                >
                    {{ title }}
                </h2>
            </div>

            <!-- Garis Aksen Ungu Logo -->
            <div class="h-1.5 w-25 m-0 rounded-full bg-brand-700"></div>
        </div>

        <!-- Tombol Aksi Opsional -->
        <Link
            v-if="actionUrl && actionLabel"
            :href="actionUrl"
            class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2"
            :class="buttonColorClasses"
        >
            <Plus class="h-4 w-4" />
            {{ actionLabel }}
        </Link>

        <!-- Slot Tambahan -->
        <div v-else-if="$slots.actions">
            <slot name="actions"></slot>
        </div>
    </div>
</template>
