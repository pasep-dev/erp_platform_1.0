<script setup lang="ts">
import CrudForm from "@/components/crud/CrudForm.vue";
import AppLayout from "@/layout/AppLayout.vue";
import MasterActiveToggle from "@/components/common/MasterActiveToggle.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useMasterFieldFormatting } from "@/composables/useMasterFieldFormatting";

interface Category {
    id: string;
    code: string;
    name: string;
    department_id: string | null;
    department?: { code: string; name: string } | null;
}

defineOptions({
    layout: AppLayout,
});

interface Brand {
    id: string;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
    categories: Category[];
}

const props = defineProps<{
    brand: Brand;
    categories: Category[];
}>();

const form = useForm({
    code: props.brand.code,
    name: props.brand.name,
    category_ids: props.brand.categories.map((category) => category.id),
    description: props.brand.description ?? "",
    is_active: Boolean(props.brand.is_active),
});

useMasterFieldFormatting(form);

const submit = () => {
    form.put(route("master.product.brands.update", props.brand.id));
};

const cancel = () => {
    window.location.href = route("master.product.brands.index");
};
</script>

<template>
    <CrudForm
        title="Edit Brand"
        submit-label="Submit Changes"
        submit-processing-label="Updating..."
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
                    Brand Code
                    <span class="text-red-500">*</span>
                </label>

                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    maxlength="50"
                    placeholder="Contoh: ASUS"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                :disabled="true" />

                <p
                    v-if="form.errors.code"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.code }}
                </p>
            </div>

            <div>
                <label
                    for="name"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Brand Name
                    <span class="text-red-500">*</span>
                </label>

                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    maxlength="150"
                    placeholder="Contoh: ASUS"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                />

                <p
                    v-if="form.errors.name"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.name }}
                </p>
            </div>

            <fieldset class="md:col-span-2">
                <legend class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Categories <span class="text-red-500">*</span>
                </legend>
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                    Brand berlaku untuk kategori utama yang dipilih beserta seluruh subkategori aktifnya.
                </p>
                <div class="grid max-h-72 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3">
                    <label
                        v-for="category in props.categories"
                        :key="category.id"
                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-white p-3 transition hover:border-brand-300 hover:bg-brand-50/40 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-brand-700 dark:hover:bg-brand-950/20"
                    >
                        <input
                            v-model="form.category_ids"
                            type="checkbox"
                            :value="category.id"
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-700 focus:ring-brand-600 dark:border-gray-600 dark:bg-gray-800"
                        />
                        <span>
                            <span class="block text-sm font-medium text-gray-800 dark:text-gray-100">{{ category.name }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ category.code }}</span>
                            <span v-if="category.department" class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                {{ category.department.code }} · {{ category.department.name }}
                            </span>
                        </span>
                    </label>
                </div>
                <p v-if="form.errors.category_ids" class="mt-2 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.category_ids }}
                </p>
                <p v-else-if="props.categories.length === 0" class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                    Belum ada kategori aktif. Tambahkan kategori sebelum mengubah brand.
                </p>
            </fieldset>

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
                    placeholder="Deskripsi brand..."
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
