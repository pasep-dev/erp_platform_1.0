<script setup lang="ts">
import { ref } from "vue";
import { Search, X } from "lucide-vue-next";

interface Props {
    modelValue: string;
    placeholder?: string;
    total?: number | string;
    debounceMs?: number;
    hoverBorder?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: "Cari data...",
    total: undefined,
    debounceMs: 300,
    hoverBorder: false,
});

const emit = defineEmits<{
    "update:modelValue": [value: string];
    search: [];
    clear: [];
}>();

let timeoutId: ReturnType<typeof setTimeout> | null = null;

const handleInput = (event: Event) => {
    const value = (event.target as HTMLInputElement).value;
    emit("update:modelValue", value);

    if (timeoutId) clearTimeout(timeoutId);

    timeoutId = setTimeout(() => {
        emit("search");
    }, props.debounceMs);
};

const handleClear = () => {
    if (timeoutId) clearTimeout(timeoutId);
    emit("update:modelValue", "");
    emit("clear");
    emit("search");
};
</script>

<template>
    <!-- Ubah p-3 menjadi px-0 py-3 agar sejajar dengan kolom tabel -->
    <div
        class="flex w-full flex-col gap-3 border-b border-gray-200 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
    >
        <div class="relative flex w-full flex-1 items-center sm:max-w-xl">
            <!-- Ikon Search -->
            <Search
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
            />

            <!-- Input Search (ditambahkan kelas penyembunyi tombol X bawaan browser) -->
            <input
                :value="modelValue"
                type="search"
                :placeholder="placeholder"
                :class="[
                    'h-10 w-full rounded-lg border border-gray-300 bg-transparent pl-9 pr-9 text-sm text-gray-900 outline-none transition focus:border-brand-700 focus:ring-2 focus:ring-brand-700/20 dark:border-gray-700 dark:text-white [&::-webkit-search-cancel-button]:appearance-none [&::-webkit-search-decoration]:appearance-none',
                    hoverBorder && 'hover:border-gray-400 dark:hover:border-gray-500',
                ]"
                @input="handleInput"
            />

            <!-- Tombol Clear Custom (Hanya 1 ikon X yang akan muncul) -->
            <button
                v-if="modelValue"
                type="button"
                title="Reset"
                class="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                @click="handleClear"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <!-- Total Data -->
        <div
            v-if="total !== undefined"
            class="text-sm text-gray-500 dark:text-gray-400"
        >
            Total: {{ total }}
        </div>
    </div>
</template>
