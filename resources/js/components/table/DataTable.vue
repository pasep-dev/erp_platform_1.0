<script setup lang="ts">
import DataTable from "primevue/datatable";

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Pagination {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

withDefaults(
    defineProps<{
        value: unknown[];
        pagination?: Pagination | null;
        dataKey?: string;
        loading?: boolean;
        stripedRows?: boolean;
        showGridlines?: boolean;
        scrollable?: boolean;
        emptyMessage?: string;
    }>(),
    {
        pagination: null,
        dataKey: "id",
        loading: false,
        stripedRows: true,
        showGridlines: false,
        scrollable: true,
        emptyMessage: "Tidak ada data.",
    },
);
</script>

<template>
    <div class="w-full">
        <!-- Toolbar -->
        <div
            v-if="$slots.toolbar"
            class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="w-full">
                <slot name="toolbar" />
            </div>
        </div>

        <!-- Table -->
        <DataTable
            :value="value"
            :data-key="dataKey"
            :loading="loading"
            :striped-rows="stripedRows"
            :show-gridlines="showGridlines"
            :scrollable="scrollable"
            class="text-sm"
        >
            <slot />

            <template #empty>
                <slot name="empty">
                    <div
                        class="flex flex-col items-center justify-center py-10 text-center"
                    >
                        <i class="pi pi-inbox mb-3 text-3xl text-muted-color" />
                        <p class="font-medium text-color">
                            {{ emptyMessage }}
                        </p>
                    </div>
                </slot>
            </template>

            <template #loading>
                <slot name="loading">
                    <div
                        class="flex items-center justify-center py-10 text-muted-color"
                    >
                        <i class="pi pi-spin pi-spinner mr-2" />
                        Memuat data...
                    </div>
                </slot>
            </template>
        </DataTable>

        <!-- Pagination -->
        <div
            v-if="pagination && pagination.last_page > 1"
            class="mt-5 flex flex-col gap-3 border-t border-surface pt-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-muted-color">
                Menampilkan
                <span class="font-medium text-color">
                    {{ pagination.from ?? 0 }}
                </span>
                –
                <span class="font-medium text-color">
                    {{ pagination.to ?? 0 }}
                </span>
                dari
                <span class="font-medium text-color">
                    {{ pagination.total }}
                </span>
                data
            </p>

            <slot name="pagination" :pagination="pagination" />
        </div>
    </div>
</template>
