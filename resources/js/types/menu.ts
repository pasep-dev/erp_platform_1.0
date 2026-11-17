import type { Component, InjectionKey, Ref } from 'vue'

export interface MenuItem {
    id: string
    label: string
    routeName?: string
    exactRouteMatch?: boolean
    anchor?: string
    icon?: Component
    permission?: string
    administratorOnly?: boolean
    children?: MenuItem[]
}

export interface MenuGroup {
    label: string
    items: MenuItem[]
}

export interface MenuSiblingContext {
    activeItemId: Ref<string | null>
    setActiveItem: (itemId: string | null) => void
}

export const menuSiblingContextKey: InjectionKey<MenuSiblingContext> =
    Symbol('menu-sibling-context')
