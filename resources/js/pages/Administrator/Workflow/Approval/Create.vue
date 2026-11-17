<script setup lang="ts">
import { computed } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";

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

interface Company {
    id: string;
    code: string;
    name: string;
}

interface Department {
    id: string;
    company_id: string;
    code: string;
    name: string;
}

interface Position {
    id: string;
    name: string;
}

interface User {
    id: string;
    name: string;
    email: string;
    company_id?: string;
    department_id?: string;
    position_id?: string;
    position?: Position;
    department?: Department;
}

interface ModuleOption {
    label: string;
    value: string;
}

const props = defineProps<{
    companies?: Company[];
    departments?: Department[];
    users?: User[];
    modules?: ModuleOption[];
}>();

const form = useForm({
    module: "",
    company_id: "",
    department_id: "",
    name: "",
    is_active: true,
    levels: [
        {
            level: 1,
            approver_user_id: "",
        },
    ],
});

const filteredDepartments = computed(() => {
    if (!form.company_id) {
        return [];
    }

    return (props.departments ?? []).filter(
        (department) =>
            String(department.company_id) === String(form.company_id),
    );
});

const approverUsers = computed(() => {
    return props.users ?? [];
});

const userLabel = (user: User) => {
    const parts = [user.name];

    if (user.position?.name) {
        parts.push(user.position.name);
    }

    if (user.department?.name) {
        parts.push(user.department.name);
    }

    return parts.join(" • ");
};

const addLevel = () => {
    form.levels.push({
        level: form.levels.length + 1,
        approver_user_id: "",
    });
};

const removeLevel = (index: number) => {
    if (form.levels.length === 1) {
        return;
    }

    form.levels.splice(index, 1);

    form.levels.forEach((level, levelIndex) => {
        level.level = levelIndex + 1;
    });
};

const submit = () => {
    form.post(route("administrator.approval-workflows.store"), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.get(route("administrator.approval-workflows.index"));
};
</script>

<template>
    <CrudForm
        title="Create Approval Workflow"
        submit-label="Save"
        submit-processing-label="Saving..."
        :processing="form.processing"
        @submit="submit"
        @cancel="cancel"
    >
        <!-- Workflow Information Section -->
        <div class="space-y-5">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <!-- Workflow Name -->
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Workflow Name
                    </label>

                    <InputText
                        v-model="form.name"
                        class="w-full"
                        placeholder="Contoh: PR IT Approval"
                    />

                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Module -->
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Module
                    </label>

                    <Select
                        v-model="form.module"
                        :options="props.modules ?? []"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Pilih module"
                        class="w-full"
                    />

                    <p
                        v-if="form.errors.module"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.module }}
                    </p>
                </div>

                <!-- Company -->
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Company
                    </label>

                    <Select
                        v-model="form.company_id"
                        :options="props.companies ?? []"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Pilih company"
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

                <!-- Department -->
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
                        placeholder="Pilih department"
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

                <!-- Active -->
                <div class="flex items-center gap-3 md:col-span-2">
                    <ToggleSwitch v-model="form.is_active" />

                    <div>
                        <div
                            class="text-sm font-medium text-gray-900 dark:text-white"
                        >
                            Active
                        </div>

                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Workflow dapat digunakan untuk transaksi baru.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="border-gray-200 dark:border-gray-800" />

        <!-- Approval Levels Section -->
        <div class="space-y-5">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-base font-semibold text-gray-900 dark:text-white"
                    >
                        Approval Levels
                    </h2>

                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Approval diproses secara berurutan dari Level 1.
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

            <!-- Empty users warning -->
            <Message
                v-if="approverUsers.length === 0"
                severity="warn"
                class="mb-4"
            >
                Belum ada user aktif yang dapat dipilih sebagai approver.
                Silakan buat atau aktifkan user terlebih dahulu.
            </Message>

            <!-- Levels List -->
            <div
                v-for="(level, index) in form.levels"
                :key="index"
                class="grid grid-cols-[70px_minmax(0,1fr)_50px] items-end gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-800"
            >
                <!-- Level -->
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Level
                    </label>

                    <div
                        class="flex h-10 items-center justify-center rounded-md border border-gray-200 bg-gray-50 font-semibold text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        {{ level.level }}
                    </div>
                </div>

                <!-- Approver -->
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Approver
                    </label>

                    <Select
                        v-model="level.approver_user_id"
                        :options="approverUsers"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Pilih approver"
                        class="w-full"
                        filter
                        :filterFields="['name', 'email']"
                    >
                        <template #option="slotProps">
                            <div class="flex flex-col py-1">
                                <span
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{ slotProps.option.name }}
                                </span>

                                <span
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ userLabel(slotProps.option) }}
                                </span>

                                <span class="text-xs text-gray-400">
                                    {{ slotProps.option.email }}
                                </span>
                            </div>
                        </template>

                        <template #value="slotProps">
                            <span v-if="slotProps.value">
                                {{
                                    approverUsers.find(
                                        (user) =>
                                            String(user.id) ===
                                            String(slotProps.value),
                                    )?.name ?? "Pilih approver"
                                }}
                            </span>

                            <span v-else class="text-gray-400">
                                Pilih approver
                            </span>
                        </template>
                    </Select>

                    <p
                        v-if="form.errors[`levels.${index}.approver_user_id`]"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors[`levels.${index}.approver_user_id`] }}
                    </p>
                </div>

                <!-- Delete Level Button -->
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
