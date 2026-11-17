<script setup>
import { computed } from "vue";
import { router, useForm } from "@inertiajs/vue3";

import AppLayout from "@/layout/AppLayout.vue";
import CrudForm from "@/components/crud/CrudForm.vue";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import ToggleSwitch from "primevue/toggleswitch";
import Message from "primevue/message";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    workflow: {
        type: Object,
        required: true,
    },

    companies: {
        type: Array,
        default: () => [],
    },

    departments: {
        type: Array,
        default: () => [],
    },

    users: {
        type: Array,
        default: () => [],
    },

    modules: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    module: props.workflow.module,
    company_id: props.workflow.company_id,
    department_id: props.workflow.department_id,
    name: props.workflow.name,
    is_active: Boolean(props.workflow.is_active),
    levels: (props.workflow.levels ?? []).map((level) => ({
        level: Number(level.level),
        approver_user_id: level.approver_user_id,
    })),
});

const filteredDepartments = computed(() => {
    return props.departments.filter(
        (department) =>
            String(department.company_id) === String(form.company_id),
    );
});

const approverUsers = computed(() => props.users);

const userLabel = (user) => {
    const details = [user.position?.name, user.department?.name, user.email]
        .filter(Boolean)
        .join(" · ");

    return details ? `${user.name} (${details})` : user.name;
};

const addLevel = () => {
    form.levels.push({
        level: form.levels.length + 1,
        approver_user_id: "",
    });
};

const removeLevel = (index) => {
    if (form.levels.length === 1) {
        return;
    }

    form.levels.splice(index, 1);

    form.levels.forEach((level, levelIndex) => {
        level.level = levelIndex + 1;
    });
};

const submit = () => {
    form.put(
        route("administrator.approval-workflows.update", props.workflow.id),
        {
            preserveScroll: true,
        },
    );
};

const cancel = () => {
    router.get(
        route("administrator.approval-workflows.index", props.workflow.id),
    );
};
</script>

<template>
    <CrudForm
        title="Edit Approval Workflow"
        submit-label="Save Changes"
        submit-processing-label="Saving..."
        :processing="form.processing"
        @submit="submit"
        @cancel="cancel"
    >
        <div class="space-y-5">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Workflow Name
                    </label>

                    <InputText v-model="form.name" class="w-full" />

                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Module
                    </label>

                    <Select
                        v-model="form.module"
                        :options="props.modules"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />

                    <p
                        v-if="form.errors.module"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.module }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Company
                    </label>

                    <Select
                        v-model="form.company_id"
                        :options="props.companies"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                        @change="form.department_id = ''"
                    />

                    <p
                        v-if="form.errors.company_id"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.company_id }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Department
                    </label>

                    <Select
                        v-model="form.department_id"
                        :options="filteredDepartments"
                        optionLabel="name"
                        optionValue="id"
                        :disabled="!form.company_id"
                        class="w-full"
                    />

                    <p
                        v-if="form.errors.department_id"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.department_id }}
                    </p>
                </div>

                <div class="flex items-center gap-3 md:col-span-2">
                    <ToggleSwitch v-model="form.is_active" />

                    <div>
                        <div
                            class="text-sm font-medium text-gray-900 dark:text-white"
                        >
                            Active
                        </div>

                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Workflow aktif dapat digunakan transaksi baru.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div
                class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-base font-semibold">Approval Levels</h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Urutan level menentukan urutan approval.
                    </p>
                </div>

                <Button
                    type="button"
                    label="Tambah Level"
                    icon="pi pi-plus"
                    severity="secondary"
                    outlined
                    @click="addLevel"
                />
            </div>

            <Message v-if="form.errors.levels" severity="error" class="mb-4">
                {{ form.errors.levels }}
            </Message>

            <Message
                v-if="approverUsers.length === 0"
                severity="warn"
                class="mb-4"
            >
                Belum ada user aktif yang dapat dipilih sebagai approver.
                Aktifkan atau buat user terlebih dahulu.
            </Message>

            <div
                v-for="(level, index) in form.levels"
                :key="index"
                class="mb-3 grid grid-cols-[70px_minmax(0,1fr)_50px] items-end gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-800"
            >
                <div>
                    <label class="mb-2 block text-sm font-medium">
                        Level
                    </label>

                    <div
                        class="flex h-10 items-center justify-center rounded-md border border-gray-200 bg-gray-50 font-semibold dark:border-gray-700 dark:bg-gray-800"
                    >
                        {{ level.level }}
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">
                        Approver
                    </label>

                    <Select
                        v-model="level.approver_user_id"
                        :options="approverUsers"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Pilih approver"
                        :disabled="!form.company_id || !form.department_id"
                        class="w-full"
                        filter
                        :filterFields="['name', 'email']"
                    >
                        <template #option="slotProps">
                            <div class="flex flex-col py-1">
                                <span class="font-medium">
                                    {{ slotProps.option.name }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    {{ userLabel(slotProps.option) }}
                                </span>
                            </div>
                        </template>

                        <template #value="slotProps">
                            <span v-if="slotProps.value">
                                {{ approverUsers.find((user) => String(user.id) === String(slotProps.value))?.name ?? "Pilih approver" }}
                            </span>
                            <span v-else class="text-gray-400">
                                Pilih approver
                            </span>
                        </template>
                    </Select>
                </div>

                <Button
                    type="button"
                    icon="pi pi-trash"
                    severity="danger"
                    text
                    rounded
                    :disabled="form.levels.length === 1"
                    @click="removeLevel(index)"
                />
            </div>
        </div>
    </CrudForm>
</template>
