<script setup lang="ts">
import { Eye, Pencil, Trash2 } from "lucide-vue-next";

withDefaults(
    defineProps<{
        showView?: boolean;
        showEdit?: boolean;
        showDelete?: boolean;
        processing?: boolean;
        variant?: "default" | "procurement";
    }>(),
    {
        showView: false,
        showEdit: true,
        showDelete: true,
        processing: false,
        variant: "default",
    },
);

const emit = defineEmits<{
    view: [];
    edit: [];
    delete: [];
}>();
</script>

<template>
    <div class="flex items-center justify-end gap-1.5">
        <!-- View Button (Biru Tua / Ungu Tua) -->
        <button
            v-if="showView"
            type="button"
            class="inline-flex items-center justify-center rounded-lg p-2 transition-all disabled:cursor-not-allowed disabled:opacity-50"
            :class="variant === 'procurement'
                ? 'bg-gray-100 text-gray-600 hover:bg-brand-50 hover:text-brand-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-300'
                : 'bg-indigo-50 text-indigo-900 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300 dark:hover:bg-indigo-900/60'"
            :disabled="processing"
            v-tooltip.top="'Lihat'"
            @click="emit('view')"
        >
            <Eye class="h-4 w-4" />
        </button>

        <!-- Edit Button (Oranye Tua - Icon Only) -->
        <button
            v-if="showEdit"
            type="button"
            class="inline-flex items-center justify-center rounded-lg p-2 transition-all disabled:cursor-not-allowed disabled:opacity-50"
            :class="variant === 'procurement'
                ? 'bg-gray-100 text-gray-600 hover:bg-brand-50 hover:text-brand-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-300'
                : 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-400 dark:hover:bg-amber-900/60'"
            :disabled="processing"
            v-tooltip.top="'Edit'"
            @click="emit('edit')"
        >
            <Pencil class="h-4 w-4" />
        </button>

        <!-- Delete Button (Merah) -->
        <button
            v-if="showDelete"
            type="button"
            class="inline-flex items-center justify-center rounded-lg bg-red-50 p-2 text-red-600 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-400 dark:hover:bg-red-900/60 disabled:cursor-not-allowed disabled:opacity-50 transition-all"
            :disabled="processing"
            v-tooltip.top="'Hapus'"
            @click="emit('delete')"
        >
            <Trash2 class="h-4 w-4" />
        </button>
    </div>
</template>
