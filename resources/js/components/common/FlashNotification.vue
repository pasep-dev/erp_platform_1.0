<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import {
    CheckCircle,
    CircleAlert,
    Info,
    TriangleAlert,
    X,
} from "lucide-vue-next";

interface FlashProps {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
    info?: string | null;
}

const page = usePage<{
    flash: FlashProps;
}>();

const visible = ref(false);
const type = ref<"success" | "error" | "warning" | "info">("success");
const message = ref("");

let hideTimer: ReturnType<typeof setTimeout> | undefined;

const flash = computed(() => page.props.flash);

function showNotification(
    notificationType: "success" | "error" | "warning" | "info",
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

const notificationConfig = computed(() => {
    const configurations = {
        success: {
            title: "Success",
            icon: CheckCircle,
            classes:
                "border-green-700 bg-green-700 text-white dark:border-green-800 dark:bg-green-800",
            closeClasses: "text-green-100 hover:bg-green-600 hover:text-white",
        },
        error: {
            title: "Error",
            icon: CircleAlert,
            classes:
                "border-red-700 bg-red-700 text-white dark:border-red-800 dark:bg-red-800",
            closeClasses: "text-red-100 hover:bg-red-600 hover:text-white",
        },
        warning: {
            title: "Warning",
            icon: TriangleAlert,
            classes:
                "border-amber-700 bg-amber-700 text-white dark:border-amber-800 dark:bg-amber-800",
            closeClasses:
                "text-amber-100 hover:bg-amber-600 hover:text-white",
        },
        info: {
            title: "Information",
            icon: Info,
            classes:
                "border-blue-800 bg-blue-800 text-white dark:border-blue-900 dark:bg-blue-900",
            closeClasses: "text-blue-100 hover:bg-blue-700 hover:text-white",
        },
    };

    return configurations[type.value];
});

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

watch(
    () => flash.value.warning,
    (value) => {
        if (value) {
            showNotification("warning", value);
        }
    },
    { immediate: true },
);

watch(
    () => flash.value.info,
    (value) => {
        if (value) {
            showNotification("info", value);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (hideTimer) {
        clearTimeout(hideTimer);
    }
});
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
            class="fixed right-4 top-4 z-[9999] w-[calc(100%-2rem)] max-w-md rounded-xl border p-4 shadow-lg"
            :class="notificationConfig.classes"
            role="alert"
            aria-live="assertive"
        >
            <div class="flex items-start gap-3">
                <component
                    :is="notificationConfig.icon"
                    class="mt-0.5 h-6 w-6 shrink-0"
                    aria-hidden="true"
                />

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold">
                        {{ notificationConfig.title }}
                    </p>
                    <p class="mt-0.5 text-sm leading-5">
                        {{ message }}
                    </p>
                </div>

                <button
                    type="button"
                    class="rounded-md p-1 transition"
                    :class="notificationConfig.closeClasses"
                    aria-label="Close notification"
                    @click="closeNotification"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
        </div>
    </Transition>
</template>
