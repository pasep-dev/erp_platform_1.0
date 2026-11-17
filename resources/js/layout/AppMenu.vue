<script setup lang="ts">
import { provide, ref } from "vue";
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { menuGroups } from "@/config/menu";
import AppMenuItem from "./AppMenuItem.vue";
import {
    menuSiblingContextKey,
    type MenuGroup,
    type MenuItem,
} from "@/types/menu";

interface PagePropsWithPermissions {
    auth?: {
        is_administrator?: boolean;
        permissions?: string[];
        menu_visibility?: string[] | null;
    };
}

const activeRootItemId = ref<string | null>(null);
const page = usePage();
const permissions = computed(
    () => (page.props as PagePropsWithPermissions).auth?.permissions ?? [],
);
const isAdministrator = computed(
    () => (page.props as PagePropsWithPermissions).auth?.is_administrator ?? false,
);
const menuVisibility = computed(
    () => (page.props as PagePropsWithPermissions).auth?.menu_visibility ?? null,
);

function filterMenuItem(item: MenuItem): MenuItem | null {
    if (item.administratorOnly && !isAdministrator.value) {
        return null;
    }

    if (
        item.permission
        && !isAdministrator.value
        && !permissions.value.includes(item.permission)
    ) {
        return null;
    }

    const children = item.children
        ?.map(filterMenuItem)
        .filter((child): child is MenuItem => child !== null);

    if (item.children && children?.length === 0) {
        return null;
    }

    if (
        !item.children
        && menuVisibility.value !== null
        && !menuVisibility.value.includes(item.id)
    ) {
        return null;
    }

    return {
        ...item,
        ...(children ? { children } : {}),
    };
}

const visibleMenuGroups = computed<MenuGroup[]>(() =>
    menuGroups
        .map((group) => ({
            ...group,
            items: group.items
                .map(filterMenuItem)
                .filter((item): item is MenuItem => item !== null),
        }))
        .filter((group) => group.items.length > 0),
);

provide(menuSiblingContextKey, {
    activeItemId: activeRootItemId,
    setActiveItem: (itemId: string | null) => {
        activeRootItemId.value = itemId;
    },
});
</script>

<template>
    <ul class="layout-menu">
        <template v-for="group in visibleMenuGroups" :key="group.label">
            <li class="layout-menu-group-label">
                {{ group.label }}
            </li>

            <AppMenuItem
                v-for="item in group.items"
                :key="item.id"
                :item="item"
                :root="true"
            />
        </template>
    </ul>
</template>

<style scoped>
.layout-menu-group-label {
    margin: 0.75rem 0 0.375rem;
    padding: 0.75rem 0.75rem 0;
    border-top: 1px solid var(--app-border);
    color: var(--app-text-muted);
    font-size: var(--app-font-size-xs);
    font-weight: 700;
    letter-spacing: 0.01em;
    line-height: 1.25rem;
}

.layout-menu-group-label:first-child {
    margin-top: 0.25rem;
    padding-top: 0;
    border-top: 0;
}

</style>
