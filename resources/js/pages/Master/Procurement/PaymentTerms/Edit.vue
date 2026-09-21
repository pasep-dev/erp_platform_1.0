<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";

import AppLayout from "@/layouts/AppLayout.vue";
import CrudForm from "@/components/crud/CrudForm.vue";

defineOptions({
    layout: AppLayout,
});

interface PaymentTerm {
    id: string;
    code: string;
    name: string;
    due_days: number;
    description: string | null;
    is_active: boolean;
}

const props = defineProps<{
    paymentTerm: PaymentTerm;
}>();

const form = useForm({
    code: props.paymentTerm.code,
    name: props.paymentTerm.name,
    due_days: props.paymentTerm.due_days,
    description: props.paymentTerm.description ?? "",
    is_active: props.paymentTerm.is_active,
});

function submit(): void {
    form.put(
        route("master.procurement.payment-terms.update", props.paymentTerm.id),
    );
}

const cancel = () => {
    window.location.href = route("master.procurement.payment-terms.index");
};
</script>

<template>
    <div class="space-y-6">
        <Head title="Edit Payment Terms" />

        <CrudForm
            :form="form"
            title="Edit Payment Terms"
            submit-label="Save"
            :cancel-url="route('master.procurement.payment-terms.index')"
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
                        Payment Term Code
                    </label>

                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        placeholder="Contoh: NET30"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="form.errors.code"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.code }}
                    </p>
                </div>

                <div>
                    <label
                        for="name"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Payment Term Name
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Contoh: Net 30 Days"
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
                        for="due_days"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Due Days
                    </label>

                    <input
                        id="due_days"
                        v-model.number="form.due_days"
                        type="number"
                        min="0"
                        max="3650"
                        placeholder="Contoh: 30"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    />

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Jumlah hari sampai tanggal jatuh tempo.
                    </p>

                    <p
                        v-if="form.errors.due_days"
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.due_days }}
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
                        placeholder="Catatan singkat payment term"
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
                        class="inline-flex cursor-pointer items-center gap-3"
                    >
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
                        class="mt-1 text-sm text-red-500"
                    >
                        {{ form.errors.is_active }}
                    </p>
                </div>
            </div>
        </CrudForm>
    </div>
</template>
