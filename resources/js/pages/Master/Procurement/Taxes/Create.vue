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
    rate: 0,
    description: "",
    is_active: true,
});

function submit(): void {
    form.post(route("master.procurement.taxes.store"));
}

const cancel = () => {
    window.location.href = route("master.procurement.taxes.index");
};
</script>

<template>
    <div class="space-y-6">
        <Head title="Create Tax" />

        <CrudForm
            title="Create Tax"
            :form="form"
            submit-label="Save"
            :cancel-url="route('master.procurement.taxes.index')"
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
                        Tax Code
                    </label>

                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        placeholder="Contoh: VAT"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="form.errors.code"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.code }}
                    </p>
                </div>

                <div>
                    <label
                        for="name"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Tax Name
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Contoh: Value Added Tax"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <label
                        for="rate"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Tax Rate (%)
                    </label>

                    <input
                        id="rate"
                        v-model.number="form.rate"
                        type="number"
                        min="0"
                        max="100"
                        step="0.0001"
                        placeholder="Contoh: 11"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    />

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Masukkan nilai pajak antara 0 sampai 100 persen.
                    </p>

                    <p
                        v-if="form.errors.rate"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.rate }}
                    </p>
                </div>

                <div>
                    <label
                        for="description"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Description
                    </label>

                    <input
                        id="description"
                        v-model="form.description"
                        type="text"
                        placeholder="Catatan singkat tax"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="form.errors.description"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label
                        class="inline-flex cursor-pointer items-center gap-3"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />

                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Active
                        </span>
                    </label>

                    <p
                        v-if="form.errors.is_active"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.is_active }}
                    </p>
                </div>
            </div>
        </CrudForm>
    </div>
</template>
