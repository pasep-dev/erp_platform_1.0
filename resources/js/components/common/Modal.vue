<!-- resources/js/components/common/Modal.vue -->
<script setup lang="ts">
import { watch, onUnmounted } from "vue";

interface Props {
    show: boolean;
    title?: string;
    maxWidth?: "sm" | "md" | "lg" | "xl" | "2xl" | "3xl" | "4xl" | "5xl";
}

const props = withDefaults(defineProps<Props>(), {
    show: false,
    title: "Detail Data",
    maxWidth: "4xl",
});

const emit = defineEmits(["close"]);

watch(
    () => props.show,
    (val) => {
        if (val) {
            document.body.style.overflow = "hidden";
        } else {
            document.body.style.overflow = "";
        }
    },
);

onUnmounted(() => {
    document.body.style.overflow = "";
});

const maxWidthClass = {
    sm: "sm:max-w-sm",
    md: "sm:max-w-md",
    lg: "sm:max-w-lg",
    xl: "sm:max-w-xl",
    "2xl": "sm:max-w-2xl",
    "3xl": "sm:max-w-3xl",
    "4xl": "sm:max-w-4xl",
    "5xl": "sm:max-w-5xl",
}[props.maxWidth];
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <!-- Diubah ke z-[9999] agar dipastikan di atas Sidebar & Header -->
            <div
                v-if="show"
                class="fixed inset-0 z-[9999] overflow-y-auto bg-gray-900/60 backdrop-blur-sm transition-opacity"
                @click="emit('close')"
            >
                <div
                    class="flex min-h-full items-center justify-center p-4 text-center sm:p-6"
                >
                    <Transition
                        enter-active-class="ease-out duration-200"
                        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-active-class="ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <div
                            v-if="show"
                            class="relative w-full transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-2xl transition-all dark:bg-gray-900 dark:border dark:border-gray-800"
                            :class="maxWidthClass"
                            @click.stop
                        >
                            <!-- Modal Header -->
                            <div
                                class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800"
                            >
                                <h3
                                    class="text-lg font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ title }}
                                </h3>
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                    @click="emit('close')"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <!-- Modal Body -->
                            <div class="p-6">
                                <slot />
                            </div>

                            <!-- Modal Footer (Opsional) -->
                            <div
                                v-if="$slots.footer"
                                class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-800 dark:bg-gray-900/50"
                            >
                                <slot name="footer" />
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
