<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useMasterFieldFormatting } from "@/composables/useMasterFieldFormatting";

import AppLayout from "@/layout/AppLayout.vue";
import MasterActiveToggle from "@/components/common/MasterActiveToggle.vue";
import CrudForm from "@/components/crud/CrudForm.vue";

defineOptions({
    layout: AppLayout,
});

interface VendorType {
    id: string;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
}

interface Props {
    vendorType: VendorType;
}

const props = defineProps<Props>();

const form = useForm({
    code: props.vendorType.code,
    name: props.vendorType.name,
    description: props.vendorType.description ?? "",
    is_active: props.vendorType.is_active,
});

useMasterFieldFormatting(form);

function submit(): void {
    form.put(
        route("master.procurement.vendor-types.update", props.vendorType.id),
    );
}

function cancel(): void {
    window.location.href = route("master.procurement.vendor-types.index");
}
</script>

<template>
    <Head title="Edit Vendor Type" />

    <CrudForm
        title="Edit Vendor Type"
        header-variant="procurement"
        header-section="Master Data"
        description="Perbarui nama dan keterangan tipe vendor."
        :form="form"
        submit-label="Update"
        :cancel-url="route('master.procurement.vendor-types.index')"
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
                    Code
                </label>

                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    maxlength="50"
                    autocomplete="off"
                    placeholder="Contoh: DISTRIBUTOR"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                :disabled="true" />

                <p v-if="form.errors.code" class="mt-1.5 text-sm text-red-500">
                    {{ form.errors.code }}
                </p>
            </div>

            <div>
                <label
                    for="name"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Vendor Type Name
                </label>

                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    maxlength="150"
                    placeholder="Contoh: Distributor"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                />

                <p v-if="form.errors.name" class="mt-1.5 text-sm text-red-500">
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
                    placeholder="Masukkan deskripsi vendor type..."
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                ></textarea>

                <p
                    v-if="form.errors.description"
                    class="mt-1.5 text-sm text-red-500"
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
