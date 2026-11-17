<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/layout/AppLayout.vue";
import AppPageHeader from "@/components/content/Header.vue";

defineOptions({ layout: AppLayout });

defineProps<{
    profile: {
        name: string;
        email: string;
        roles: string[];
    };
}>();
</script>

<template>
    <Head title="My Profile" />

    <div class="space-y-6">
        <AppPageHeader
            title="My Profile"
            description="Informasi akun yang sedang digunakan."
        />

        <section
            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex items-center gap-4 border-b border-gray-200 pb-6 dark:border-gray-800">
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 text-xl font-semibold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300"
                    aria-hidden="true"
                >
                    {{ profile.name.charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-semibold text-gray-900 dark:text-white">
                        {{ profile.name }}
                    </h1>
                    <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                        {{ profile.email }}
                    </p>
                </div>
            </div>

            <dl class="grid gap-5 pt-6 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Name</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                        {{ profile.name }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Email</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                        {{ profile.email }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Role</dt>
                    <dd class="mt-1 flex flex-wrap gap-2">
                        <span
                            v-for="role in profile.roles"
                            :key="role"
                            class="rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700 dark:bg-brand-900/40 dark:text-brand-300"
                        >
                            {{ role }}
                        </span>
                        <span
                            v-if="profile.roles.length === 0"
                            class="text-sm text-gray-500 dark:text-gray-400"
                        >
                            No role assigned
                        </span>
                    </dd>
                </div>
            </dl>
        </section>
    </div>
</template>
