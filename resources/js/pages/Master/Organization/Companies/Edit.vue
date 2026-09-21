<script setup lang="ts">
import CrudForm from "@/components/crud/CrudForm.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";

defineOptions({
    layout: AppLayout,
});

interface Company {
    id: string;
    code: string;
    name: string;
    short_name: string | null;
    is_active: boolean;
}

const props = defineProps<{
    company: Company;
}>();

const form = useForm({
    code: props.company.code,
    name: props.company.name,
    short_name: props.company.short_name,
    is_active: Boolean(props.company.is_active),
});

const submit = () => {
    form.put(route("master.organization.companies.update", props.company.id));
};

const cancel = () => {
    window.location.href = route("master.organization.companies.index");
};
</script>

<template>
    <Head title="Edit Company" />

    <CrudForm
        title="Edit Company"
        description="Perbarui data perusahaan."
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
                    Code
                </label>

                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-blue-500 dark:border-gray-700 dark:text-white"
                />

                <p v-if="form.errors.code" class="mt-1 text-sm text-red-500">
                    {{ form.errors.code }}
                </p>
            </div>

            <div>
                <label
                    for="short_name"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Short Name
                </label>

                <input
                    id="short_name"
                    v-model="form.short_name"
                    type="text"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-blue-500 dark:border-gray-700 dark:text-white"
                />

                <p
                    v-if="form.errors.short_name"
                    class="mt-1 text-sm text-red-500"
                >
                    {{ form.errors.short_name }}
                </p>
            </div>
        </div>

        <div>
            <label
                for="name"
                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                Name
            </label>

            <input
                id="name"
                v-model="form.name"
                type="text"
                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-blue-500 dark:border-gray-700 dark:text-white"
            />

            <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">
                {{ form.errors.name }}
            </p>
        </div>

        <div>
            <label
                for="is_active"
                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                Status
            </label>

            <select
                id="is_active"
                v-model="form.is_active"
                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            >
                <option :value="true">Active</option>
                <option :value="false">Inactive</option>
            </select>

            <p v-if="form.errors.is_active" class="mt-1 text-sm text-red-500">
                {{ form.errors.is_active }}
            </p>
        </div>
    </CrudForm>
</template>
