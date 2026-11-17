<script setup lang="ts">
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";

withDefaults(defineProps<{
    modelValue: string;
    total: number;
    totalLabel?: string;
    placeholder?: string;
}>(), {
    totalLabel: "Records",
    placeholder: "Cari data...",
});

const emit = defineEmits<{
    "update:modelValue": [value: string];
    search: [];
}>();
</script>

<template>
    <div class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-800">
        <div class="flex flex-1 flex-wrap items-center gap-3">
            <SearchFilterBar
                :model-value="modelValue"
                hover-border
                :placeholder="placeholder"
                class="w-full sm:w-64"
                @update:model-value="emit('update:modelValue', $event)"
                @search="emit('search')"
            />
            <slot />
        </div>

        <div class="whitespace-nowrap rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-500 dark:bg-gray-900 dark:text-gray-400">
            Total {{ total.toLocaleString("id-ID") }} {{ totalLabel }}
        </div>
    </div>
</template>
