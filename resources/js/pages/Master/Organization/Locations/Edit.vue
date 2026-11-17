<script setup lang="ts">
import { computed } from "vue";
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

interface Branch {
    id: string;
    company_id: string;
    code: string;
    name: string;
}

interface ParentLocation {
    id: string;
    company_id: string;
    code: string;
    name: string;
}

interface Location {
    id: string;
    company_id: string;
    branch_id: string | null;
    code: string;
    name: string;
    parent_id: string | null;
    location_type: string | null;
    is_active: boolean;
}

const props = defineProps<{
    location: Location;
    companies: Company[];
    branches: Branch[];
    parents: ParentLocation[];
}>();

const form = useForm({
    company_id: props.location.company_id,
    branch_id: props.location.branch_id ?? "",
    code: props.location.code,
    name: props.location.name,
    parent_id: props.location.parent_id ?? "",
    location_type: props.location.location_type ?? "",
    is_active: Boolean(props.location.is_active),
});

useMasterFieldFormatting(form);

const filteredBranches = computed(() => {
    if (!form.company_id) {
        return [];
    }

    return props.branches.filter(
        (branch) => branch.company_id === form.company_id,
    );
});

const filteredParents = computed(() => {
    if (!form.company_id) {
        return [];
    }

    return props.parents.filter(
        (parent) => parent.company_id === form.company_id,
    );
});

function submit(): void {
    form.put(route("master.organization.locations.update", props.location.id));
}

function cancel(): void {
    window.location.href = route("master.organization.locations.index");
}

function handleCompanyChange(): void {
    form.branch_id = "";
    form.parent_id = "";
}
</script>

<template>
    <Head title="Edit Location" />

    <CrudForm
        title="Edit Location"
        description="Perbarui data lokasi."
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
                @change="handleCompanyChange"
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
                    for="branch_id"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Branch
                </label>

                <select
                    id="branch_id"
                    v-model="form.branch_id"
                    :disabled="!form.company_id"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-none focus:border-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800"
                >
                    <option value="">
                        {{
                            form.company_id
                                ? "Pilih Branch"
                                : "Pilih Company terlebih dahulu"
                        }}
                    </option>

                    <option
                        v-for="branch in filteredBranches"
                        :key="branch.id"
                        :value="branch.id"
                    >
                        {{ branch.code }} - {{ branch.name }}
                    </option>
                </select>

                <p
                    v-if="form.errors.branch_id"
                    class="mt-1 text-sm text-red-500"
                >
                    {{ form.errors.branch_id }}
                </p>
            </div>

            <div>
                <label
                    for="location_type"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Location Type
                </label>

                <input
                    id="location_type"
                    v-model="form.location_type"
                    type="text"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-blue-500 dark:border-gray-700 dark:text-white"
                />

                <p
                    v-if="form.errors.location_type"
                    class="mt-1 text-sm text-red-500"
                >
                    {{ form.errors.location_type }}
                </p>
            </div>
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
                :disabled="true" />

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
                for="parent_id"
                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                Parent Location
            </label>

            <select
                id="parent_id"
                v-model="form.parent_id"
                :disabled="!form.company_id"
                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-none focus:border-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800"
            >
                <option value="">Tanpa Parent</option>

                <option
                    v-for="parent in filteredParents"
                    :key="parent.id"
                    :value="parent.id"
                >
                    {{ parent.code }} - {{ parent.name }}
                </option>
            </select>

            <p v-if="form.errors.parent_id" class="mt-1 text-sm text-red-500">
                {{ form.errors.parent_id }}
            </p>
        </div>

        <div>
            <MasterActiveToggle
                v-model="form.is_active"
                :error="form.errors.is_active"
            />
        </div>
    </CrudForm>
</template>
