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
        <div class="mb-8">
            <p
                class="login-eyebrow text-sm font-semibold uppercase tracking-[0.14em]"
            >
                Welcome back
            </p>
            <h2
                id="guest-form-title"
                class="login-heading mt-2 text-3xl font-semibold tracking-tight"
            >
                Sign in
            </h2>
            <p class="login-description mt-2 text-sm leading-6">
                Enter your account details to continue.
            </p>
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <!-- Email -->
            <div>
                <label
                    for="email"
                    class="login-label mb-2 block text-sm font-medium"
                >
                    Email address
                </label>

                <div class="relative">
                    <Mail
                        class="login-field-icon pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2"
                    />

                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        :aria-invalid="Boolean(form.errors.email)"
                        autofocus
                        placeholder="you@company.com"
                        class="login-field h-12 w-full rounded-lg border pl-10 pr-4 text-sm outline-none transition"
                    />
                </div>

                <p
                    v-if="form.errors.email"
                    class="login-error mt-2 text-sm"
                >
                    {{ form.errors.email }}
                </p>
            </div>

            <!-- Password -->
            <div>
                <label
                    for="password"
                    class="login-label mb-2 block text-sm font-medium"
                >
                    Password
                </label>

                <div class="relative">
                    <LockKeyhole
                        class="login-field-icon pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2"
                    />

                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        name="password"
                        autocomplete="current-password"
                        :aria-invalid="Boolean(form.errors.password)"
                        placeholder="Enter your password"
                        class="login-field h-12 w-full rounded-lg border pl-10 pr-11 text-sm outline-none transition"
                    />

                    <button
                        type="button"
                        class="login-password-toggle absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1 transition"
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
                    class="login-error mt-2 text-sm"
                >
                    {{ form.errors.password }}
                </p>
            </div>

            <!-- Remember -->
            <label class="flex cursor-pointer items-center gap-2">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="login-checkbox h-4 w-4 rounded border-gray-300"
                />

                <span class="login-description text-sm">
                    Remember me
                </span>
            </label>

            <!-- Submit -->
            <button
                type="submit"
                :disabled="form.processing"
                class="login-submit flex h-12 w-full items-center justify-center rounded-lg px-4 text-sm font-semibold text-white shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span v-if="form.processing"> Signing in... </span>

                <span v-else> Sign in </span>
            </button>
        </form>
    </GuestLayout>
</template>

<style scoped>
.login-eyebrow {
    color: var(--p-primary-color);
}

.login-heading,
.login-label {
    color: var(--app-text-primary);
}

.login-description,
.login-field-icon,
.login-password-toggle {
    color: var(--app-text-secondary);
}

.login-error {
    color: var(--app-danger-strong);
}

.login-field {
    border-color: var(--app-border);
    background-color: var(--app-surface);
    color: var(--app-text-primary);
}

.login-field::placeholder {
    color: var(--app-text-muted);
}

.login-field:focus {
    border-color: var(--p-primary-color);
    box-shadow: 0 0 0 4px var(--app-primary-soft);
}

.login-field[aria-invalid="true"] {
    border-color: var(--app-danger);
}

.login-field[aria-invalid="true"]:focus {
    box-shadow: 0 0 0 4px var(--app-danger-soft);
}

.login-checkbox {
    accent-color: var(--p-primary-color);
}

.login-password-toggle:hover {
    color: var(--p-primary-color);
}

.login-password-toggle:focus-visible,
.login-submit:focus-visible {
    outline: 2px solid var(--p-primary-color);
    outline-offset: 3px;
}

.login-submit {
    background: var(--p-primary-color);
}

.login-submit:hover {
    background: var(--p-primary-hover-color);
}
</style>
