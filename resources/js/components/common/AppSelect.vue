<script setup lang="ts">
import Select from "primevue/select";

defineProps<{
    options: any[];
    optionLabel?: string;
    optionValue?: string;
    placeholder?: string;
}>();

const model = defineModel<any>();
</script>

<template>
    <Select
        v-model="model"
        :options="options"
        :optionLabel="optionLabel || 'label'"
        :optionValue="optionValue || 'value'"
        :placeholder="placeholder"
        :pt="{
            root: ({ state }) => [
                '!h-10 !px-3 !rounded-xl !bg-white dark:!bg-gray-900 flex items-center transition-all duration-200 cursor-pointer',
                state.overlayVisible
                    ? '!border-purple-600 !ring-2 !ring-purple-100 dark:!ring-purple-900/40'
                    : '!border-gray-200 dark:!border-gray-800 hover:!border-purple-300',
            ],
            label: '!p-0 !text-xs !font-medium !text-gray-700 dark:!text-gray-200 flex items-center',
            dropdown: '!w-4 !text-gray-400',
            overlay:
                '!rounded-xl !border-gray-100 dark:!border-gray-800 !shadow-xl !text-xs',
            option: ({ context }) => [
                '!py-2 !px-3 !text-xs !rounded-lg my-0.5 transition-colors duration-150 cursor-pointer',
                context.selected
                    ? '!bg-purple-100 !text-purple-900 font-semibold dark:!bg-purple-950/60 dark:!text-purple-200'
                    : context.focused
                      ? '!bg-purple-50 !text-purple-700 dark:!bg-purple-900/30 dark:!text-purple-300'
                      : '!text-gray-700 dark:!text-gray-300 hover:!bg-purple-50 dark:hover:!bg-purple-900/30',
            ],
        }"
    />
</template>
