<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import GuestLayout from "../../layouts/GuestLayout.vue";
import { Eye, EyeOff, LockKeyhole, Mail } from "lucide-vue-next";
import { ref } from "vue";
import { usePage } from "@inertiajs/vue3";

const page = usePage();

const showPassword = ref(false);

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const submit = () => {
    form.post("/login", {
        onFinish: () => {
            form.password = "";
        },
    });
};
</script>

<template>
    <Head :title="`Login - ${page.props.app.name}`" />

    <GuestLayout>
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                Sign in
            </h2>
        </div>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <!-- Email -->
            <div>
                <label
                    for="email"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Email address
                </label>

                <div class="relative">
                    <Mail
                        class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                    />

                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        autofocus
                        placeholder="you@company.com"
                        class="h-11 w-full rounded-lg border border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-gray-400 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-gray-700 dark:focus:ring-gray-800"
                        :class="{
                            'border-red-500 focus:border-red-500 focus:ring-red-100':
                                form.errors.email,
                        }"
                    />
                </div>

                <p v-if="form.errors.email" class="mt-2 text-xs text-red-600">
                    {{ form.errors.email }}
                </p>
            </div>

            <!-- Password -->
            <div>
                <label
                    for="password"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Password
                </label>

                <div class="relative">
                    <LockKeyhole
                        class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                    />

                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        name="password"
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        class="h-11 w-full rounded-lg border border-gray-200 bg-white pl-10 pr-11 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-gray-400 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-gray-700 dark:focus:ring-gray-800"
                        :class="{
                            'border-red-500 focus:border-red-500 focus:ring-red-100':
                                form.errors.password,
                        }"
                    />

                    <button
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200"
                        :aria-label="
                            showPassword ? 'Hide password' : 'Show password'
                        "
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="h-5 w-5" />

                        <Eye v-else class="h-5 w-5" />
                    </button>
                </div>

                <p
                    v-if="form.errors.password"
                    class="mt-2 text-xs text-red-600"
                >
                    {{ form.errors.password }}
                </p>
            </div>

            <!-- Remember -->
            <label class="flex cursor-pointer items-center gap-2">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-400 dark:border-gray-700 dark:bg-gray-950"
                />

                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Remember me
                </span>
            </label>

            <!-- Submit -->
            <button
                type="submit"
                :disabled="form.processing"
                class="flex h-11 w-full items-center justify-center rounded-lg bg-gray-900 px-4 text-sm font-medium text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
            >
                <span v-if="form.processing"> Signing in... </span>

                <span v-else> Sign in </span>
            </button>
        </form>
    </GuestLayout>
</template>
