<script setup lang="ts">
import { ArrowLeft, Save } from "lucide-vue-next";

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        submitLabel?: string;
        processing?: boolean;
        submitProcessingLabel?: string;
    }>(),
    {
        title: "",
        description: "",
        submitLabel: "Simpan",
        processing: false,
        submitProcessingLabel: "Menyimpan...",
    },
);

const emit = defineEmits<{
    submit: [];
    cancel: [];
}>();
</script>

<template>
    <div class="space-y-6">
        <div
            v-if="title || description"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <div>
                <h1
                    v-if="title"
                    class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ title }}
                </h1>

                <p
                    v-if="description"
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ description }}
                </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                :disabled="processing"
                @click="emit('cancel')"
            >
                <ArrowLeft class="h-4 w-4" />
                Kembali
            </button>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form class="space-y-5" @submit.prevent="emit('submit')">
                <slot></slot>

                <div
                    class="flex justify-end gap-3 border-t border-gray-200 pt-5 dark:border-gray-800"
                >
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                        :disabled="processing"
                        @click="emit('cancel')"
                    >
                        Batal
                    </button>

                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="processing"
                    >
                        <Save class="h-4 w-4" />

                        {{ processing ? submitProcessingLabel : submitLabel }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
