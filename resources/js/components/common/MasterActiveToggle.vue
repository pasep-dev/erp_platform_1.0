<script setup lang="ts">
import ToggleSwitch from "primevue/toggleswitch";

defineProps<{
    modelValue: boolean;
    error?: string;
}>();

const emit = defineEmits<{
    "update:modelValue": [value: boolean];
}>();
</script>

<template>
    <div class="space-y-1">
        <div class="flex items-center gap-3">
            <ToggleSwitch
                input-id="is_active"
                :model-value="modelValue"
                @update:model-value="emit('update:modelValue', $event)"
            />

            <label
                for="is_active"
                class="text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                Active
            </label>
        </div>

        <p v-if="error" class="text-sm text-red-500">
            {{ error }}
        </p>
    </div>
</template>
