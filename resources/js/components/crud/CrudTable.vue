<script setup lang="ts">
import { computed } from "vue";

interface CrudColumn {
    key: string;
    label: string;
    class?: string;
}

const props = withDefaults(
    defineProps<{
        columns: CrudColumn[];
        rows: any[];
        rowKey?: string;
        emptyMessage?: string;
    }>(),
    {
        rowKey: "id",
        emptyMessage: "Tidak ada data.",
    },
);

const hasRows = computed(() => props.rows.length > 0);
</script>

<template>
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead
                class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400"
            >
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        class="px-5 py-3 font-medium"
                        :class="column.class"
                    >
                        {{ column.label }}
                    </th>

                    <!-- 1. Tambahkan w-1 whitespace-nowrap agar lebar kolom pas dengan konten -->
                    <th
                        v-if="$slots.actions"
                        class="w-1 whitespace-nowrap px-5 py-3 text-right font-medium"
                    >
                        Actions
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                <tr
                    v-for="(row, index) in rows"
                    :key="String(row[rowKey] ?? index)"
                    class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                >
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        class="px-5 py-4 text-gray-700 dark:text-gray-300"
                        :class="column.class"
                    >
                        <slot
                            :name="`cell-${column.key}`"
                            :row="row"
                            :value="row[column.key]"
                        >
                            {{ row[column.key] ?? "-" }}
                        </slot>
                    </td>

                    <!-- 2. Sesuaikan w-1 whitespace-nowrap di sini juga -->
                    <td
                        v-if="$slots.actions"
                        class="w-1 whitespace-nowrap px-5 py-4 text-right"
                    >
                        <slot name="actions" :row="row"></slot>
                    </td>
                </tr>

                <tr v-if="!hasRows">
                    <td
                        :colspan="columns.length + ($slots.actions ? 1 : 0)"
                        class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ emptyMessage }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
