<script setup lang="ts">
import { router, Link } from "@inertiajs/vue3";
import { KeyRound } from "lucide-vue-next";
import { computed, ref } from "vue";
import { route } from "ziggy-js";

import AppPagination from "@/components/common/AppPagination.vue";
import AppSelect from "@/components/common/AppSelect.vue";
import SearchFilterBar from "@/components/common/SearchFilterBar.vue";
import Header from "@/components/content/Header.vue";
import CrudTable from "@/components/crud/CrudTable.vue";
import AppLayout from "@/layout/AppLayout.vue";

defineOptions({ layout: AppLayout });

interface AccessTarget {
    id: number;
    name: string;
    type: "role" | "user";
    identifier: string | null;
    roles?: string[];
    permissions_count: number;
    is_system: boolean;
    is_editable: boolean;
}

interface PaginatedTargets {
    data: AccessTarget[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

const props = defineProps<{
    targets: PaginatedTargets;
    filters: {
        type: "role" | "user";
        search: string;
        per_page: number;
    };
}>();

const search = ref(props.filters.search);
const targetType = ref(props.filters.type);
const perPage = ref(props.filters.per_page);
const typeOptions = [
    { label: "Role", value: "role" },
    { label: "User", value: "user" },
];

const columns = computed(() => [
    { key: "target", label: targetType.value === "role" ? "Role" : "User" },
    {
        key: "permissions_count",
        label:
            targetType.value === "role"
                ? "Role Permissions"
                : "Direct User Permissions",
    },
    { key: "configured", label: "Configuration" },
]);

function visitIndex(): void {
    router.get(
        route("administrator.access-control.index"),
        {
            type: targetType.value,
            search: search.value || undefined,
            per_page: perPage.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function clearSearch(): void {
    search.value = "";
    perPage.value = 15;
    visitIndex();
}
</script>

<template>
    <div class="space-y-6">
        <Header
            title="Access Control"
            description="Review and configure permissions assigned directly to roles or individual users."
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-800"
            >
                <div class="flex flex-1 flex-wrap items-center gap-3">
                    <AppSelect
                        v-model="targetType"
                        :options="typeOptions"
                        class="w-full sm:w-36"
                        @change="visitIndex"
                    />
                    <SearchFilterBar
                        v-model="search"
                        :placeholder="
                            targetType === 'role'
                                ? 'Search role...'
                                : 'Search user or email...'
                        "
                        class="w-full sm:w-72"
                        @search="visitIndex"
                        @clear="clearSearch"
                    />
                </div>

                <div
                    class="whitespace-nowrap rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-500 dark:bg-gray-900 dark:text-gray-400"
                >
                    Total {{ targets.total }} {{ targetType === "role" ? "Roles" : "Users" }}
                </div>
            </div>

            <CrudTable
                :columns="columns"
                :rows="targets.data"
                :empty-message="
                    targetType === 'role'
                        ? 'Tidak ada role.'
                        : 'Tidak ada akun pengguna.'
                "
            >
                <template #cell-target="{ row }">
                    <div class="flex flex-col">
                        <span class="font-semibold text-gray-900 dark:text-white">
                            {{ row.name }}
                        </span>
                        <span
                            v-if="row.identifier"
                            class="text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ row.identifier }}
                        </span>
                        <span
                            v-if="row.type === 'user' && row.roles?.length"
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Role: {{ row.roles.join(", ") }}
                        </span>
                        <span
                            v-if="row.is_system"
                            class="mt-1 w-fit rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                        >
                            System protected
                        </span>
                    </div>
                </template>

                <template #cell-permissions_count="{ row }">
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        {{ row.permissions_count }}
                    </span>
                </template>

                <template #cell-configured="{ row }">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium"
                        :class="
                            row.permissions_count > 0
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                        "
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="
                                row.permissions_count > 0
                                    ? 'bg-emerald-500'
                                    : 'bg-gray-400'
                            "
                        ></span>
                        {{ row.permissions_count > 0 ? "Configured" : "Not configured" }}
                    </span>
                </template>

                <template #actions="{ row }">
                    <Link
                        v-if="row.is_editable"
                        :href="
                            route('administrator.access-control.edit', {
                                type: row.type,
                                target: row.id,
                            })
                        "
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:text-gray-300 dark:hover:border-brand-700 dark:hover:bg-brand-500/10 dark:hover:text-brand-300"
                        :title="`Atur hak akses ${row.name}`"
                        :aria-label="`Atur hak akses ${row.name}`"
                    >
                        <KeyRound class="h-4 w-4" aria-hidden="true" />
                    </Link>
                    <span
                        v-else
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500"
                        title="Hak akses Administrator dilindungi"
                        aria-label="Hak akses dilindungi"
                    >
                        <KeyRound class="h-4 w-4" aria-hidden="true" />
                    </span>
                </template>
            </CrudTable>

            <AppPagination
                v-model:per-page="perPage"
                :meta="targets"
                @change="visitIndex"
            />
        </div>

        <section
            class="rounded-xl border border-blue-100 bg-blue-50/70 p-5 dark:border-blue-900/60 dark:bg-blue-950/30"
        >
            <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-200">
                Role vs user permissions
            </h2>
            <p class="mt-1 text-sm text-blue-800 dark:text-blue-300">
                Role permissions apply to every account with that role. User
                permissions are direct grants for the selected account and do not
                replace permissions inherited from its roles.
            </p>
        </section>
    </div>
</template>
