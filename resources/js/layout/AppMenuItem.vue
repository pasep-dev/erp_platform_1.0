<script setup lang="ts">
import {
    computed,
    inject,
    nextTick,
    onBeforeUnmount,
    onMounted,
    provide,
    ref,
    watch,
} from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import {
    menuSiblingContextKey,
    type MenuItem,
    type MenuSiblingContext,
} from "@/types/menu";

const props = withDefaults(
    defineProps<{
        item: MenuItem;
        root?: boolean;
    }>(),
    {
        root: false,
    },
);

const page = usePage();

const parentMenu = inject<MenuSiblingContext>(menuSiblingContextKey);
const localOpen = ref(false);
const activeChildItemId = ref<string | null>(null);
const submenuRef = ref<HTMLUListElement | null>(null);
const activeIndicatorTop = ref<number | null>(null);

provide(menuSiblingContextKey, {
    activeItemId: activeChildItemId,
    setActiveItem: (itemId: string | null) => {
        activeChildItemId.value = itemId;
    },
});

const isOpen = computed(() => {
    return parentMenu
        ? parentMenu.activeItemId.value === props.item.id
        : localOpen.value;
});

const hasChildren = computed(() => {
    return Boolean(props.item.children?.length);
});

const rootHref = computed(() => {
    if (!props.item.routeName || hasChildren.value) {
        return undefined;
    }

    const href = route(props.item.routeName);

    return props.item.anchor ? `${href}#${props.item.anchor}` : href;
});

const currentPath = computed(() => {
    return String(page.url).split("?")[0];
});

const getPath = (routeName?: string) => {
    if (!routeName) {
        return null;
    }

    return new URL(route(routeName), window.location.origin).pathname;
};

const matchesMenuRoute = (item: MenuItem): boolean => {
    const path = getPath(item.routeName);

    if (!path) {
        return false;
    }

    if (item.exactRouteMatch || path === "/") {
        return currentPath.value === path;
    }

    return (
        currentPath.value === path || currentPath.value.startsWith(`${path}/`)
    );
};

const isCurrent = computed(() => {
    return matchesMenuRoute(props.item);
});

const hasActiveChild = (items?: MenuItem[]): boolean => {
    if (!items?.length) {
        return false;
    }

    return items.some((child) => {
        if (matchesMenuRoute(child)) {
            return true;
        }

        return hasActiveChild(child.children);
    });
};

const isActive = computed(() => {
    return isCurrent.value || hasActiveChild(props.item.children);
});

watch(
    isActive,
    (active) => {
        if (active) {
            if (parentMenu) {
                parentMenu.setActiveItem(props.item.id);
            } else if (hasChildren.value) {
                localOpen.value = true;
            }
        }
    },
    { immediate: true },
);

const updateActiveIndicator = (): void => {
    const submenu = submenuRef.value;
    const activeItemId = activeChildItemId.value;

    if (!submenu || !activeItemId) {
        activeIndicatorTop.value = null;

        return;
    }

    const activeItem = Array.from(submenu.children).find(
        (element): element is HTMLElement =>
            element instanceof HTMLElement &&
            element.dataset.menuItemId === activeItemId,
    );
    const activeLink = activeItem?.querySelector<HTMLElement>(
        ":scope > .layout-menuitem-link.active-route",
    );

    if (!activeLink) {
        activeIndicatorTop.value = null;

        return;
    }

    const submenuTop = submenu.getBoundingClientRect().top;
    const activeLinkBounds = activeLink.getBoundingClientRect();

    activeIndicatorTop.value =
        activeLinkBounds.top -
        submenuTop +
        activeLinkBounds.height / 2 -
        10;
};

watch(
    [activeChildItemId, currentPath],
    () => {
        nextTick(updateActiveIndicator);
    },
    { flush: "post", immediate: true },
);

const handleWindowResize = (): void => {
    updateActiveIndicator();
};

onMounted(() => {
    window.addEventListener("resize", handleWindowResize);
    nextTick(updateActiveIndicator);
});

onBeforeUnmount(() => {
    window.removeEventListener("resize", handleWindowResize);
});

const toggleMenu = (event: MouseEvent) => {
    if (!hasChildren.value) {
        return;
    }

    event.preventDefault();

    const shouldOpen = !isOpen.value;

    if (parentMenu) {
        parentMenu.setActiveItem(shouldOpen ? props.item.id : null);
    } else {
        localOpen.value = shouldOpen;
    }
};
</script>

<template>
    <!-- ROOT MENU -->
    <li v-if="root" class="layout-root-menuitem">
        <component
            :is="rootHref ? Link : 'div'"
            :href="rootHref"
            class="layout-menuitem-root-text root-menu-link"
            :class="{ 'active-root': isActive }"
            :title="item.label"
            @click="toggleMenu"
        >
            <component
                v-if="item.icon"
                :is="item.icon"
                class="root-menu-icon"
            />

            <span class="root-menu-label">
                {{ item.label }}
            </span>

            <i
                v-if="hasChildren"
                class="pi root-menu-arrow"
                :class="isOpen ? 'pi-angle-down' : 'pi-angle-right'"
            />
        </component>

        <Transition name="layout-submenu">
            <ul v-if="hasChildren" v-show="isOpen" class="layout-submenu">
                <AppMenuItem
                    v-for="child in item.children"
                    :key="child.id"
                    :item="child"
                    :root="false"
                />
            </ul>
        </Transition>
    </li>

    <!-- SUB MENU -->
    <li
        v-else
        :data-menu-item-id="item.id"
        :class="{
            'active-menuitem': isActive,
        }"
    >
        <!-- Parent level 2 -->
        <a
            v-if="hasChildren"
            href="#"
            class="layout-menuitem-link submenu-parent-link mb-1"
            @click="toggleMenu"
        >
            <span class="layout-menuitem-text">
                {{ item.label }}
            </span>

            <i
                class="pi layout-submenu-toggler"
                :class="isOpen ? 'pi-angle-down' : 'pi-angle-right'"
            />
        </a>

        <!-- Leaf level 3 -->
        <Link
            v-else-if="item.routeName"
            :href="`${route(item.routeName)}${item.anchor ? `#${item.anchor}` : ''}`"
            class="layout-menuitem-link leaf-menu-link"
            :class="{
                'active-route': isCurrent,
            }"
        >
            <span class="layout-menuitem-text">
                {{ item.label }}
            </span>
        </Link>

        <Transition v-if="hasChildren" name="layout-submenu">
            <ul
                ref="submenuRef"
                v-show="isOpen"
                class="layout-submenu"
                :class="{ 'has-active-indicator': activeIndicatorTop !== null }"
                :style="{
                    '--active-indicator-top': `${activeIndicatorTop ?? 0}px`,
                }"
            >
                <AppMenuItem
                    v-for="child in item.children"
                    :key="child.id"
                    :item="child"
                    :root="false"
                />
            </ul>
        </Transition>
    </li>
</template>

<style scoped>
/* =========================================
   ROOT / PARENT MENU
   ========================================= */

.root-menu-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;

    min-height: 2.75rem;
    padding: 0.625rem 0.75rem;

    border-radius: 10px;

    color: #344054;
    font-size: 13px !important;
    line-height: 20px !important;
    font-weight: 650;

    text-transform: none !important;

    cursor: pointer;

    transition:
        background-color 0.2s ease,
        color 0.2s ease,
        box-shadow 0.2s ease;
}

:global(.app-dark) .root-menu-link {
    color: #d0d5dd;
}

.root-menu-icon {
    width: 17px;
    height: 17px;
    flex-shrink: 0;
    color: #667085;
    transition: color 0.2s ease;
}

.root-menu-label {
    flex: 1;
    font-size: 13px !important;
    line-height: 20px !important;
}

.root-menu-arrow {
    margin-left: auto;
    font-size: 0.85rem;
}

/* =========================================
   SUB MENU / PARENT LEVEL 2
   ========================================= */

.layout-menuitem-link {
    min-height: 2.4rem;
    padding: 0.55rem 0.75rem !important;

    color: #475467 !important;
    font-size: 13px !important;
    line-height: 20px !important;
    font-weight: 450;

    border-radius: 9px;

    transition:
        background-color 0.2s ease,
        color 0.2s ease;
}

.layout-menuitem-text {
    font-size: 13px !important;
    line-height: 20px !important;
}

:global(.app-dark) .layout-menuitem-link {
    color: #d0d5dd !important;
}

/* =========================================
   LEAF LEVEL 3
   ========================================= */

.layout-submenu .layout-menuitem-link {
    font-size: 13px !important;
    line-height: 20px !important;
}

/* =========================================
   ICON SUB MENU
   ========================================= */

.layout-menuitem-icon {
    width: 17px;
    height: 17px;
    flex-shrink: 0;
}

/* =========================================
   HIERARCHY LINE
   ========================================= */

.layout-submenu {
    position: relative;
    border-left: 1px solid #e4e7ec !important;

    margin-left: 0.875rem !important;
    padding-left: 0.875rem !important;
}

.layout-submenu .layout-submenu {
    position: relative;
    border-left: 1px solid #e4e7ec !important;

    margin-left: 0.875rem !important;
    padding-left: 0.875rem !important;
}

:global(.app-dark) .layout-submenu,
:global(.app-dark) .layout-submenu .layout-submenu {
    border-left-color: #344054 !important;
}

.layout-submenu.has-active-indicator::after {
    position: absolute;
    top: var(--active-indicator-top);
    left: -1px;
    width: 3px;
    height: 1.25rem;
    border-radius: 9999px;
    background: var(--p-primary-color);
    content: "";
    pointer-events: none;
    transition: top 220ms cubic-bezier(0.2, 0.8, 0.2, 1);
    z-index: 1;
}

:global(.app-dark) .layout-submenu.has-active-indicator::after {
    background: var(--p-primary-color);
}

/* =========================================
   HOVER
   ========================================= */

.root-menu-link:hover,
.leaf-menu-link:hover {
    background: var(--app-primary-soft) !important;
    color: var(--p-primary-color) !important;
}

.root-menu-link:hover .root-menu-icon {
    color: var(--p-primary-color);
}

:global(.app-dark) .root-menu-link:hover,
:global(.app-dark) .leaf-menu-link:hover {
    background: var(--app-primary-soft) !important;
    color: var(--p-primary-color) !important;
}

:global(.app-dark) .root-menu-link:hover .root-menu-icon {
    color: var(--p-primary-color);
}

/* =========================================
   ACTIVE
   ========================================= */

.root-menu-link.active-root {
    color: var(--p-primary-color) !important;
    background: var(--app-primary-soft) !important;
    box-shadow: none;
    font-weight: 700;
}

.root-menu-link.active-root .root-menu-icon {
    color: var(--p-primary-color);
}

.leaf-menu-link.active-route {
    background: var(--app-primary-soft) !important;
    color: var(--p-primary-color) !important;
    font-weight: 600;
}

:global(.app-dark) .root-menu-link.active-root {
    color: var(--p-primary-color) !important;
    background: var(--app-primary-soft) !important;
    box-shadow: none;
}

:global(.app-dark) .root-menu-link.active-root .root-menu-icon {
    color: var(--p-primary-color);
}

:global(.app-dark) .leaf-menu-link.active-route {
    color: var(--p-primary-color) !important;
    background: var(--app-primary-soft) !important;
}

.root-menu-link:focus-visible,
.leaf-menu-link:focus-visible {
    outline: 2px solid var(--p-primary-color);
    outline-offset: 2px;
}

/* =========================================
   SUBMENU TRANSITION
   ========================================= */

.layout-submenu-enter-active,
.layout-submenu-leave-active {
    transition:
        max-height 0.2s ease,
        opacity 0.2s ease;
    overflow: hidden;
}

.layout-submenu-enter-from,
.layout-submenu-leave-to {
    max-height: 0;
    opacity: 0;
}

.layout-submenu-enter-to,
.layout-submenu-leave-from {
    max-height: 1000px;
    opacity: 1;
}
</style>
