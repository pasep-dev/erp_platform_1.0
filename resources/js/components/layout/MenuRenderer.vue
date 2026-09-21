<script setup lang="ts">
import MenuItem from "./MenuItem.vue";

import type { MenuItem as MenuItemType } from "@/types/menu";

defineProps<{
    items: MenuItemType[];
}>();
</script>

<template>
    <nav class="space-y-1">
        <MenuItem v-for="item in items" :key="item.id" :item="item" />
    </nav>
</template>
