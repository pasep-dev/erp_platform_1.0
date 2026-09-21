<script setup lang="ts">
import CrudForm from "@/components/crud/CrudForm.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";

defineOptions({
    layout: AppLayout,
});

interface Brand {
    id: string;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
}

const props = defineProps<{
    brand: Brand;
}>();

const form = useForm({
    code: props.brand.code,
    name: props.brand.name,
    description: props.brand.description ?? "",
    is_active: Boolean(props.brand.is_active),
});

const submit = () => {
    form.put(route("master.asset-management.brands.update", props.brand.id));
};

const cancel = () => {
    window.location.href = route("master.asset-management.brands.index");
};
</script>

<template>
    <Head title="Edit Asset Brand" />

    <CrudForm
        title="Edit Asset Brand"
        description="Perbarui data merek atau produsen aset."
        submit-label="Simpan Perubahan"
        submit-processing-label="Memperbarui..."
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
                />

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
                <label class="flex cursor-pointer items-center gap-3">
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
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.is_active }}
                </p>
            </div>
        </div>
    </CrudForm>
</template>
