<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Button from "primevue/button";
import DataTable from "primevue/datatable";

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Pagination {
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    value: unknown[];
    pagination: Pagination;
    dataKey?: string;
}

withDefaults(defineProps<Props>(), {
    dataKey: "id",
});

function paginationLabel(label: string): string {
    return label
        .replace(/&laquo;/g, "")
        .replace(/&raquo;/g, "")
        .trim();
}

function paginationIcon(label: string): string | undefined {
    if (label.includes("&laquo;")) {
        return "pi pi-angle-left";
    }

    if (label.includes("&raquo;")) {
        return "pi pi-angle-right";
    }

    return undefined;
}
</script>

<template>
    <div>
        <!-- Toolbar -->
        <div
            class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="w-full lg:max-w-2xl">
                <slot name="search" />
            </div>

            <div class="text-sm text-muted-color">
                Total:
                <span class="font-semibold text-color">
                    {{ pagination.total }}
                </span>
            </div>
        </div>

        <!-- Data Table -->
        <DataTable
            :value="value"
            :dataKey="dataKey"
            stripedRows
            showGridlines
            scrollable
            responsiveLayout="scroll"
            class="text-sm"
        >
            <slot></slot>
            <slot name="columns"></slot>

            <template #empty>
                <slot name="empty">
                    <div
                        class="flex flex-col items-center justify-center py-10 text-center"
                    >
                        <i class="pi pi-inbox mb-3 text-3xl text-muted-color" />

                        <p class="font-medium text-color">Belum ada data.</p>
                    </div>
                </slot>
            </template>
        </DataTable>

        <!-- Pagination -->
        <div
            v-if="pagination.last_page > 1"
            class="mt-5 flex flex-col gap-3 border-t border-surface pt-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-muted-color">
                Menampilkan
                <span class="font-medium text-color">
                    {{ pagination.from }}
                </span>
                –
                <span class="font-medium text-color">
                    {{ pagination.to }}
                </span>
                dari
                <span class="font-medium text-color">
                    {{ pagination.total }}
                </span>
                data
            </p>

            <div class="flex flex-wrap gap-1">
                <template v-for="link in pagination.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-scroll>
                        <Button
                            :label="paginationLabel(link.label)"
                            :icon="paginationIcon(link.label)"
                            :severity="link.active ? 'primary' : 'secondary'"
                            :outlined="!link.active"
                            size="small"
                        />
                    </Link>

                    <Button
                        v-else
                        :label="paginationLabel(link.label)"
                        disabled
                        severity="secondary"
                        outlined
                        size="small"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
