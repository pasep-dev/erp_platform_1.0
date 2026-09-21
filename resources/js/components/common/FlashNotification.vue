<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { CheckCircle, XCircle, X } from "lucide-vue-next";

interface FlashProps {
    success?: string | null;
    error?: string | null;
}

const page = usePage<{
    flash: FlashProps;
}>();

const visible = ref(false);
const type = ref<"success" | "error">("success");
const message = ref("");

let hideTimer: ReturnType<typeof setTimeout> | undefined;

const flash = computed(() => page.props.flash);

function showNotification(
    notificationType: "success" | "error",
    notificationMessage: string,
): void {
    if (hideTimer) {
        clearTimeout(hideTimer);
    }

    type.value = notificationType;
    message.value = notificationMessage;
    visible.value = true;

    hideTimer = setTimeout(() => {
        visible.value = false;
    }, 5000);
}

function closeNotification(): void {
    if (hideTimer) {
        clearTimeout(hideTimer);
    }

    visible.value = false;
}

watch(
    () => flash.value.success,
    (value) => {
        if (value) {
            showNotification("success", value);
        }
    },
    { immediate: true },
);

watch(
    () => flash.value.error,
    (value) => {
        if (value) {
            showNotification("error", value);
        }
    },
    { immediate: true },
);
</script>

<template>
    <Transition
        enter-active-class="transform transition duration-300 ease-out"
        enter-from-class="translate-y-2 opacity-0 sm:translate-x-4 sm:translate-y-0"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible"
            class="fixed right-4 top-4 z-[9999] w-[calc(100%-2rem)] max-w-md rounded-xl border bg-white p-4 shadow-lg dark:bg-gray-900"
            :class="
                type === 'success'
                    ? 'border-green-200 dark:border-green-500/30'
                    : 'border-red-200 dark:border-red-500/30'
            "
            role="alert"
        >
            <div class="flex items-start gap-3">
                <CheckCircle
                    v-if="type === 'success'"
                    class="mt-0.5 h-5 w-5 shrink-0 text-green-600 dark:text-green-400"
                />

                <XCircle
                    v-else
                    class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-400"
                />

                <p
                    class="flex-1 text-sm"
                    :class="
                        type === 'success'
                            ? 'text-green-800 dark:text-green-300'
                            : 'text-red-800 dark:text-red-300'
                    "
                >
                    {{ message }}
                </p>

                <button
                    type="button"
                    class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/[0.05] dark:hover:text-gray-200"
                    aria-label="Tutup notifikasi"
                    @click="closeNotification"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>
    </Transition>
</template>
