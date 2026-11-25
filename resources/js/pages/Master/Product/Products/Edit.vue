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

interface Category {
    id: string;
    code: string;
    name: string;
    parent_id: string | null;
}

interface Type {
    id: string;
    category_id: string;
    code: string;
    name: string;
    category?: Category;
}

interface Brand {
    id: string;
    code: string;
    name: string;
    categories: Category[];
}

interface Model {
    id: string;
    brand_id: string;
    type_id: string;
    code: string;
    name: string;
    brand?: Brand;
    type?: Type;
}

interface Product {
    id: string;
    category_id: string;
    type_id: string;
    brand_id: string;
    model_id: string | null;
    group: string;
    inventory_tracking_mode: string;
    base_uom_id: string | null;
    code: string;
    name: string;
    description: string | null;
    image_url: string | null;
    is_active: boolean;
    departments: Array<{ id: string; name: string }>;
}

interface UnitOfMeasure {
    id: string;
    code: string;
    name: string;
}

interface Department {
    id: string;
    name: string;
}

const props = defineProps<{
    product: Product;
    categories: Category[];
    types: Type[];
    brands: Brand[];
    models: Model[];
    unitsOfMeasure: UnitOfMeasure[];
    departments: Department[];
}>();

const form = useForm({
    category_id: props.product.category_id,
    type_id: props.product.type_id,
    brand_id: props.product.brand_id,
    model_id: props.product.model_id ?? "",
    group: props.product.group,
    inventory_tracking_mode: props.product.inventory_tracking_mode ?? "QUANTITY",
    base_uom_id: props.product.base_uom_id ?? "",
    code: props.product.code,
    name: props.product.name,
    description: props.product.description ?? "",
    image: null as File | null,
    is_active: props.product.is_active,
    department_ids: props.product.departments.map((department) => department.id),
});

useMasterFieldFormatting(form);

const filteredTypes = computed(() =>
    form.category_id
        ? props.types.filter((item) => item.category_id === form.category_id)
        : props.types,
);

const filteredBrands = computed(() =>
    props.brands.filter((brand) => {
        const category = props.categories.find((item) => item.id === form.category_id);
        const rootCategoryId = category?.parent_id ?? category?.id;

        return brand.categories.some((brandCategory) =>
            brandCategory.id === form.category_id
            || (brandCategory.parent_id === null && brandCategory.id === rootCategoryId),
        );
    }),
);

const filteredModels = computed(() =>
    props.models.filter((item) => item.brand_id === form.brand_id && item.type_id === form.type_id),
);

watch(
    () => form.category_id,
    (value) => {
        const exists = props.types.some(
            (item) => item.id === form.type_id && item.category_id === value,
        );

        if (!value || !exists) {
            form.type_id = "";
        }

        const category = props.categories.find((item) => item.id === value);
        const rootCategoryId = category?.parent_id ?? category?.id;
        const brandExists = props.brands.some((brand) =>
            brand.id === form.brand_id && brand.categories.some((brandCategory) =>
                brandCategory.id === value
                || (brandCategory.parent_id === null && brandCategory.id === rootCategoryId),
            ),
        );
        if (!value || !brandExists) {
            form.brand_id = "";
            form.model_id = "";
        }
    },
);

watch(
    () => form.brand_id,
    (value) => {
        if (!value) {
            form.model_id = "";
            return;
        }

        const exists = props.models.some(
            (item) => item.id === form.model_id && item.brand_id === value && item.type_id === form.type_id,
        );

        if (!exists) {
            form.model_id = "";
        }
    },
);

watch(
    () => form.type_id,
    (value) => {
        const exists = props.models.some(
            (item) => item.id === form.model_id && item.type_id === value && item.brand_id === form.brand_id,
        );
        if (!exists) {
            form.model_id = "";
        }
    },
);

watch(
    () => form.group,
    (value) => {
        if (value !== "CONSUMABLE") {
            form.inventory_tracking_mode = "QUANTITY";
            form.base_uom_id = "";
        }
    },
);

const submit = () => {
    form.transform((data) => ({ ...data, _method: "put" })).post(
        route("master.product.products.update", props.product.id),
        { forceFormData: true },
    );
};

function selectImage(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.image = input.files?.[0] ?? null;
}

const cancel = () => {
    window.location.href = route("master.product.products.index");
};
</script>

<template>
    <CrudForm
        title="Edit Product"
        submit-label="Submit Changes"
        submit-processing-label="Updating..."
        :processing="form.processing"
        @submit="submit"
        @cancel="cancel"
    >
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <fieldset class="md:col-span-2">
                <legend class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Department pemilik <span class="text-red-500">*</span></legend>
                <div class="mt-2 grid max-h-56 grid-cols-1 gap-2 overflow-y-auto rounded-xl border border-gray-300 bg-white p-3 sm:grid-cols-2 dark:border-gray-700 dark:bg-gray-900">
                    <label v-for="department in departments" :key="department.id" class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800">
                        <input v-model="form.department_ids" type="checkbox" :value="department.id" class="size-4 rounded border-gray-300 text-brand-700 focus:ring-brand-700/20 dark:border-gray-600 dark:bg-gray-800" />
                        <span>{{ department.name }}</span>
                    </label>
                </div>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Katalog produk berlaku untuk semua branch. Department menentukan unit yang dapat menggunakan produk ini.</p>
                <p v-if="form.errors.department_ids" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ form.errors.department_ids }}</p>
            </fieldset>

            <div>
                <label
                    for="group"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Group
                    <span class="text-red-500">*</span>
                </label>

                <select
                    id="group"
                    v-model="form.group"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="ASSET">ASSET</option>
                    <option value="CONSUMABLE">CONSUMABLE</option>
                    <option value="LICENSE">LICENSE</option>
                </select>

                <p
                    v-if="form.errors.group"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.group }}
                </p>
            </div>

            <div v-if="form.group === 'CONSUMABLE'">
                <label for="inventory_tracking_mode" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Inventory Tracking
                    <span class="text-red-500">*</span>
                </label>
                <select id="inventory_tracking_mode" v-model="form.inventory_tracking_mode" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="QUANTITY">Quantity based</option>
                    <option value="SERIAL">Serial number per unit</option>
                </select>
                <p v-if="form.errors.inventory_tracking_mode" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ form.errors.inventory_tracking_mode }}</p>
            </div>

            <div v-if="form.group === 'CONSUMABLE'">
                <label for="base_uom_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Satuan Stok <span class="text-red-500">*</span></label>
                <select id="base_uom_id" v-model="form.base_uom_id" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white" required>
                    <option value="" disabled>Pilih satuan dasar stok</option>
                    <option v-for="unit in unitsOfMeasure" :key="unit.id" :value="unit.id">{{ unit.code }} · {{ unit.name }}</option>
                </select>
                <p v-if="form.errors.base_uom_id" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ form.errors.base_uom_id }}</p>
            </div>

            <div>
                <label
                    for="code"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Product Code
                    <span class="text-red-500">*</span>
                </label>

                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    maxlength="50"
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
                    Product Name
                    <span class="text-red-500">*</span>
                </label>

                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    maxlength="150"
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
                <label for="image" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Gambar produk <span class="font-normal text-gray-400">(Opsional)</span></label>
                <div v-if="product.image_url" class="mb-3">
                    <img :src="product.image_url" :alt="`Gambar ${product.name}`" class="h-32 w-32 rounded-xl border border-gray-200 object-cover dark:border-gray-700" />
                </div>
                <input id="image" type="file" accept="image/jpeg,image/png,image/webp" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="selectImage" />
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Format JPG, PNG, atau WebP. Maksimal 5 MB. Kosongkan jika tidak ingin mengganti gambar.</p>
                <p v-if="form.image" class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ form.image.name }}</p>
                <p v-if="form.errors.image" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ form.errors.image }}</p>
            </div>

            <div>
                <label
                    for="category_id"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Category
                    <span class="text-red-500">*</span>
                </label>

                <select
                    id="category_id"
                    v-model="form.category_id"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="">Select Category</option>
                    <option
                        v-for="item in props.categories"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }} ({{ item.code }})
                    </option>
                </select>

                <p
                    v-if="form.errors.category_id"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.category_id }}
                </p>
            </div>

            <div>
                <label
                    for="type_id"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Type
                    <span class="text-red-500">*</span>
                </label>

                <select
                    id="type_id"
                    v-model="form.type_id"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="">Select Type</option>
                    <option
                        v-for="item in filteredTypes"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }} ({{ item.category?.name ?? "-" }})
                    </option>
                </select>

                <p
                    v-if="form.errors.type_id"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
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
                    <span class="text-red-500">*</span>
                </label>

                <select
                    id="brand_id"
                    v-model="form.brand_id"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="">Select Brand</option>
                    <option
                        v-for="item in filteredBrands"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }} ({{ item.code }})
                    </option>
                </select>

                <p
                    v-if="form.errors.brand_id"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.brand_id }}
                </p>
            </div>

            <div>
                <label
                    for="model_id"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Model
                    <span class="ml-1 text-xs font-normal text-gray-400">Optional</span>
                </label>

                <select
                    id="model_id"
                    v-model="form.model_id"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    :disabled="!form.brand_id || !form.type_id"
                >
                    <option value="">Tanpa model</option>
                    <option
                        v-for="item in filteredModels"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }} ({{ item.brand?.name ?? "-" }})
                    </option>
                </select>

                <p
                    v-if="form.errors.model_id"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.model_id }}
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
