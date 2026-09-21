<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import AppLayout from "@/layouts/AppLayout.vue";
import CrudForm from "@/components/crud/CrudForm.vue";

defineOptions({
    layout: AppLayout,
});

const form = useForm({
    code: "",
    name: "",
    description: "",
    is_active: true,
});

function submit(): void {
    form.post(route("master.procurement.vendor-types.store"));
}

function cancel(): void {
    window.location.href = route("master.procurement.vendor-types.index");
}
</script>

<template>
    <Head title="Create Vendor Type" />

    <CrudForm
        title="Create Vendor Type"
        :form="form"
        submit-label="Save"
        :cancel-url="route('master.procurement.vendor-types.index')"
        submit-processing-label="Saving..."
        :processing="form.processing"
        @submit="submit"
        @cancel="cancel"
    >
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
                <label
                    for="code"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Code
                </label>

                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    maxlength="50"
                    autocomplete="off"
                    placeholder="Contoh: DISTRIBUTOR"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                />

                <p v-if="form.errors.code" class="mt-1.5 text-sm text-red-500">
                    {{ form.errors.code }}
                </p>
            </div>

            <div>
                <label
                    for="name"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Vendor Type Name
                </label>

                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    maxlength="150"
                    placeholder="Contoh: Distributor"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                />

                <p v-if="form.errors.name" class="mt-1.5 text-sm text-red-500">
                    {{ form.errors.name }}
                </p>
            </div>

            <div class="md:col-span-2">
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
                    placeholder="Masukkan deskripsi vendor type..."
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                ></textarea>

                <p
                    v-if="form.errors.description"
                    class="mt-1.5 text-sm text-red-500"
                >
                    {{ form.errors.description }}
                </p>
            </div>

            <div class="md:col-span-2">
                <label class="inline-flex cursor-pointer items-center gap-3">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />

                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Active
                    </span>
                </label>

                <p
                    v-if="form.errors.is_active"
                    class="mt-1.5 text-sm text-red-500"
                >
                    {{ form.errors.is_active }}
                </p>
            </div>
        </div>
    </CrudForm>
</template>
