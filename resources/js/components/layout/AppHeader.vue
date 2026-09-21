<script setup lang="ts">
import {
    Bell,
    ChevronDown,
    Menu,
    Moon,
    Search,
    Sun,
    UserCircle,
    X,
} from "lucide-vue-next";
import { onBeforeUnmount, onMounted, ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import { useSidebar } from "../../composables/useSidebar";
import { useTheme } from "../../composables/useTheme";

const { isMobileOpen, toggleSidebar, toggleMobileSidebar } = useSidebar();
const isMobileSearchOpen = ref(false);

const { isDark, toggleTheme } = useTheme();

const isUserMenuOpen = ref(false);
const isNotificationOpen = ref(false);
const searchInput = ref<HTMLInputElement | null>(null);

const toggleSidebarMenu = () => {
    if (window.innerWidth >= 1024) {
        toggleSidebar();
    } else {
        toggleMobileSidebar();
    }
};

const toggleUserMenu = () => {
    isNotificationOpen.value = false;
    isUserMenuOpen.value = !isUserMenuOpen.value;
};

const toggleNotification = () => {
    isUserMenuOpen.value = false;
    isNotificationOpen.value = !isNotificationOpen.value;
};

const handleKeyboardShortcut = (event: KeyboardEvent) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        searchInput.value?.focus();
    }

    if (event.key === "Escape") {
        isUserMenuOpen.value = false;
        isNotificationOpen.value = false;
    }
};

const handleDocumentClick = (event: MouseEvent) => {
    const target = event.target as HTMLElement;

    if (!target.closest("[data-user-menu]")) {
        isUserMenuOpen.value = false;
    }

    if (!target.closest("[data-notification-menu]")) {
        isNotificationOpen.value = false;
    }
};

const logout = () => {
    router.post("/logout");
};

onMounted(() => {
    document.addEventListener("keydown", handleKeyboardShortcut);
    document.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener("keydown", handleKeyboardShortcut);
    document.removeEventListener("click", handleDocumentClick);
});
</script>

<template>
    <header
        class="sticky top-0 z-40 w-full border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="flex h-16 items-center justify-between gap-4 px-4 md:px-6">
            <!-- Left -->
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                    aria-label="Toggle sidebar"
                    @click="toggleSidebarMenu"
                >
                    <X v-if="isMobileOpen" class="h-5 w-5" />

                    <Menu v-else class="h-5 w-5" />
                </button>
            </div>

            <!-- Right -->
            <div class="flex shrink-0 items-center gap-2">
                <!-- Theme -->
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                    :aria-label="
                        isDark ? 'Switch to light mode' : 'Switch to dark mode'
                    "
                    @click="toggleTheme"
                >
                    <Sun v-if="isDark" class="h-5 w-5" />

                    <Moon v-else class="h-5 w-5" />
                </button>

                <!-- Notifications -->
                <div class="relative" data-notification-menu>
                    <button
                        type="button"
                        class="relative flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                        aria-label="Notifications"
                        @click.stop="toggleNotification"
                    >
                        <Bell class="h-5 w-5" />

                        <span
                            class="absolute right-2 top-2 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-gray-900"
                        />
                    </button>

                    <Transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="scale-95 opacity-0"
                        enter-to-class="scale-100 opacity-100"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="scale-100 opacity-100"
                        leave-to-class="scale-95 opacity-0"
                    >
                        <div
                            v-if="isNotificationOpen"
                            class="absolute right-0 mt-3 w-80 origin-top-right rounded-xl border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-800 dark:bg-gray-900"
                        >
                            <div
                                class="flex items-center justify-between border-b border-gray-100 px-2 pb-3 dark:border-gray-800"
                            >
                                <h3
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    Notifications
                                </h3>

                                <span
                                    class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                                >
                                    1 new
                                </span>
                            </div>

                            <div class="py-2">
                                <div
                                    class="rounded-lg px-2 py-3 transition hover:bg-gray-50 dark:hover:bg-gray-800"
                                >
                                    <p
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        Welcome to ERP Platform
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Notification center is ready.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- User -->
                <div class="relative" data-user-menu>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-gray-100 dark:hover:bg-gray-800"
                        @click.stop="toggleUserMenu"
                    >
                        <UserCircle class="h-9 w-9 text-gray-400" />

                        <span class="hidden text-left sm:block">
                            <span
                                class="block text-sm font-medium text-gray-900 dark:text-white"
                            >
                                Administrator
                            </span>

                            <span
                                class="block text-xs text-gray-500 dark:text-gray-400"
                            >
                                System Administrator
                            </span>
                        </span>

                        <ChevronDown
                            class="hidden h-4 w-4 text-gray-400 sm:block"
                        />
                    </button>

                    <Transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="scale-95 opacity-0"
                        enter-to-class="scale-100 opacity-100"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="scale-100 opacity-100"
                        leave-to-class="scale-95 opacity-0"
                    >
                        <div
                            v-if="isUserMenuOpen"
                            class="absolute right-0 mt-3 w-64 origin-top-right rounded-xl border border-gray-200 bg-white p-2 shadow-xl dark:border-gray-800 dark:bg-gray-900"
                        >
                            <div
                                class="border-b border-gray-100 px-3 py-3 dark:border-gray-800"
                            >
                                <p
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    Administrator
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    System Administrator
                                </p>
                            </div>

                            <div class="py-2">
                                <Link
                                    href="/"
                                    class="flex items-center rounded-lg px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                >
                                    Profile
                                </Link>

                                <Link
                                    href="/"
                                    class="flex items-center rounded-lg px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                >
                                    Settings
                                </Link>
                            </div>

                            <div
                                class="border-t border-gray-100 pt-2 dark:border-gray-800"
                            >
                                <button
                                    type="button"
                                    class="w-full rounded-lg px-3 py-2 text-left text-sm text-red-600 transition hover:bg-red-50 dark:hover:bg-red-950/20"
                                    @click="logout"
                                >
                                    Sign out
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>

        <!-- Mobile search -->
        <div
            v-if="isMobileSearchOpen"
            class="border-t border-gray-200 px-4 py-3 lg:hidden dark:border-gray-800"
        >
            <div class="relative">
                <Search
                    class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                />

                <input
                    type="search"
                    autofocus
                    placeholder="Search..."
                    class="h-10 w-full rounded-lg border border-gray-200 bg-gray-50 pl-10 pr-4 text-sm text-gray-900 outline-none transition focus:border-gray-300 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-800/50 dark:text-white dark:placeholder:text-gray-500"
                />
            </div>
        </div>
    </header>
</template>
