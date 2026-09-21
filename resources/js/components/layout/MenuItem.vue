<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import { ChevronDown } from "lucide-vue-next";
import { route } from "ziggy-js";

import type { MenuItem } from "@/types/menu";
import { useSidebar } from "../../composables/useSidebar";

const props = defineProps<{
    item: MenuItem;
    level?: number;
}>();

const page = usePage();

const { isExpanded, isMobileOpen } = useSidebar();

const isOpen = ref(false);

const isMini = computed(() => {
    return !isExpanded.value && !isMobileOpen.value;
});

const hasChildren = computed(() => {
    return Boolean(props.item.children?.length);
});

const currentUrl = computed(() => {
    return String(page.url);
});

const ownUrl = computed(() => {
    if (!props.item.routeName) {
        return null;
    }

    return route(props.item.routeName);
});

const isCurrent = computed(() => {
    if (!ownUrl.value) {
        return false;
    }

    return (
        currentUrl.value ===
        new URL(ownUrl.value, window.location.origin).pathname
    );
});

const hasActiveChild = computed(() => {
    return Boolean(
        props.item.children?.some((child) => {
            if (child.routeName) {
                const childUrl = route(child.routeName);
                const childPath = new URL(childUrl, window.location.origin)
                    .pathname;

                if (currentUrl.value === childPath) {
                    return true;
                }
            }

            return child.children?.some((nestedChild) => {
                if (!nestedChild.routeName) {
                    return false;
                }

                const nestedUrl = route(nestedChild.routeName);
                const nestedPath = new URL(nestedUrl, window.location.origin)
                    .pathname;

                return currentUrl.value === nestedPath;
            });
        }),
    );
});

watch(
    hasActiveChild,
    (active) => {
        if (active && !isMini.value) {
            isOpen.value = true;
        }
    },
    { immediate: true },
);

watch(isMini, (mini) => {
    if (mini) {
        isOpen.value = false;
    }
});
</script>

<template>
    <div class="w-full">
        <!-- Menu dengan submenu -->
        <button
            v-if="hasChildren"
            type="button"
            :title="isMini ? item.label : undefined"
            class="flex w-full items-center rounded-lg py-2 text-left text-sm transition hover:bg-[#FAF0FA] hover:text-brand-700 dark:hover:bg-brand-950/50 dark:hover:text-brand-300"
            :class="[
                isMini ? 'justify-center px-0' : 'justify-start gap-3 px-3',
                {
                    'bg-[#FAF0FA] font-bold text-brand-700 dark:bg-brand-950/50 dark:text-brand-300':
                        hasActiveChild || isOpen,
                    'text-[#5E5E70] dark:text-gray-300':
                        !hasActiveChild && !isOpen,
                },
            ]"
            @click="isOpen = !isOpen"
        >
            <!-- Ikon HANYA tampil jika level 1 (Menu Utama) -->
            <component
                :is="item.icon"
                v-if="item.icon && (!level || level === 1)"
                class="size-5 shrink-0"
            />

            <span v-if="!isMini" class="min-w-0 flex-1 truncate">
                {{ item.label }}
            </span>

            <ChevronDown
                v-if="!isMini"
                class="size-4 shrink-0 transition-transform"
                :class="{ 'rotate-180': isOpen }"
            />
        </button>

        <!-- Menu tanpa submenu -->
        <Link
            v-else-if="item.routeName"
            :href="route(item.routeName)"
            :title="isMini ? item.label : undefined"
            class="flex w-full items-center rounded-lg py-2 text-sm transition hover:bg-[#FAF0FA] hover:text-brand-700 dark:hover:bg-brand-950/50 dark:hover:text-brand-300"
            :class="[
                isMini ? 'justify-center px-0' : 'justify-start gap-3 px-3',
                isCurrent
                    ? 'bg-brand-700 font-semibold text-white dark:bg-brand-700 dark:text-white'
                    : 'text-[#5E5E70] dark:text-gray-300',
            ]"
        >
            <!-- Ikon HANYA tampil jika level 1 (Menu Utama) -->
            <component
                :is="item.icon"
                v-if="item.icon && (!level || level === 1)"
                class="size-5 shrink-0"
            />

            <span v-if="!isMini" class="min-w-0 flex-1 truncate">
                {{ item.label }}
            </span>
        </Link>

        <!-- Submenu hanya tampil ketika sidebar terbuka -->
        <div
            v-if="hasChildren && isOpen && !isMini"
            class="mt-1 space-y-1 border-l border-gray-200 pl-4 dark:border-gray-700"
        >
            <MenuItem
                v-for="child in item.children"
                :key="child.id"
                :item="child"
                :level="(level ?? 1) + 1"
            />
        </div>
    </div>
</template>
