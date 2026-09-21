<script setup lang="ts">
import type { Component } from "vue";
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import { Plus } from "lucide-vue-next";

type ButtonColor = "brand" | "green" | "red" | "gray";

interface Props {
    title: string;
    description?: string;
    icon?: Component;
    actionUrl?: string;
    actionLabel?: string;
    actionColor?: ButtonColor; // Props warna opsional (default: brand)
}

const props = withDefaults(defineProps<Props>(), {
    actionColor: "brand",
});

// Mapping class Tailwind berdasarkan varian warna agar aman dari purging
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
    <div
        class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
    >
        <div>
            <div class="flex items-center gap-2">
                <component
                    :is="icon"
                    v-if="icon"
                    class="h-6 w-6 text-brand-700"
                />
                <h1
                    class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white"
                >
                    {{ title }}
                </h1>
            </div>

            <!-- Garis Aksen Ungu Logo -->
            <div class="mt-2 h-1 w-12 rounded-full bg-brand-700"></div>

            <p
                v-if="description"
                class="mt-2 text-sm text-gray-500 dark:text-gray-400"
            >
                {{ description }}
            </p>
        </div>

        <!-- Tombol Aksi Opsional dengan Warna Dinamis -->
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
