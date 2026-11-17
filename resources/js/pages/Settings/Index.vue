<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/layout/AppLayout.vue";
import { route } from "ziggy-js";
import {
    Blocks,
    Building2,
    CloudDownload,
    Database,
    Globe2,
    HardDrive,
    ImagePlus,
    LoaderCircle,
    Mail,
    MapPin,
    Phone,
    Save,
    Trash2,
} from "lucide-vue-next";
import miniSvg from "@/../images/logo/citra-logo.svg";

interface Settings {
    system: {
        name: string;
        logo_url: string | null;
    };
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

interface BackupSettings {
    enabled: boolean;
    time: string;
    retention: number;
    directory: string;
    last_success_at: string | null;
    available: boolean;
    driver: string;
    storage_available: boolean;
    storage_location: string;
    mount_root: string;
    backups: BackupFile[];
}

interface BackupFile {
    filename: string;
    size_bytes: number;
    created_at: string;
}

interface NumberingModule {
    code: string;
    name: string;
    category: string;
    is_active: boolean;
}

const props = defineProps<{
    settings: Settings;
    backup: BackupSettings;
    modules: NumberingModule[];
}>();

const activeTab = ref<"identity" | "backup" | "modules">("identity");
const form = useForm({
    system: {
        name: props.settings.system.name,
        logo: null as File | null,
        remove_logo: false,
    },

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

    backup: {
        enabled: props.backup.enabled,
        time: props.backup.time,
        directory: props.backup.directory,
    },
});

const backupForm = useForm({});
const moduleAvailabilityForm = useForm({
    is_active: false,
});
const moduleCreateForm = useForm({
    code: "",
    name: "",
    category: "",
});
const deletingBackup = ref<string | null>(null);
const updatingModule = ref<string | null>(null);
const storageLocation = computed(() => {
    const root = props.backup.mount_root.replace(/\/+$/, "");
    const directory = form.backup.directory.replace(/^\/+|\/+$/g, "");

    return directory ? `${root}/${directory}` : root;
});

const logoPreview = ref(props.settings.system.logo_url ?? miniSvg);
const logoObjectUrl = ref<string | null>(null);

const updateLogo = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    if (logoObjectUrl.value) {
        URL.revokeObjectURL(logoObjectUrl.value);
        logoObjectUrl.value = null;
    }

    form.system.logo = file;
    form.system.remove_logo = false;

    if (file) {
        logoObjectUrl.value = URL.createObjectURL(file);
        logoPreview.value = logoObjectUrl.value;
    } else {
        logoPreview.value = props.settings.system.logo_url ?? miniSvg;
    }
};

const removeLogo = (): void => {
    if (logoObjectUrl.value) {
        URL.revokeObjectURL(logoObjectUrl.value);
        logoObjectUrl.value = null;
    }

    form.system.logo = null;
    form.system.remove_logo = true;
    logoPreview.value = miniSvg;
};

onBeforeUnmount(() => {
    if (logoObjectUrl.value) {
        URL.revokeObjectURL(logoObjectUrl.value);
    }
});

const submit = () => {
    form.put("/settings", { forceFormData: true });
};

const updateModuleAvailability = (module: NumberingModule): void => {
    moduleAvailabilityForm.is_active = !module.is_active;
    updatingModule.value = module.code;

    moduleAvailabilityForm.patch(
        route("settings.modules.update", {
            numberingModule: module.code,
        }),
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                updatingModule.value = null;
            },
        },
    );
};

const createNumberingModule = (): void => {
    moduleCreateForm.post(route("settings.modules.store"), {
        preserveScroll: true,
        onSuccess: () => {
            moduleCreateForm.reset();
        },
    });
};

const createBackup = (): void => {
    backupForm.post("/settings/backup", {
        preserveScroll: true,
    });
};

const deleteBackup = (filename: string): void => {
    if (!window.confirm(`Delete backup "${filename}"?`)) {
        return;
    }

    deletingBackup.value = filename;
    router.delete(`/settings/backup/${encodeURIComponent(filename)}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingBackup.value = null;
        },
    });
};

const formatBackupSize = (size: number): string => {
    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const formatBackupDate = (date: string): string =>
    new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(date));
</script>

<template>
    <Head :title="`Settings | ${form.system.name}`" />

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

            <nav
                class="flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-800"
                role="tablist"
                aria-label="Settings sections"
            >
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'identity'"
                    class="inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition"
                    :class="
                        activeTab === 'identity'
                            ? 'border-gray-900 text-gray-900 dark:border-white dark:text-white'
                            : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
                    "
                    @click="activeTab = 'identity'"
                >
                    <Building2 class="h-4 w-4" />
                    Company & System
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'backup'"
                    class="inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition"
                    :class="
                        activeTab === 'backup'
                            ? 'border-gray-900 text-gray-900 dark:border-white dark:text-white'
                            : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
                    "
                    @click="activeTab = 'backup'"
                >
                    <CloudDownload class="h-4 w-4" />
                    Database Backup
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'modules'"
                    class="inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition"
                    :class="
                        activeTab === 'modules'
                            ? 'border-gray-900 text-gray-900 dark:border-white dark:text-white'
                            : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
                    "
                    @click="activeTab = 'modules'"
                >
                    <Blocks class="h-4 w-4" />
                    Modules
                </button>
            </nav>

            <!-- Application Identity -->
            <section
                v-if="activeTab === 'identity'"
                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
            >
                <div
                    class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <ImagePlus class="h-5 w-5" />
                        </div>

                        <div>
                            <h2
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                Application Identity
                            </h2>

                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Set the application name and logo shown in the
                                sign-in page and navigation.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 p-6 md:grid-cols-[minmax(0,1fr)_auto]">
                    <div class="space-y-5">
                        <div>
                            <label
                                for="system-name"
                                class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                System Name
                            </label>

                            <input
                                id="system-name"
                                v-model="form.system.name"
                                type="text"
                                maxlength="150"
                                class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 text-sm text-gray-900 outline-none focus:border-gray-400 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-white dark:focus:border-gray-700 dark:focus:ring-gray-800"
                                :class="{
                                    'border-red-500':
                                        form.errors['system.name'],
                                }"
                            />

                            <p
                                v-if="form.errors['system.name']"
                                class="mt-2 text-xs text-red-600"
                            >
                                {{ form.errors["system.name"] }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="system-logo"
                                class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                System Logo
                            </label>

                            <input
                                id="system-logo"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium dark:text-gray-300 dark:file:bg-gray-800"
                                @change="updateLogo"
                            />

                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                PNG, JPG, or WebP; maximum 2 MB. SVG files are
                                not accepted.
                            </p>

                            <p
                                v-if="form.errors['system.logo']"
                                class="mt-2 text-xs text-red-600"
                            >
                                {{ form.errors["system.logo"] }}
                            </p>

                            <button
                                v-if="
                                    props.settings.system.logo_url ||
                                    form.system.logo
                                "
                                type="button"
                                class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400"
                                @click="removeLogo"
                            >
                                <Trash2 class="h-4 w-4" />
                                Remove logo
                            </button>
                        </div>
                    </div>

                    <div
                        class="flex min-h-36 min-w-36 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-950"
                    >
                        <img
                            :src="logoPreview"
                            alt="System logo preview"
                            class="max-h-28 max-w-36 object-contain"
                        />
                    </div>
                </div>
            </section>

            <!-- Company -->
            <section
                v-if="activeTab === 'identity'"
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
                v-if="activeTab === 'identity'"
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

            <!-- Database Backup -->
            <section
                v-if="activeTab === 'backup'"
                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
            >
                <div
                    class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <CloudDownload class="h-5 w-5" />
                        </div>

                        <div>
                            <h2
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                Database Backup
                            </h2>

                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Store and manage manual and automatic database
                                backups in the configured volume.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-5 p-6">
                    <div
                        class="flex flex-col gap-4 rounded-xl bg-gray-50 p-4 dark:bg-gray-950 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="space-y-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    Create backup now
                                </p>

                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Driver: {{ backup.driver }}. Manual and
                                    automatic backups share the same 7-file
                                    retention limit.
                                </p>
                            </div>
                        </div>

                        <div>
                            <button
                                type="button"
                                :disabled="
                                    !backup.available ||
                                    !backup.storage_available ||
                                    backupForm.processing
                                "
                                class="inline-flex h-10 items-center gap-2 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
                                @click="createBackup"
                            >
                                <LoaderCircle
                                    v-if="backupForm.processing"
                                    class="h-4 w-4 animate-spin"
                                />
                                <CloudDownload v-else class="h-4 w-4" />
                                {{
                                    backupForm.processing
                                        ? "Creating backup..."
                                        : "Create backup"
                                }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="backupForm.processing"
                        class="space-y-2"
                        role="status"
                        aria-live="polite"
                    >
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Backup is being created. This may take several
                            minutes; please keep this page open.
                        </p>
                        <div
                            class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800"
                            role="progressbar"
                            aria-label="Database backup in progress"
                            aria-valuetext="Backup is being created"
                        >
                            <div class="h-full w-1/3 animate-pulse rounded-full bg-gray-700 dark:bg-gray-300" />
                        </div>
                    </div>

                    <p
                        v-if="!backup.available"
                        class="text-sm text-amber-700 dark:text-amber-300"
                    >
                        Backup is unavailable. The application requires a
                        PostgreSQL database and the pg_dump utility.
                    </p>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        The host volume is mounted by the operator. Choose a
                        subfolder inside that volume; absolute paths and paths
                        outside the mount are not allowed.
                    </p>

                    <p
                        v-if="!backup.storage_available"
                        class="text-sm text-amber-700 dark:text-amber-300"
                    >
                        The mounted backup directory is missing or not writable.
                        Configure the volume and permissions before creating
                        backups.
                    </p>

                    <div class="grid gap-5 border-t border-gray-200 pt-5 dark:border-gray-800 md:grid-cols-3">
                        <label
                            class="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            <input
                                v-model="form.backup.enabled"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-500 dark:border-gray-700 dark:bg-gray-950"
                            />
                            Enable automatic backup
                        </label>

                        <div>
                            <label
                                for="backup-time"
                                class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Daily backup time
                            </label>

                            <input
                                id="backup-time"
                                v-model="form.backup.time"
                                type="time"
                                class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 text-sm text-gray-900 outline-none focus:border-gray-400 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-white dark:focus:border-gray-700 dark:focus:ring-gray-800"
                            />

                            <p
                                v-if="form.errors['backup.time']"
                                class="mt-2 text-xs text-red-600"
                            >
                                {{ form.errors["backup.time"] }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="backup-directory"
                                class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Backup folder inside mounted volume
                            </label>

                            <input
                                id="backup-directory"
                                v-model="form.backup.directory"
                                type="text"
                                maxlength="255"
                                placeholder="backups/scheduled"
                                class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 text-sm text-gray-900 outline-none focus:border-gray-400 focus:ring-3 focus:ring-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-white dark:focus:border-gray-700 dark:focus:ring-gray-800"
                            />

                            <p
                                v-if="form.errors['backup.directory']"
                                class="mt-2 text-xs text-red-600"
                            >
                                {{ form.errors["backup.directory"] }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-start gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                    >
                        <HardDrive class="mt-0.5 h-5 w-5 shrink-0 text-gray-500" />
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                Backup root location
                            </p>
                            <code class="block break-all text-xs text-gray-600 dark:text-gray-400">
                                {{ storageLocation }}
                            </code>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                At most 7 files are kept. When a new backup is
                                created, the oldest backup is removed.
                            </p>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Time follows the configured application timezone.
                        Automatic backups run only while the Laravel scheduler
                        is running. Backups exclude uploaded files, environment
                        secrets, and PostgreSQL cluster roles. Last successful backup:
                        {{ backup.last_success_at ?? "Not yet created" }}.
                    </p>

                    <div class="space-y-3 border-t border-gray-200 pt-5 dark:border-gray-800">
                        <div class="flex items-center gap-2">
                            <Database class="h-5 w-5 text-gray-500" />
                            <h3 class="font-semibold text-gray-900 dark:text-white">
                                Saved database backups
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                ({{ backup.backups.length }}/7)
                            </span>
                        </div>

                        <div
                            v-if="backup.backups.length === 0"
                            class="rounded-lg bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 dark:bg-gray-950 dark:text-gray-400"
                        >
                            No database backups have been created yet.
                        </div>

                        <div
                            v-else
                            class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800"
                        >
                            <table class="w-full min-w-[640px] divide-y divide-gray-200 text-left text-sm dark:divide-gray-800">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-950 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3 font-medium">Created</th>
                                        <th class="px-4 py-3 font-medium">File</th>
                                        <th class="px-4 py-3 font-medium">Size</th>
                                        <th class="px-4 py-3 text-right font-medium">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <tr
                                        v-for="file in backup.backups"
                                        :key="file.filename"
                                    >
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ formatBackupDate(file.created_at) }}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">
                                            {{ file.filename }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ formatBackupSize(file.size_bytes) }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-950"
                                                :disabled="
                                                    backupForm.processing ||
                                                    deletingBackup === file.filename
                                                "
                                                @click="deleteBackup(file.filename)"
                                            >
                                                <LoaderCircle
                                                    v-if="deletingBackup === file.filename"
                                                    class="h-4 w-4 animate-spin"
                                                />
                                                <Trash2 v-else class="h-4 w-4" />
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="activeTab === 'modules'"
                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <Blocks class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="font-semibold text-gray-900 dark:text-white">
                                Modul Sistem
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Kelola daftar modul yang tersedia di aplikasi dan status aktifnya.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 p-6">
                    <form
                        class="space-y-5 rounded-xl border border-gray-200 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-gray-950/50"
                        @submit.prevent="createNumberingModule"
                    >
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">
                                Tambah Modul
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Daftarkan modul sistem agar dapat dipilih pada pengaturan Document Numbering.
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label for="module-code" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Kode Modul
                                </label>
                                <input
                                    id="module-code"
                                    v-model="moduleCreateForm.code"
                                    type="text"
                                    maxlength="50"
                                    autocomplete="off"
                                    placeholder="contoh: SALES_ORDER"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm uppercase text-gray-900 outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    @input="moduleCreateForm.code = moduleCreateForm.code.toUpperCase()"
                                />
                                <p v-if="moduleCreateForm.errors.code" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ moduleCreateForm.errors.code }}
                                </p>
                            </div>
                            <div>
                                <label for="module-name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Nama Modul
                                </label>
                                <input
                                    id="module-name"
                                    v-model="moduleCreateForm.name"
                                    type="text"
                                    maxlength="150"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                />
                                <p v-if="moduleCreateForm.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ moduleCreateForm.errors.name }}
                                </p>
                            </div>
                            <div>
                                <label for="module-category" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Kategori
                                </label>
                                <input
                                    id="module-category"
                                    v-model="moduleCreateForm.category"
                                    type="text"
                                    maxlength="100"
                                    placeholder="contoh: Procurement"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                />
                                <p v-if="moduleCreateForm.errors.category" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ moduleCreateForm.errors.category }}
                                </p>
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-gray-200 pt-4 dark:border-gray-800">
                            <button
                                type="submit"
                                :disabled="moduleCreateForm.processing"
                                class="inline-flex h-10 items-center gap-2 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
                            >
                                <LoaderCircle v-if="moduleCreateForm.processing" class="h-4 w-4 animate-spin" />
                                <span>{{ moduleCreateForm.processing ? "Menyimpan..." : "Tambah Modul" }}</span>
                            </button>
                        </div>
                    </form>

                    <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-4 text-sm text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200">
                        Pengaturan pola, sequence, dan reset nomor tetap dilakukan di menu Document Numbering. Hanya modul aktif dari daftar ini yang dapat dipilih saat membuat aturan penomoran.
                    </div>

                    <div
                        v-if="modules.length === 0"
                        class="rounded-lg bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 dark:bg-gray-950 dark:text-gray-400"
                    >
                        Belum ada modul sistem yang terdaftar.
                    </div>

                    <div
                        v-for="module in modules"
                        :key="module.code"
                        class="flex flex-col gap-4 rounded-xl border border-gray-200 p-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    {{ module.name }}
                                </span>
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    {{ module.category }}
                                </span>
                            </div>
                            <code class="text-xs text-gray-500 dark:text-gray-400">
                                {{ module.code }}
                            </code>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ module.is_active ? "Tersedia untuk Document Numbering" : "Tidak tersedia untuk Document Numbering" }}
                            </span>
                        </div>

                        <button
                            type="button"
                            role="switch"
                            :aria-checked="module.is_active"
                            :aria-label="`${module.is_active ? 'Nonaktifkan' : 'Aktifkan'} modul ${module.name}`"
                            :disabled="moduleAvailabilityForm.processing"
                            class="inline-flex min-h-10 items-center gap-3 self-start rounded-lg px-2 py-1 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-60 sm:self-auto"
                            @click="updateModuleAvailability(module)"
                        >
                            <span
                                class="relative h-6 w-11 rounded-full transition"
                                :class="module.is_active ? 'bg-emerald-600' : 'bg-gray-300 dark:bg-gray-700'"
                            >
                                <span
                                    class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition"
                                    :class="module.is_active ? 'left-5' : 'left-0.5'"
                                />
                            </span>
                            <span
                                :class="module.is_active ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400'"
                            >
                                {{
                                    updatingModule === module.code
                                        ? "Menyimpan..."
                                        : module.is_active
                                          ? "Aktif"
                                          : "Nonaktif"
                                }}
                            </span>
                        </button>
                    </div>

                    <p
                        v-if="moduleAvailabilityForm.errors.is_active"
                        class="text-sm text-red-600 dark:text-red-400"
                    >
                        {{ moduleAvailabilityForm.errors.is_active }}
                    </p>
                </div>
            </section>

            <!-- Save -->
            <div
                v-if="activeTab !== 'modules'"
                class="flex items-center justify-end gap-4"
            >
                <span
                    v-if="form.recentlySuccessful"
                    class="text-sm text-green-600"
                >
                    Settings were saved successfully.
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
