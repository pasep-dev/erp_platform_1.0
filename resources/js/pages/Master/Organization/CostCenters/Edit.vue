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
}

interface CostCenter {
    id: string;
    company_id: string;
    code: string;
    name: string;
    is_active: boolean;
}

const props = defineProps<{
    costCenter: CostCenter;
    companies: Company[];
}>();

const form = useForm({
    company_id: props.costCenter.company_id,
    code: props.costCenter.code,
    name: props.costCenter.name,
    is_active: Boolean(props.costCenter.is_active),
});

const submit = () => {
    form.put(
        route("master.organization.cost-centers.update", props.costCenter.id),
    );
};

const cancel = () => {
    window.location.href = route("master.organization.cost-centers.index");
};
</script>

<template>
    <Head title="Edit Cost Center" />

    <CrudForm
        title="Edit Cost Center"
        description="Perbarui data cost center."
        submit-label="Simpan Perubahan"
        submit-processing-label="Memperbarui..."
        :processing="form.processing"
        @submit="submit"
        @cancel="cancel"
    >
        <div>
            <label
                for="company_id"
                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                Company
            </label>

            <select
                id="company_id"
                v-model="form.company_id"
                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            >
                <option value="">Pilih Company</option>

                <option
                    v-for="company in props.companies"
                    :key="company.id"
                    :value="company.id"
                >
                    {{ company.code }} - {{ company.name }}
                </option>
            </select>

            <p v-if="form.errors.company_id" class="mt-1 text-sm text-red-500">
                {{ form.errors.company_id }}
            </p>
        </div>

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
