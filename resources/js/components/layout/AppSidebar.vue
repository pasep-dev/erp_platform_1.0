<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { LayoutDashboard, Settings } from "lucide-vue-next";
import miniSvg from "@/../images/logo/citra-logo.svg";
import textSvg from "@/../images/logo/citra-teks.svg";
import { useSidebar } from "../../composables/useSidebar";
import { menuItems } from "@/config/menu";
import MenuRenderer from "./MenuRenderer.vue";

interface MenuItem {
    name: string;
    href?: string;
    icon: typeof LayoutDashboard;
    subItems?: {
        name: string;
        href: string;
    }[];
}

interface MenuGroup {
    title: string;
    items: MenuItem[];
}

const page = usePage();

const { isExpanded, isMobileOpen, openSubmenu } = useSidebar();

const menuGroups: MenuGroup[] = [
    {
        title: "Menu",
        items: [
            {
                name: "Dashboard",
                href: "/",
                icon: LayoutDashboard,
            },
            {
                name: "Settings",
                href: "/settings",
                icon: Settings,
            },
        ],
    },
];

const currentUrl = computed(() => page.url);

const isActive = (href: string) => {
    if (href === "/") {
        return currentUrl.value === "/";
    }

    return currentUrl.value === href || currentUrl.value.startsWith(`${href}/`);
};

const isSubmenuOpen = (groupIndex: number, itemIndex: number) => {
    const key = `${groupIndex}-${itemIndex}`;

    if (openSubmenu.value === key) {
        return true;
    }

    const item = menuGroups[groupIndex]?.items[itemIndex];

    return item?.subItems?.some((subItem) => isActive(subItem.href)) ?? false;
};

const startTransition = (element: Element) => {
    const el = element as HTMLElement;

    el.style.height = "auto";

    const height = el.scrollHeight;

    el.style.height = "0px";

    el.offsetHeight;

    el.style.height = `${height}px`;
};

const endTransition = (element: Element) => {
    const el = element as HTMLElement;

    el.style.height = "";
};
</script>

<template>
    <aside
        :class="[
            'fixed left-0 top-0 z-99999 flex h-screen flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900 dark:text-white',
            {
                'lg:w-[290px]': isExpanded || isMobileOpen,
                'lg:w-[90px]': !isExpanded && !isMobileOpen,
                'w-[290px] translate-x-0': isMobileOpen,
                '-translate-x-full': !isMobileOpen,
                'lg:translate-x-0': true,
            },
        ]"
    >
        <div
            :class="[
                'flex h-16 items-center py-3',
                !isExpanded && !isMobileOpen
                    ? 'justify-center px-0'
                    : 'justify-start px-2',
            ]"
        >
            <Link href="/" class="flex items-center gap-3 px-0">
                <img
                    :src="miniSvg"
                    alt="Citra Logo"
                    class="h-13 w-auto shrink-0 object-contain"
                />

                <div
                    v-if="isExpanded || isMobileOpen"
                    class="flex flex-col justify-center whitespace-nowrap leading-tight"
                >
                    <!-- Baris 1: CITRA GROUP -->
                    <span
                        class="text-logo-title font-bold tracking-wider text-gray-900 dark:text-white"
                    >
                        <!-- Huruf C warna ungu -->
                        <span class="text-citra-purple">C</span>ITRA GROUP
                    </span>

                    <!-- Baris 2: Subtitle / Tagline -->
                    <span
                        class="text-[8px] font-semibold tracking-widest text-gray-500 dark:text-gray-400"
                    >
                        Asset Management & Tracking System
                    </span>
                </div>
            </Link>
        </div>

        <!-- Navigation -->
        <div class="flex min-w-0 flex-col overflow-y-auto no-scrollbar">
            <nav class="mb-6 mt-4">
                <MenuRenderer :items="menuItems" />
            </nav>
        </div>
    </aside>
</template>
