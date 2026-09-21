<script setup lang="ts">
import { Pencil, Trash2 } from "lucide-vue-next";

withDefaults(
    defineProps<{
        showEdit?: boolean;
        showDelete?: boolean;
        processing?: boolean;
    }>(),
    {
        showEdit: true,
        showDelete: true,
        processing: false,
    },
);

const emit = defineEmits<{
    edit: [];
    delete: [];
}>();
</script>

<template>
    <div class="flex items-center justify-end gap-2">
        <button
            v-if="showEdit"
            type="button"
            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
            :disabled="processing"
            @click="emit('edit')"
        >
            <Pencil class="h-3.5 w-3.5" />
        </button>

        <button
            v-if="showDelete"
            type="button"
            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-600 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
            :disabled="processing"
            @click="emit('delete')"
        >
            <Trash2 class="h-3.5 w-3.5" />
        </button>
    </div>
</template>
