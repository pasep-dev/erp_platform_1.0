<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import AppLayout from "../../layouts/AppLayout.vue";
import { Building2, Globe2, Mail, MapPin, Phone, Save } from "lucide-vue-next";

interface Settings {
    company: {
        name: string;
        email: string;
        phone: string | null;
        address: string | null;
    };
    regional: {
        timezone: string;
        date_format: string;
        datetime_format: string;
    };
}

const props = defineProps<{
    settings: Settings;
}>();

const form = useForm({
    company: {
        name: props.settings.company.name,
        email: props.settings.company.email,
        phone: props.settings.company.phone ?? "",
        address: props.settings.company.address ?? "",
    },

    regional: {
        timezone: props.settings.regional.timezone,
        date_format: props.settings.regional.date_format,
        datetime_format: props.settings.regional.datetime_format,
    },
});

const submit = () => {
    form.put("/settings");
};
</script>

<template>
    <Head title="Settings" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Settings
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Manage application and organization settings.
                </p>
            </div>

            <!-- Company -->
            <section
                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
            >
                <div
                    class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <Building2 class="h-5 w-5" />
                        </div>

                        <div>
                            <h2
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                Company Information
                            </h2>

                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Basic organization information.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 p-6 md:grid-cols-2">
                    <!-- Company Name -->
                    <div>
                        <label
                            for="company-name"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Company Name
                        </label>

                        <input
                            id="company-name"
                            v-model="form.company.name"
                            type="text"
                            class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 text-sm text-gray-900 outline-none focus:border-gray-400 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-white dark:focus:border-gray-700 dark:focus:ring-gray-800"
                            :class="{
                                'border-red-500': form.errors['company.name'],
                            }"
                        />

                        <p
                            v-if="form.errors['company.name']"
                            class="mt-2 text-xs text-red-600"
                        >
                            {{ form.errors["company.name"] }}
                        </p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label
                            for="company-email"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Email
                        </label>

                        <div class="relative">
                            <Mail
                                class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                            />

                            <input
                                id="company-email"
                                v-model="form.company.email"
                                type="email"
                                class="h-11 w-full rounded-lg border border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-900 outline-none focus:border-gray-400 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-white dark:focus:border-gray-700 dark:focus:ring-gray-800"
                            />
                        </div>

                        <p
                            v-if="form.errors['company.email']"
                            class="mt-2 text-xs text-red-600"
                        >
                            {{ form.errors["company.email"] }}
                        </p>
                    </div>

                    <!-- Phone -->
                    <div>
                        <label
                            for="company-phone"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Phone
                        </label>

                        <div class="relative">
                            <Phone
                                class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                            />

                            <input
                                id="company-phone"
                                v-model="form.company.phone"
                                type="text"
                                class="h-11 w-full rounded-lg border border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-900 outline-none focus:border-gray-400 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-white dark:focus:border-gray-700 dark:focus:ring-gray-800"
                            />
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="md:col-span-2">
                        <label
                            for="company-address"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Address
                        </label>

                        <div class="relative">
                            <MapPin
                                class="pointer-events-none absolute left-3 top-3 h-5 w-5 text-gray-400"
                            />

                            <textarea
                                id="company-address"
                                v-model="form.company.address"
                                rows="3"
                                class="w-full rounded-lg border border-gray-200 bg-white py-3 pl-10 pr-4 text-sm text-gray-900 outline-none focus:border-gray-400 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-white dark:focus:border-gray-700 dark:focus:ring-gray-800"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Regional -->
            <section
                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
            >
                <div
                    class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <Globe2 class="h-5 w-5" />
                        </div>

                        <div>
                            <h2
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                Regional Settings
                            </h2>

                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Configure timezone and date formatting.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 p-6 md:grid-cols-3">
                    <!-- Timezone -->
                    <div>
                        <label
                            for="timezone"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Timezone
                        </label>

                        <select
                            id="timezone"
                            v-model="form.regional.timezone"
                            class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 text-sm text-gray-900 outline-none focus:border-gray-400 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-white dark:focus:border-gray-700 dark:focus:ring-gray-800"
                        >
                            <option value="Asia/Jakarta">Asia/Jakarta</option>

                            <option value="Asia/Makassar">Asia/Makassar</option>

                            <option value="Asia/Jayapura">Asia/Jayapura</option>

                            <option value="UTC">UTC</option>
                        </select>
                    </div>

                    <!-- Date -->
                    <div>
                        <label
                            for="date-format"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Date Format
                        </label>

                        <input
                            id="date-format"
                            v-model="form.regional.date_format"
                            type="text"
                            placeholder="d/m/Y"
                            class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 text-sm text-gray-900 outline-none focus:border-gray-400 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-white dark:focus:border-gray-700 dark:focus:ring-gray-800"
                        />

                        <p class="mt-1 text-xs text-gray-400">Example: d/m/Y</p>
                    </div>

                    <!-- DateTime -->
                    <div>
                        <label
                            for="datetime-format"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Date & Time Format
                        </label>

                        <input
                            id="datetime-format"
                            v-model="form.regional.datetime_format"
                            type="text"
                            placeholder="d/m/Y H:i"
                            class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 text-sm text-gray-900 outline-none focus:border-gray-400 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-white dark:focus:border-gray-700 dark:focus:ring-gray-800"
                        />

                        <p class="mt-1 text-xs text-gray-400">
                            Example: d/m/Y H:i
                        </p>
                    </div>
                </div>
            </section>

            <!-- Save -->
            <div class="flex items-center justify-end gap-4">
                <span
                    v-if="form.recentlySuccessful"
                    class="text-sm text-green-600"
                >
                    Settings berhasil disimpan.
                </span>

                <button
                    type="button"
                    :disabled="form.processing"
                    class="inline-flex h-11 items-center gap-2 rounded-lg bg-gray-900 px-5 text-sm font-medium text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
                    @click="submit"
                >
                    <Save class="h-4 w-4" />

                    <span>
                        {{ form.processing ? "Saving..." : "Save Changes" }}
                    </span>
                </button>
            </div>
        </div>
    </AppLayout>
</template>
