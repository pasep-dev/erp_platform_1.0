<script setup lang="ts">
import { Link, useForm } from "@inertiajs/vue3";
import { computed, watch } from "vue";
import { route } from "ziggy-js";

import Header from "@/components/content/Header.vue";
import AppLayout from "@/layout/AppLayout.vue";

defineOptions({ layout: AppLayout });

interface AccessTarget {
    id: number;
    type: "role" | "user";
    type_label: string;
    name: string;
    identifier: string | null;
}

interface PermissionItem {
    id: number;
    name: string;
    label: string;
}

interface PermissionGroup {
    name: string;
    label: string;
    permissions: PermissionItem[];
}

const props = defineProps<{
    target: AccessTarget;
    canEdit: boolean;
    permissionGroups: PermissionGroup[];
    assignedPermissionIds: number[];
}>();

const form = useForm({
    permission_ids: [...props.assignedPermissionIds],
});

const assignedPermissionCount = computed(() => form.permission_ids.length);

watch(
    () => props.assignedPermissionIds,
    (permissionIds) => {
        form.permission_ids = [...permissionIds];
    },
    { immediate: true },
);

function isGroupSelected(group: PermissionGroup): boolean {
    return group.permissions.every((permission) =>
        form.permission_ids.includes(permission.id),
    );
}

function toggleGroup(group: PermissionGroup): void {
    const groupPermissionIds = group.permissions.map((permission) => permission.id);
    const remainingPermissionIds = form.permission_ids.filter(
        (permissionId) => !groupPermissionIds.includes(permissionId),
    );

    form.permission_ids = isGroupSelected(group)
        ? remainingPermissionIds
        : [...new Set([...remainingPermissionIds, ...groupPermissionIds])];
}

function submit(): void {
    form.put(
        route("administrator.access-control.update", {
            type: props.target.type,
            target: props.target.id,
        }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="space-y-6">
        <Header
            :title="`Access Control · ${target.type_label}`"
            :description="`Atur permission untuk ${target.name}.`"
        >
            <template #actions>
                <Link
                    :href="
                        route('administrator.access-control.index', {
                            type: target.type,
                        })
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Kembali ke Access Control
                </Link>
            </template>
        </Header>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ target.type_label }}
                    </p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ target.name }}
                    </h2>
                    <p
                        v-if="target.identifier"
                        class="mt-0.5 text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ target.identifier }}
                    </p>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ assignedPermissionCount }} direct permissions
                </div>
            </div>
        </div>

        <div
            v-if="!canEdit"
            class="rounded-xl border border-amber-100 bg-amber-50/70 p-5 dark:border-amber-900/60 dark:bg-amber-950/30"
        >
            <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                Hak akses dilindungi
            </h2>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                Permission Administrator dikelola oleh sistem dan tidak dapat
                diubah dari halaman ini.
            </p>
        </div>

        <div
            v-else-if="target.type === 'user'"
            class="rounded-xl border border-blue-100 bg-blue-50/70 p-5 dark:border-blue-900/60 dark:bg-blue-950/30"
        >
            <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-200">
                Permission khusus pengguna
            </h2>
            <p class="mt-1 text-sm text-blue-800 dark:text-blue-300">
                Permission ini berlaku langsung pada akun tersebut. Permission
                dari role yang dimiliki tetap berlaku dan tidak dihapus.
            </p>
        </div>

        <template v-if="canEdit">
            <div class="grid gap-4 xl:grid-cols-2">
                <section
                    v-for="group in permissionGroups"
                    :key="group.name"
                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800"
                    >
                        <div>
                            <h2 class="font-semibold text-gray-900 dark:text-white">
                                {{ group.label }}
                            </h2>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ group.permissions.length }} permissions
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800 dark:border-gray-700 dark:text-gray-300 dark:hover:border-brand-700 dark:hover:bg-brand-500/10 dark:hover:text-brand-300"
                            @click="toggleGroup(group)"
                        >
                            {{ isGroupSelected(group) ? "Clear all" : "Select all" }}
                        </button>
                    </div>

                    <div class="grid gap-2 p-4 sm:grid-cols-2">
                        <label
                            v-for="permission in group.permissions"
                            :key="permission.id"
                            class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-2 text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                        >
                            <input
                                v-model="form.permission_ids"
                                type="checkbox"
                                :value="permission.id"
                                class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900"
                            />
                            <span class="break-all">{{ permission.label }}</span>
                        </label>
                    </div>
                </section>
            </div>

            <p
                v-if="form.errors.permission_ids"
                class="text-sm text-red-600 dark:text-red-400"
            >
                {{ form.errors.permission_ids }}
            </p>

            <div class="flex justify-end">
                <button
                    type="button"
                    class="rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ form.processing ? "Menyimpan..." : "Simpan Hak Akses" }}
                </button>
            </div>
        </template>
    </div>
</template>
