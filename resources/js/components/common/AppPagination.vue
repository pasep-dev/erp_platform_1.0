<script setup lang="ts">
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import Select from "primevue/select";
import { ChevronLeft, ChevronRight } from "lucide-vue-next";

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface MetaPagination {
    total: number;
    links?: PaginationLink[];
}

interface Props {
    meta: MetaPagination;
    perPageOptions?: Array<{ label: string; value: number }>;
    clientPageCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
    perPageOptions: () => [
        { label: "5", value: 5 },
        { label: "15", value: 15 },
        { label: "25", value: 25 },
        { label: "50", value: 50 },
        { label: "100", value: 100 },
    ],
    clientPageCount: undefined,
});

const perPage = defineModel<number>("perPage", { default: 5 });
const currentPage = defineModel<number>("page", { default: 1 });

const emit = defineEmits<{
    (e: "change"): void;
    (e: "page-change"): void;
}>();

const clientPages = computed(() =>
    Array.from({ length: props.clientPageCount ?? 0 }, (_, index) => index + 1),
);

function setClientPage(page: number): void {
    currentPage.value = page;
    emit("page-change");
}

function onChangePerPage() {
    emit("change");
}
</script>

<template>
    <div
        v-if="props.meta.total > 0"
        class="flex flex-col gap-3 border-t border-gray-200 px-5 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
    >
        <!-- Select Opsi Per Page -->
        <div
            class="order-1 flex items-center text-xs text-gray-500 dark:text-gray-400"
        >
            Show
            <Select
                v-model="perPage"
                :options="props.perPageOptions"
                optionLabel="label"
                optionValue="value"
                class="ml-1.5 w-20"
                size="small"
                :pt="{
                    root: '!h-8 !py-0 !px-2.5 !text-xs !rounded-lg !border-gray-200 dark:!border-gray-800 !bg-white dark:!bg-gray-900 !shadow-none focus:!ring-1 focus:!ring-purple-500',
                    label: '!p-0 !text-xs !font-medium !text-gray-700 dark:!text-gray-200 !leading-8',
                    dropdown: '!w-4 !text-gray-400',
                    overlay:
                        '!rounded-xl !border-gray-100 dark:!border-gray-800 !shadow-xl !text-xs',
                    option: '!py-1.5 !px-3 !text-xs !rounded-md my-0.5',
                }"
                @change="onChangePerPage"
            />
        </div>

        <!-- Links Pagination -->
        <div
            class="order-2 flex items-center gap-1"
        >
            <template v-if="props.clientPageCount !== undefined">
                <button
                    type="button"
                    class="flex h-8 min-w-[32px] items-center justify-center rounded-lg px-2 text-xs text-gray-600 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:text-gray-300 dark:text-gray-300 dark:hover:bg-white/[0.05] dark:disabled:text-gray-600"
                    aria-label="Halaman sebelumnya"
                    :disabled="currentPage <= 1"
                    @click="setClientPage(currentPage - 1)"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <button
                    v-for="page in clientPages"
                    :key="page"
                    type="button"
                    class="flex h-8 min-w-[32px] items-center justify-center rounded-lg px-2.5 text-xs font-medium transition-all"
                    :class="page === currentPage ? 'bg-purple-100 font-bold text-purple-900 dark:bg-purple-900/50 dark:text-purple-200' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/[0.05]'"
                    :aria-current="page === currentPage ? 'page' : undefined"
                    :aria-label="`Halaman ${page}`"
                    @click="setClientPage(page)"
                >
                    {{ page }}
                </button>
                <button
                    type="button"
                    class="flex h-8 min-w-[32px] items-center justify-center rounded-lg px-2 text-xs text-gray-600 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:text-gray-300 dark:text-gray-300 dark:hover:bg-white/[0.05] dark:disabled:text-gray-600"
                    aria-label="Halaman berikutnya"
                    :disabled="currentPage >= (props.clientPageCount ?? 1)"
                    @click="setClientPage(currentPage + 1)"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </template>

            <template v-else v-for="link in props.meta.links ?? []" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="flex h-8 min-w-[32px] items-center justify-center rounded-lg px-2.5 text-xs font-medium transition-all"
                    :class="
                        link.active
                            ? 'bg-purple-100 font-bold text-purple-900 dark:bg-purple-900/50 dark:text-purple-200'
                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/[0.05]'
                    "
                >
                    <ChevronLeft
                        v-if="link.label.includes('Previous')"
                        class="h-4 w-4"
                    />
                    <ChevronRight
                        v-else-if="link.label.includes('Next')"
                        class="h-4 w-4"
                    />
                    <span v-else v-html="link.label"></span>
                </Link>

                <span
                    v-else
                    class="flex h-8 min-w-[32px] cursor-not-allowed items-center justify-center rounded-lg px-2 text-xs text-gray-300 opacity-60 dark:text-gray-600"
                >
                    <ChevronLeft
                        v-if="link.label.includes('Previous')"
                        class="h-4 w-4"
                    />
                    <ChevronRight
                        v-else-if="link.label.includes('Next')"
                        class="h-4 w-4"
                    />
                    <span v-else v-html="link.label"></span>
                </span>
            </template>
        </div>
    </div>
</template>
