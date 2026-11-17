<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useLayout } from "@/layout/composables/layout";
import AppConfigurator from "./AppConfigurator.vue";
import miniSvg from "@/../images/logo/citra-logo.svg";

const page = usePage<{
    app: {
        name: string;
        logo_url: string | null;
    };
    auth: {
        user: {
            name: string;
        } | null;
    };
}>();

const { layoutState, toggleDarkMode, isDarkTheme, toggleMenu } = useLayout();
const isScrolled = ref(false);

const updateScrollState = (): void => {
    isScrolled.value = window.scrollY > 24;
};

onMounted(() => {
    updateScrollState();
    window.addEventListener("scroll", updateScrollState, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener("scroll", updateScrollState);
});
</script>

<template>
    <div
        class="layout-topbar"
        :class="{ 'layout-topbar-scrolled': isScrolled }"
    >
        <div class="topbar-brand-controls">
            <Link
                href="/"
                class="layout-mobile-brand"
                :aria-label="page.props.app.name"
                :title="page.props.app.name"
            >
                <img :src="page.props.app.logo_url ?? miniSvg" alt="" />
            </Link>

            <button
                type="button"
                class="layout-menu-button layout-topbar-action"
                :aria-label="
                    layoutState.mobileMenuActive || layoutState.overlayMenuActive
                        ? 'Close sidebar'
                        : layoutState.staticMenuInactive
                          ? 'Expand sidebar'
                          : 'Collapse sidebar'
                "
                aria-controls="app-sidebar"
                @click="toggleMenu"
            >
                <svg
                    class="sidebar-toggle-icon"
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                    focusable="false"
                >
                    <path
                        d="M3 4H21M12 9H21M12 15H21M3 20H21"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                    />
                    <path
                        d="M8.5 8.25L3 12L8.5 15.75V8.25Z"
                        fill="currentColor"
                    />
                </svg>
            </button>
        </div>

        <div class="layout-topbar-actions">
            <!-- Desktop configuration -->
            <div class="layout-config-menu">
                <!-- Dark mode -->
                <button
                    type="button"
                    class="layout-topbar-action"
                    @click="toggleDarkMode"
                >
                    <i
                        :class="[
                            'pi',
                            {
                                'pi-moon': isDarkTheme,
                                'pi-sun': !isDarkTheme,
                            },
                        ]"
                    />
                </button>

                <!-- Theme configurator -->
                <div class="relative">
                    <button
                        v-styleclass="{
                            selector: '@next',
                            enterFromClass: 'hidden',
                            enterActiveClass: 'p-anchored-overlay-enter-active',
                            leaveToClass: 'hidden',
                            leaveActiveClass: 'p-anchored-overlay-leave-active',
                            hideOnOutsideClick: true,
                        }"
                        type="button"
                        class="layout-topbar-action layout-topbar-action-highlight"
                    >
                        <i class="pi pi-palette"></i>
                    </button>

                    <AppConfigurator />
                </div>
            </div>

            <!-- Mobile menu button -->
            <button
                class="layout-topbar-menu-button layout-topbar-action"
                v-styleclass="{
                    selector: '@next',
                    enterFromClass: 'hidden',
                    enterActiveClass: 'p-anchored-overlay-enter-active',
                    leaveToClass: 'hidden',
                    leaveActiveClass: 'p-anchored-overlay-leave-active',
                    hideOnOutsideClick: true,
                }"
                type="button"
            >
                <i class="pi pi-ellipsis-v"></i>
            </button>

            <!-- Right menu -->
            <div class="layout-topbar-menu hidden lg:block">
                <div class="layout-topbar-menu-content">
                    <button type="button" class="layout-topbar-action">
                        <i class="pi pi-calendar"></i>
                        <span>Calendar</span>
                    </button>

                    <button type="button" class="layout-topbar-action">
                        <i class="pi pi-inbox"></i>
                        <span>Messages</span>
                    </button>

                    <div class="relative">
                        <button
                            v-styleclass="{
                                selector: '@next',
                                enterFromClass: 'hidden',
                                enterActiveClass: 'p-anchored-overlay-enter-active',
                                leaveToClass: 'hidden',
                                leaveActiveClass: 'p-anchored-overlay-leave-active',
                                hideOnOutsideClick: true,
                            }"
                            type="button"
                            class="layout-topbar-action"
                            aria-label="Open profile menu"
                            aria-haspopup="true"
                        >
                            <i class="pi pi-user"></i>
                            <span>{{ page.props.auth.user?.name ?? "Profile" }}</span>
                        </button>

                        <div
                            class="profile-dropdown hidden absolute right-0 top-full z-[1000] mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-900"
                        >
                            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ page.props.auth.user?.name ?? "User" }}
                                </p>
                            </div>
                            <Link
                                :href="route('profile.show')"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                            >
                                <i class="pi pi-id-card"></i>
                                <span>My Profile</span>
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                            >
                                <i class="pi pi-sign-out"></i>
                                <span>Logout</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.topbar-brand-controls {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 0.75rem;
}

.layout-topbar .topbar-brand-controls .layout-menu-button {
    margin-right: 0;
}

.layout-mobile-brand {
    display: none;
    width: 2.5rem;
    height: 2.5rem;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    border-radius: var(--app-radius-control);
}

.layout-mobile-brand img {
    width: 2.5rem;
    height: 2.5rem;
    object-fit: contain;
}

.layout-mobile-brand:focus-visible {
    outline: 2px solid var(--p-primary-color);
    outline-offset: 2px;
}

@media (max-width: 991px) {
    .layout-mobile-brand {
        display: flex;
    }
}
</style>
