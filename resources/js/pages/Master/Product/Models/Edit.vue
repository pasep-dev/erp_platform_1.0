<script setup lang="ts">
import { computed, watch } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useMasterFieldFormatting } from "@/composables/useMasterFieldFormatting";

import AppLayout from "@/layout/AppLayout.vue";
import MasterActiveToggle from "@/components/common/MasterActiveToggle.vue";
import CrudForm from "@/components/crud/CrudForm.vue";

defineOptions({
    layout: AppLayout,
});

interface Brand {
    id: string;
    code: string;
    name: string;
    categories: Category[];
}

interface Category {
    id: string;
    code: string;
    name: string;
    department_id: string | null;
}

interface Type {
    id: string;
    category_id: string;
    code: string;
    name: string;
    category?: Category;
}

interface AssetModel {
    id: string;
    brand_id: string;
    type_id: string;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
}

const props = defineProps<{
    model: AssetModel;
    brands: Brand[];
    types: Type[];
}>();

const form = useForm({
    brand_id: props.model.brand_id,
    type_id: props.model.type_id,
    code: props.model.code,
    name: props.model.name,
    description: props.model.description ?? "",
    is_active: props.model.is_active,
});

useMasterFieldFormatting(form);

const filteredBrands = computed(() => {
    const selectedType = props.types.find((type) => type.id === form.type_id);
    const categoryId = selectedType?.category_id;
    return selectedType
        ? props.brands.filter((brand) =>
              brand.categories.some((category) => category.id === categoryId),
          )
        : [];
});

watch(
    () => form.type_id,
    () => {
        if (!filteredBrands.value.some((brand) => brand.id === form.brand_id)) {
            form.brand_id = "";
        }
    },
);

const submit = () => {
    form.put(route("master.product.models.update", props.model.id));
};
const cancel = () => {
    window.location.href = route("master.product.models.index");
};
</script>

<template>
    <CrudForm
        title="Edit Model"
        submit-label="Submit Changes"
        submit-processing-label="Updating..."
        :processing="form.processing"
        @submit="submit"
        @cancel="cancel"
    >
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
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
                        v-for="brand in filteredBrands"
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
                        {{ type.name }} ({{ type.category?.name ?? "-" }})
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
                :disabled="true" />

                <p v-if="form.errors.code" class="mt-1 text-sm text-error-500">
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

                <p v-if="form.errors.name" class="mt-1 text-sm text-error-500">
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
                <MasterActiveToggle
                    v-model="form.is_active"
                    :error="form.errors.is_active"
                />
            </div>
        </div>
    </CrudForm>
</template>
