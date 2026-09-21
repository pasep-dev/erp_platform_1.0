<template>
    <SidebarProvider>
        <AppLayoutContent>
            <slot />
        </AppLayoutContent>
    </SidebarProvider>
</template>

<script setup lang="ts">
import AppLayoutContent from '../components/layout/AppLayoutContent.vue';
import SidebarProvider from '../components/layout/SidebarProvider.vue';
</script>