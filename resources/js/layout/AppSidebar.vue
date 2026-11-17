<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import { useLayout } from "@/layout/composables/layout";
import AppMenu from "./AppMenu.vue";
import miniSvg from "@/../images/logo/citra-logo.svg";

const page = usePage<{
    app: {
        name: string;
        logo_url: string | null;
    };
}>();

const { layoutState, isDesktop, hasOpenOverlay } = useLayout();
const sidebarRef = ref<HTMLElement | null>(null);

let outsideClickListener: ((event: MouseEvent) => void) | null = null;

watch(
    () => window.location.pathname,
    (newPath) => {
        if (isDesktop()) {
            layoutState.activePath = null;
        } else {
            layoutState.activePath = newPath;
        }

        layoutState.overlayMenuActive = false;
        layoutState.mobileMenuActive = false;
        layoutState.menuHoverActive = false;
    },
    { immediate: true },
);

watch(hasOpenOverlay, (newVal) => {
    if (isDesktop()) {
        if (newVal) {
            bindOutsideClickListener();
        } else {
            unbindOutsideClickListener();
        }
    }
});

const bindOutsideClickListener = () => {
    if (!outsideClickListener) {
        outsideClickListener = (event: MouseEvent) => {
            if (isOutsideClicked(event)) {
                layoutState.overlayMenuActive = false;
            }
        };

        document.addEventListener("click", outsideClickListener);
    }
};

const unbindOutsideClickListener = () => {
    if (outsideClickListener) {
        document.removeEventListener("click", outsideClickListener);
        outsideClickListener = null;
    }
};

const isOutsideClicked = (event: MouseEvent) => {
    const sidebar = sidebarRef.value;
    const topbarButton = document.querySelector(".layout-menu-button");

    if (!sidebar) {
        return false;
    }

    return !(
        sidebar.isSameNode(event.target as Node) ||
        sidebar.contains(event.target as Node) ||
        topbarButton?.isSameNode(event.target as Node) ||
        topbarButton?.contains(event.target as Node)
    );
};

onBeforeUnmount(() => {
    unbindOutsideClickListener();
});
</script>

<template>
    <div class="sidebar-shell">
        <aside
            id="app-sidebar"
            ref="sidebarRef"
            class="layout-sidebar sidebar-with-footer"
        >
            <div class="sidebar-brand-header">
                <Link
                    href="/"
                    class="sidebar-brand"
                    :aria-label="page.props.app.name"
                >
                    <img
                        :src="page.props.app.logo_url ?? miniSvg"
                        alt=""
                        class="sidebar-brand-logo"
                    />
                    <span class="sidebar-brand-copy">
                        <span class="sidebar-brand-title">
                            {{ page.props.app.name }}
                        </span>
                        <span class="sidebar-brand-subtitle">
                            Asset Management & Tracking System
                        </span>
                    </span>
                </Link>
            </div>

            <div class="sidebar-menu-scroll">
                <AppMenu />
            </div>

            <footer class="sidebar-footer">
                <span class="sidebar-footer-label">Asset Management System</span>
                <span class="sidebar-project-version" title="Version 1.0">
                    v1.0
                </span>
            </footer>
        </aside>

    </div>
</template>

<style scoped>
:global(.layout-sidebar.sidebar-with-footer) {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.sidebar-brand-header {
    position: relative;
    display: flex;
    box-sizing: border-box;
    min-height: 5rem;
    flex: 0 0 5rem;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin: 0 -1rem 0.5rem;
    padding: 1rem 1rem 0;
    background: var(--app-surface);
}

.sidebar-brand-header::after {
    position: absolute;
    right: 1rem;
    bottom: 0;
    left: 1rem;
    height: 1px;
    background: var(--app-border);
    content: "";
}

.sidebar-brand {
    display: flex;
    width: 100%;
    min-width: 0;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    color: var(--app-text-primary);
}

.sidebar-brand-logo {
    width: 2.75rem;
    height: 2.75rem;
    flex: 0 0 auto;
    object-fit: contain;
}

.sidebar-brand-copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.125rem;
    line-height: 1.1;
}

.sidebar-brand-title {
    overflow: hidden;
    font-size: 0.875rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sidebar-brand-accent {
    color: var(--p-primary-color);
}

.sidebar-brand-subtitle {
    color: var(--app-text-secondary);
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.sidebar-brand:focus-visible {
    outline: 2px solid var(--p-primary-color);
    outline-offset: 2px;
}

.sidebar-menu-scroll {
    min-height: 0;
    flex: 1;
    overflow-x: hidden;
    overflow-y: auto;
    padding-bottom: 0.75rem;
    scrollbar-color: #d0d5dd transparent;
    scrollbar-width: thin;
}

.sidebar-footer {
    display: flex;
    box-sizing: border-box;
    min-width: 0;
    flex: 0 0 auto;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    overflow: hidden;
    padding: 0.875rem 1.25rem;
    border-top: 1px solid var(--app-border);
    background: var(--app-surface);
}

.sidebar-footer-label {
    overflow: hidden;
    color: var(--app-text-secondary);
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-overflow: ellipsis;
    text-transform: uppercase;
    white-space: nowrap;
}

.sidebar-user-card {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem;
    border: 1px solid #eaecf0;
    border-radius: 0.75rem;
    background: #fcfcfd;
}

.sidebar-user-avatar {
    display: flex;
    width: 2.25rem;
    height: 2.25rem;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: #f3e8f4;
    color: #7f2982;
    font-size: 0.75rem;
    font-weight: 700;
}

.sidebar-user-details {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.125rem;
}

.sidebar-user-name,
.sidebar-user-email {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sidebar-user-name {
    color: #344054;
    font-size: 0.8125rem;
    font-weight: 600;
}

.sidebar-user-email {
    color: #667085;
    font-size: 0.6875rem;
}

.sidebar-project-version {
    border-radius: 9999px;
    background: var(--app-primary-soft);
    padding: 0.1875rem 0.5rem;
    color: var(--p-primary-color);
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

:global(.app-dark) .sidebar-footer {
    border-top-color: var(--app-border);
    background: var(--app-surface);
}

:global(.app-dark) .sidebar-user-card {
    border-color: #344054;
    background: #1d2939;
}

:global(.app-dark) .sidebar-user-avatar {
    background: #302044;
    color: #e9d7fe;
}

:global(.app-dark) .sidebar-user-name {
    color: #f2f4f7;
}

:global(.app-dark) .sidebar-user-email {
    color: #d0d5dd;
}

:global(.app-dark) .sidebar-brand-header {
    background: var(--app-surface);
}

@media (max-width: 991px) {
    .sidebar-brand-header {
        min-height: 4.75rem;
        flex-basis: 4.75rem;
        padding: 0.75rem 1rem 0;
    }
}

</style>
