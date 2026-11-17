<script setup lang="ts">
import { Link, router, useForm } from "@inertiajs/vue3";
import { computed, watch } from "vue";
import { route } from "ziggy-js";

import Header from "@/components/content/Header.vue";
import AppLayout from "@/layout/AppLayout.vue";
import { menuGroups } from "@/config/menu";
import type { MenuItem } from "@/types/menu";

defineOptions({ layout: AppLayout });

interface MenuTarget {
    id: number;
    type: "role" | "user";
    type_label: string;
    name: string;
    identifier: string | null;
}

interface MenuSection {
    label: string;
    items: MenuItem[];
}

interface FlatMenuItem {
    item: MenuItem;
    label: string;
    depth: number;
}

const props = defineProps<{
    target: MenuTarget;
    canEdit: boolean;
    hasCustomSettings: boolean;
    selectedMenuKeys: string[] | null;
    inheritsRoleSettings: boolean;
}>();

function collectKeys(item: MenuItem): string[] {
    return [
        item.id,
        ...(item.children?.flatMap(collectKeys) ?? []),
    ];
}

const allMenuKeys = [...new Set(menuGroups.flatMap((group) =>
    group.items.flatMap(collectKeys),
))];

const form = useForm({
    menu_keys: props.selectedMenuKeys ?? [...allMenuKeys],
});

watch(
    () => props.selectedMenuKeys,
    (menuKeys) => {
        form.menu_keys = menuKeys ?? [...allMenuKeys];
    },
);

const selectedCount = computed(() => form.menu_keys.length);
const sections = computed<MenuSection[]>(() => menuGroups);

function flatten(item: MenuItem, parentLabel: string, depth: number): FlatMenuItem[] {
    const label = parentLabel ? `${parentLabel} / ${item.label}` : item.label;

    return [
        { item, label, depth },
        ...(item.children?.flatMap((child) => flatten(child, label, depth + 1)) ?? []),
    ];
}

function isSelected(item: MenuItem): boolean {
    return collectKeys(item).every((key) => form.menu_keys.includes(key));
}

function isPartiallySelected(item: MenuItem): boolean {
    const keys = collectKeys(item);
    const selectedKeys = keys.filter((key) => form.menu_keys.includes(key));

    return selectedKeys.length > 0 && selectedKeys.length < keys.length;
}

function toggle(item: MenuItem): void {
    const keys = collectKeys(item);
    const selectedKeys = new Set(form.menu_keys);

    if (keys.every((key) => selectedKeys.has(key))) {
        keys.forEach((key) => selectedKeys.delete(key));
    } else {
        keys.forEach((key) => selectedKeys.add(key));
    }

    form.menu_keys = [...selectedKeys];
}

function submit(): void {
    form.put(
        route("administrator.menu-control.update", {
            type: props.target.type,
            target: props.target.id,
        }),
        { preserveScroll: true },
    );
}

function resetToDefault(): void {
    router.put(
        route("administrator.menu-control.update", {
            type: props.target.type,
            target: props.target.id,
        }),
        { reset_to_default: true },
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="space-y-6">
        <Header
            :title="`Menu Control · ${target.type_label}`"
            :description="`Pilih item sidebar yang terlihat untuk ${target.name}.`"
        >
            <template #actions>
                <Link
                    :href="route('administrator.menu-control.index', { type: target.type })"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Kembali
                </Link>
            </template>
        </Header>

        <section
            class="rounded-xl border border-blue-100 bg-blue-50/70 p-5 dark:border-blue-900/60 dark:bg-blue-950/30"
        >
            <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-200">
                {{ target.type === "role" ? "Visibilitas untuk role" : "Visibilitas untuk user" }}
            </h2>
            <p class="mt-1 text-sm text-blue-800 dark:text-blue-300">
                <template v-if="target.type === 'user'">
                    Pengaturan user menjadi daftar menu khusus dan menggantikan
                    pengaturan role. Jika dikosongkan, daftar menu role yang
                    dikonfigurasi akan digabung.
                </template>
                <template v-else>
                    Pengaturan ini berlaku untuk semua user dengan role tersebut.
                    Jika user memiliki pengaturan sendiri, pengaturan user yang
                    digunakan.
                </template>
                Permission backend tetap menentukan apakah halaman dapat dibuka.
            </p>
            <p
                v-if="inheritsRoleSettings"
                class="mt-2 text-sm font-medium text-blue-900 dark:text-blue-200"
            >
                Daftar di bawah mengikuti konfigurasi role yang dimiliki user.
            </p>
        </section>

        <div
            v-if="!canEdit"
            class="rounded-xl border border-amber-100 bg-amber-50/70 p-5 dark:border-amber-900/60 dark:bg-amber-950/30"
        >
            <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                Pengaturan dilindungi
            </h2>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                Administrator selalu mendapatkan seluruh menu dan tidak dapat
                dibatasi dari halaman ini.
            </p>
        </div>

        <template v-else>
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-900 dark:text-white">
                            Item Sidebar
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ selectedCount }} menu dipilih
                        </p>
                    </div>
                    <button
                        v-if="hasCustomSettings"
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="resetToDefault"
                    >
                        Gunakan Role / Default
                    </button>
                </div>

                <div class="grid gap-5 xl:grid-cols-2">
                    <section
                        v-for="section in sections"
                        :key="section.label"
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800"
                    >
                        <h3
                            class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                        >
                            {{ section.label }}
                        </h3>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            <label
                                v-for="entry in section.items.flatMap((item) => flatten(item, '', 0))"
                                :key="entry.item.id"
                                class="flex cursor-pointer items-center gap-3 px-4 py-3 text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                                :style="{ paddingLeft: `${16 + entry.depth * 20}px` }"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isSelected(entry.item)"
                                    :indeterminate="isPartiallySelected(entry.item)"
                                    class="rounded border-gray-300 text-brand-700 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900"
                                    @change="toggle(entry.item)"
                                />
                                <span>{{ entry.item.label }}</span>
                                <span
                                    v-if="entry.item.children?.length"
                                    class="ml-auto text-xs text-gray-400"
                                >
                                    {{ entry.item.children.length }} submenu
                                </span>
                            </label>
                        </div>
                    </section>
                </div>

                <p
                    v-if="form.errors.menu_keys"
                    class="mt-4 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.menu_keys }}
                </p>

                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        class="rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        {{ form.processing ? "Menyimpan..." : "Simpan Menu" }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
