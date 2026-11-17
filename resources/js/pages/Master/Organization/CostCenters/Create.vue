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

interface Company {
    id: string;
    code: string;
    name: string;
}

const props = defineProps<{
    companies: Company[];
}>();

const form = useForm({
    company_id: "",
    code: "",
    name: "",
    is_active: true,
});

useMasterFieldFormatting(form);

const submit = () => {
    form.post(route("master.organization.cost-centers.store"));
};

const cancel = () => {
    window.location.href = route("master.organization.cost-centers.index");
};
</script>

<template>
    <CrudForm
        title="Add Cost Center"
        submit-processing-label="Saving..."
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
                    placeholder="Contoh: CC-IT"
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
                    placeholder="Contoh: Information Technology"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-blue-500 dark:border-gray-700 dark:text-white"
                />

                <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">
                    {{ form.errors.name }}
                </p>
            </div>
        </div>

        <div>
            <MasterActiveToggle
                v-model="form.is_active"
                :error="form.errors.is_active"
            />
        </div>
    </CrudForm>
</template>
