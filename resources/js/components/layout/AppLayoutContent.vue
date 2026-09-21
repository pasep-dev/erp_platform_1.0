<template>
    <div class="min-h-screen bg-gray-50 xl:flex dark:bg-gray-950">
        <AppSidebar />

        <Backdrop />

        <main
            :class="[
                'min-w-0 flex-1 transition-all duration-300 ease-in-out',
                isExpanded || isHovered ? 'lg:ml-[290px]' : 'lg:ml-[90px]',
            ]"
        >
            <AppHeader />

            <FlashNotification />

            <div class="mx-auto max-w-full p-4 md:p-6">
                <slot />
            </div>
        </main>
    </div>
</template>

<script setup lang="ts">
import AppHeader from "./AppHeader.vue";
import AppSidebar from "./AppSidebar.vue";
import Backdrop from "./Backdrop.vue";
import { useSidebar } from "../../composables/useSidebar";
import FlashNotification from "@/components/common/FlashNotification.vue";

const { isExpanded, isHovered } = useSidebar();
</script>
