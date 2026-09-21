import type { Component } from 'vue'

export interface MenuItem {
    id: string
    label: string
    routeName?: string
    icon?: Component
    children?: MenuItem[]
}