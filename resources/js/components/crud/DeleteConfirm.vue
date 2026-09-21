<script setup lang="ts">
import { AlertTriangle, X } from "lucide-vue-next";

defineProps<{
    show: boolean;
    title?: string;
    message?: string;
    processing?: boolean;
}>();

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 z-[9998] flex items-center justify-center bg-black/50 p-4"
                @click.self="emit('close')"
            >
                <div
                    class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="delete-confirm-title"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400"
                        >
                            <AlertTriangle class="h-5 w-5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <h2
                                id="delete-confirm-title"
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                {{ title || "Konfirmasi Hapus" }}
                            </h2>

                            <p
                                class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400"
                            >
                                {{
                                    message ||
                                    "Apakah Anda yakin ingin menghapus data ini?"
                                }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/[0.05] dark:hover:text-gray-200"
                            aria-label="Tutup dialog"
                            :disabled="processing"
                            @click="emit('close')"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]"
                            :disabled="processing"
                            @click="emit('close')"
                        >
                            Batal
                        </button>

                        <button
                            type="button"
                            class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="processing"
                            @click="emit('confirm')"
                        >
                            {{ processing ? "Menghapus..." : "Ya, Hapus" }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
