<script setup lang="ts">
import CrudForm from "@/components/crud/CrudForm.vue";
import AppLayout from "@/layout/AppLayout.vue";
import MasterActiveToggle from "@/components/common/MasterActiveToggle.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useMasterFieldFormatting } from "@/composables/useMasterFieldFormatting";

defineOptions({
    layout: AppLayout,
});

interface Category {
    id: string;
    department_id: string | null;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
}

interface DepartmentOption {
    id: string;
    code: string;
    name: string;
}

const props = defineProps<{
    category: Category;
    departments: DepartmentOption[];
}>();

const form = useForm({
    department_id: props.category.department_id ?? "",
    code: props.category.code,
    name: props.category.name,
    description: props.category.description ?? "",
    is_active: Boolean(props.category.is_active),
});

useMasterFieldFormatting(form);

const submit = () => {
    form.put(route("master.product.categories.update", props.category.id));
};

const cancel = () => {
    window.location.href = route("master.product.categories.index");
};
</script>

<template>
    <CrudForm
        title="Edit Category"
        submit-label="Submit Changes"
        submit-processing-label="Updating..."
        :processing="form.processing"
        @submit="submit"
        @cancel="cancel"
    >
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
                <label
                    for="department_id"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Department
                    <span class="text-red-500">*</span>
                </label>

                <select
                    id="department_id"
                    v-model="form.department_id"
                    required
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="">Select department</option>

                    <option
                        v-for="department in props.departments"
                        :key="department.id"
                        :value="department.id"
                    >
                        {{ department.name }} ({{ department.code }})
                    </option>
                </select>

                <p
                    v-if="form.errors.department_id"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.department_id }}
                </p>

                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                    Department menentukan pengelompokan category.
                </p>
            </div>

            <div>
                <label
                    for="code"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Category Code
                    <span class="text-red-500">*</span>
                </label>

                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    maxlength="50"
                    placeholder="Contoh: IT-EQUIPMENT"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                :disabled="true" />

                <p
                    v-if="form.errors.code"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.code }}
                </p>
            </div>

            <div class="md:col-span-2">
                <label
                    for="name"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Category Name
                    <span class="text-red-500">*</span>
                </label>

                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    maxlength="150"
                    placeholder="Contoh: IT Equipment"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                />

                <p
                    v-if="form.errors.name"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
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
                    maxlength="1000"
                    placeholder="Deskripsi category..."
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                ></textarea>

                <p
                    v-if="form.errors.description"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.description }}
                </p>
            </div>

            <div class="md:col-span-2">
                <MasterActiveToggle
                    v-model="form.is_active"
                    :error="form.errors.is_active"
                />
            </div>
        </div>
    </CrudForm>
</template>
