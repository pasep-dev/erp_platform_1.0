<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import AppLayout from "@/layouts/AppLayout.vue";
import CrudForm from "@/components/crud/CrudForm.vue";

interface Brand {
    id: string;
    code: string;
    name: string;
}

interface Category {
    id: string;
    code: string;
    name: string;
}

interface Type {
    id: string;
    category_id: string;
    code: string;
    name: string;
    category?: Category;
}

const props = defineProps<{
    brands: Brand[];
    types: Type[];
}>();

const form = useForm({
    type_id: "",
    brand_id: "",
    code: "",
    name: "",
    description: "",
    is_active: true,
});

const submit = () => {
    form.post(route("master.asset-management.models.store"));
};
const cancel = () => {
    window.location.href = route("master.asset-management.models.index");
};
</script>

<template>
    <Head title="Master - Model Asset" />

    <AppLayout>
        <div class="space-y-6">
            <CrudForm
                title="Tambah Model Asset"
                description="Tambahkan model dan jenis asset."
                submit-label="Simpan"
                submit-processing-label="Menyimpan..."
                :processing="form.processing"
                :cancel-url="route('master.asset-management.models.index')"
                @submit="submit"
                @cancel="cancel"
            >
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label
                            for="type_id"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Type
                        </label>

                        <select
                            id="type_id"
                            v-model="form.type_id"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">Select Type</option>

                            <option
                                v-for="type in props.types"
                                :key="type.id"
                                :value="type.id"
                            >
                                {{ type.name }} ({{
                                    type.category?.name ?? "-"
                                }})
                            </option>
                        </select>

                        <p
                            v-if="form.errors.type_id"
                            class="mt-1 text-sm text-error-500"
                        >
                            {{ form.errors.type_id }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="brand_id"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Brand
                        </label>

                        <select
                            id="brand_id"
                            v-model="form.brand_id"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">Select Brand</option>

                            <option
                                v-for="brand in props.brands"
                                :key="brand.id"
                                :value="brand.id"
                            >
                                {{ brand.name }} ({{ brand.code }})
                            </option>
                        </select>

                        <p
                            v-if="form.errors.brand_id"
                            class="mt-1 text-sm text-error-500"
                        >
                            {{ form.errors.brand_id }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="code"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Model Code
                        </label>

                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            placeholder="e.g. ASUS-VIVOBOOK-14"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:text-white"
                        />

                        <p
                            v-if="form.errors.code"
                            class="mt-1 text-sm text-error-500"
                        >
                            {{ form.errors.code }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="name"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Model Name
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="e.g. Vivobook 14"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:text-white"
                        />

                        <p
                            v-if="form.errors.name"
                            class="mt-1 text-sm text-error-500"
                        >
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
                            placeholder="Description..."
                            class="w-full resize-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:text-white"
                        ></textarea>

                        <p
                            v-if="form.errors.description"
                            class="mt-1 text-sm text-error-500"
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
                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                            />

                            <span
                                class="text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Active
                            </span>
                        </label>

                        <p
                            v-if="form.errors.is_active"
                            class="mt-1 text-sm text-error-500"
                        >
                            {{ form.errors.is_active }}
                        </p>
                    </div>
                </div>
            </CrudForm>
        </div>
    </AppLayout>
</template>
