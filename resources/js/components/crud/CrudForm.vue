<script setup lang="ts">
import Button from "primevue/button";
import { Head } from "@inertiajs/vue3";
import ProcurementPageHeader from "@/components/procurement/ProcurementPageHeader.vue";

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        submitLabel?: string;
        processing?: boolean;
        submitProcessingLabel?: string;
        headerVariant?: "default" | "procurement";
        headerSection?: string;
        stackedHeader?: boolean;
    }>(),
    {
        title: "",
        description: "",
        submitLabel: "Simpan",
        processing: false,
        submitProcessingLabel: "Menyimpan...",
        headerVariant: "default",
        headerSection: "Master Data",
        stackedHeader: false,
    },
);

const emit = defineEmits<{
    submit: [];
    cancel: [];
}>();
</script>

<template>
    <Head :title="title" />
    <div class="space-y-6" :class="{ 'procurement-ui': headerVariant === 'procurement' }">
        <!-- Header -->
        <ProcurementPageHeader
            v-if="headerVariant === 'procurement'"
            :title="title"
            :section="headerSection"
            :description="description"
        >
            <template #actions>
                <Button
                    label="Back"
                    icon="pi pi-arrow-left"
                    class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                    outlined
                    :disabled="processing"
                    @click="emit('cancel')"
                />
            </template>
        </ProcurementPageHeader>

        <div
            v-else
            class="app-page-header flex justify-between gap-4"
            :class="stackedHeader ? 'items-start' : 'items-center'"
        >
            <div class="min-w-0">
                <div :class="stackedHeader ? 'flex flex-col items-start gap-1.5' : 'flex items-center'">
                    <p
                        class="app-page-title text-3xl font-bold tracking-tight text-gray-800 dark:text-white"
                    >
                        {{ title }}
                    </p>
                    <p
                        v-if="description"
                        :class="stackedHeader ? 'max-w-2xl text-sm leading-5 text-gray-500 dark:text-gray-400' : 'mt-1.5 max-w-2xl text-sm text-gray-500 dark:text-gray-400'"
                    >
                        {{ description }}
                    </p>
                </div>

                <!-- Garis Aksen Ungu Logo -->
                <div :class="stackedHeader ? 'mt-3 h-1 w-14 rounded-full bg-brand-700' : 'h-1 w-20 rounded-full bg-brand-700'"></div>
            </div>

            <Button
                label="Back"
                icon="pi pi-arrow-left"
                class="inline-flex font-bold items-center justify-center rounded-lg !bg-gray-50 p-2 !text-gray-700 !border !border-gray-700 hover:!bg-gray-100 dark:!bg-gray-950/40 dark:!text-gray-400 dark:hover:!bg-gray-900/60 disabled:cursor-not-allowed disabled:opacity-50 transition-all"
                outlined
                :disabled="processing"
                @click="emit('cancel')"
            />
        </div>

        <!-- Form Card -->
        <div class="app-card rounded-xl border border-surface bg-surface-0 p-6">
            <form class="space-y-6" @submit.prevent="emit('submit')">
                <slot></slot>

                <!-- Form Actions -->
                <div
                    class="flex justify-end gap-2 border-t border-surface pt-5"
                >
                    <Button
                        type="button"
                        label="Cancel"
                        class="inline-flex font-bold items-center justify-center rounded-lg !bg-gray-50 p-2 !text-gray-700 !border !border-gray-700 hover:!bg-gray-100 dark:!bg-gray-950/40 dark:!text-gray-400 dark:hover:!bg-gray-900/60 disabled:cursor-not-allowed disabled:opacity-50 transition-all"
                        outlined
                        icon="pi pi-times"
                        :disabled="processing"
                        @click="emit('cancel')"
                    />

                    <Button
                        label="Submit"
                        type="submit"
                        :label="
                            processing ? submitProcessingLabel : submitLabel
                        "
                        icon="pi pi-save"
                        class="inline-flex font-bold items-center justify-center !bg-brand-700 p-2 !text-brand-50 !border !border-brand-50 hover:!bg-brand-900 dark:!bg-brand-50 dark:!text-brand-100 dark:hover:!bg-brand-700/60 disabled:cursor-not-allowed disabled:opacity-50 transition-all"
                        :loading="processing"
                        :disabled="processing"
                    />
                </div>
            </form>
        </div>
    </div>
</template>
