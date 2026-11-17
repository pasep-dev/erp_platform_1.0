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

interface Vendor {
    id: string;
    code: string;
    name: string;
    tax_number: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    description: string | null;
    is_active: boolean;
    vendor_type_id: string | null;
}

interface VendorType {
    id: string;
    code: string;
    name: string;
    is_active?: boolean;
}

const props = defineProps<{
    vendor: Vendor;
    vendorTypes: VendorType[];
}>();

const form = useForm({
    vendor_type_id: props.vendor.vendor_type_id ?? "",

    code: props.vendor.code,
    name: props.vendor.name,
    tax_number: props.vendor.tax_number ?? "",
    email: props.vendor.email ?? "",
    phone: props.vendor.phone ?? "",
    address: props.vendor.address ?? "",
    description: props.vendor.description ?? "",
    is_active: props.vendor.is_active,
});

useMasterFieldFormatting(form);

function submit(): void {
    form.put(route("master.procurement.vendors.update", props.vendor.id));
}

const cancel = () => {
    window.location.href = route("master.procurement.vendors.index");
};
</script>

<template>
    <div class="space-y-6">
        <Head title="Edit Vendors" />

        <CrudForm
            :form="form"
            title="Edit Vendors"
            header-variant="procurement"
            header-section="Master Data"
            description="Perbarui informasi vendor yang tersimpan."
            submit-label="Save"
            :cancel-url="route('master.procurement.vendors.index')"
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
                        Vendor Code
                    </label>

                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        placeholder="Contoh: VEND-001"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    :disabled="true" />

                    <p
                        v-if="form.errors.code"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.code }}
                    </p>
                </div>

                <div>
                    <label
                        for="vendor_type_id"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Vendor Type <span class="text-error-500">*</span>
                    </label>

                    <select
                        id="vendor_type_id"
                        v-model="form.vendor_type_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    >
                        <option value="" disabled>Select Vendor Type</option>

                        <option
                            v-for="vendorType in props.vendorTypes"
                            :key="vendorType.id"
                            :value="vendorType.id"
                        >
                            {{ vendorType.name }} ({{ vendorType.code }})
                            <template v-if="vendorType.is_active === false">
                                — Inactive
                            </template>
                        </option>
                    </select>

                    <p
                        v-if="form.errors.vendor_type_id"
                        class="mt-1 text-sm text-error-500"
                    >
                        {{ form.errors.vendor_type_id }}
                    </p>
                </div>

                <div>
                    <label
                        for="name"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Vendor Name
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Contoh: PT Sumber Makmur"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <label
                        for="tax_number"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Tax Number
                    </label>

                    <input
                        id="tax_number"
                        v-model="form.tax_number"
                        type="text"
                        placeholder="Nomor NPWP / Tax Number"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="form.errors.tax_number"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.tax_number }}
                    </p>
                </div>

                <div>
                    <label
                        for="email"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Email
                    </label>

                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="vendor@example.com"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="form.errors.email"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label
                        for="phone"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Phone
                    </label>

                    <input
                        id="phone"
                        v-model="form.phone"
                        type="text"
                        placeholder="Contoh: 021-12345678"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="form.errors.phone"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.phone }}
                    </p>
                </div>

                <div>
                    <label
                        for="description"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Description
                    </label>

                    <input
                        id="description"
                        v-model="form.description"
                        type="text"
                        placeholder="Catatan singkat vendor"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="form.errors.description"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label
                        for="address"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Address
                    </label>

                    <textarea
                        id="address"
                        v-model="form.address"
                        rows="4"
                        placeholder="Alamat lengkap vendor..."
                        class="w-full resize-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    ></textarea>

                    <p
                        v-if="form.errors.address"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.address }}
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
    </div>
</template>
