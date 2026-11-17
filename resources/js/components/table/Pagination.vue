<script setup lang="ts">
import { Link } from "@inertiajs/vue3";

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    links: PaginationLink[];
}>();

function label(link: PaginationLink): string {
    if (link.label.includes("&laquo;")) {
        return "Previous";
    }

    if (link.label.includes("&raquo;")) {
        return "Next";
    }

    return link.label
        .replace(/&laquo;/g, "")
        .replace(/&raquo;/g, "")
        .trim();
}
</script>

<template>
    <div class="flex items-center gap-1">
        <template
            v-for="(link, index) in links"
            :key="`${link.label}-${index}`"
        >
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-xs font-medium transition"
                :class="
                    link.active
                        ? 'border-primary bg-primary text-white'
                        : 'border-surface-200 bg-surface-0 text-surface-600 hover:bg-surface-100 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300 dark:hover:bg-surface-800'
                "
            >
                {{ label(link) }}
            </Link>

            <span
                v-else
                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-surface-200 bg-surface-50 px-2 text-xs text-surface-400 dark:border-surface-700 dark:bg-surface-800"
            >
                {{ label(link) }}
            </span>
        </template>
    </div>
</template>
