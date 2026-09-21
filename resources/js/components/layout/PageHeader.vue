<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="mb-6">
        <h1
            class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white md:text-3xl"
        >
            {{ title }}
        </h1>

        <p
            v-if="description"
            class="mt-1 text-sm text-gray-500 dark:text-gray-400 md:text-base"
        >
            {{ description }}
        </p>
    </div>
</template>