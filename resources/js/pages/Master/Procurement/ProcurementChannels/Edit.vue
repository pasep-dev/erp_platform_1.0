<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import AppLayout from "@/layouts/AppLayout.vue";
import CrudForm from "@/components/crud/CrudForm.vue";

defineOptions({
    layout: AppLayout,
});

interface ProcurementChannel {
    id: string;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
}

const props = defineProps<{
    procurementChannel: ProcurementChannel;
}>();

const form = useForm({
    code: props.procurementChannel.code,
    name: props.procurementChannel.name,
    description: props.procurementChannel.description ?? "",
    is_active: props.procurementChannel.is_active,
});

function submit(): void {
    form.put(
        route(
            "master.procurement.procurement-channels.update",
            props.procurementChannel.id,
        ),
    );
}

const cancel = () => {
    window.location.href = route(
        "master.procurement.procurement-channels.index",
    );
};
</script>

<template>
    <Head title="Edit Procurement Channel" />

    <CrudForm
        title="Edit Procurement Channel"
        :form="form"
        submit-label="Update"
        :cancel-url="route('master.procurement.procurement-channels.index')"
        submit-processing-label="Updating..."
        :processing="form.processing"
        @submit="submit"
        @cancel="cancel"
    >
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <label
                    for="code"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Code <span class="text-error-500">*</span>
                </label>

                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    maxlength="50"
                    placeholder="Contoh: MARKETPLACE"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-none focus:border-brand-300 focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                />

                <p v-if="form.errors.code" class="mt-1 text-sm text-error-500">
                    {{ form.errors.code }}
                </p>
            </div>

            <div>
                <label
                    for="name"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Procurement Channel Name
                    <span class="text-error-500">*</span>
                </label>

                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    maxlength="150"
                    placeholder="Contoh: Marketplace"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-none focus:border-brand-300 focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                />

                <p v-if="form.errors.name" class="mt-1 text-sm text-error-500">
                    {{ form.errors.name }}
                </p>
            </div>
        </div>

        <div>
            <label
                for="description"
                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                Description
            </label>

            <textarea
                id="description"
                v-model="form.description"
                rows="4"
                maxlength="1000"
                placeholder="Description procurement channel..."
                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none focus:border-brand-300 focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
            ></textarea>

            <p
                v-if="form.errors.description"
                class="mt-1 text-sm text-error-500"
            >
                {{ form.errors.description }}
            </p>
        </div>

        <div>
            <label
                class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                Status
            </label>

            <label class="inline-flex cursor-pointer items-center gap-3">
                <input
                    v-model="form.is_active"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700"
                />

                <span class="text-sm text-gray-700 dark:text-gray-300">
                    Active
                </span>
            </label>

            <p v-if="form.errors.is_active" class="mt-1 text-sm text-error-500">
                {{ form.errors.is_active }}
            </p>
        </div>
    </CrudForm>
</template>
