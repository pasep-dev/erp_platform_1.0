<template>
    <SidebarProvider>
        <AppLayoutContent>
            <slot />
        </AppLayoutContent>
    </SidebarProvider>
</template>

<script setup lang="ts">
import AppLayoutContent from './AppLayoutContent.vue';
import SidebarProvider from './SidebarProvider.vue';
</script>