<script setup lang="ts">
import { computed } from "vue";
import CrudForm from "@/components/crud/CrudForm.vue";
import AppLayout from "@/layout/AppLayout.vue";
import MasterActiveToggle from "@/components/common/MasterActiveToggle.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useMasterFieldFormatting } from "@/composables/useMasterFieldFormatting";
import { useNumberingPreview } from "@/composables/useNumberingPreview";

defineOptions({
    layout: AppLayout,
});

interface CategoryOption {
    id: string;
    code: string;
    name: string;
    parent_id: string | null;
    parent: {
        id: string;
        code: string;
        name: string;
    } | null;
}

const props = defineProps<{
    categories: CategoryOption[];
}>();

const categoryGroups = computed(() => {
    const groups = new Map<
        string,
        { code: string; label: string; categories: CategoryOption[] }
    >();

    for (const category of props.categories) {
        const groupCode = category.parent?.code ?? "OTHER";
        const group = groups.get(groupCode) ?? {
            code: groupCode,
            label: category.parent
                ? `${category.parent.code} · ${category.parent.name}`
                : "Lainnya",
            categories: [],
        };

        group.categories.push(category);
        groups.set(groupCode, group);
    }

    return [...groups.values()]
        .map((group) => ({
            ...group,
            categories: [...group.categories].sort((left, right) =>
                left.code.localeCompare(right.code, "id-ID", {
                    numeric: true,
                    sensitivity: "base",
                }),
            ),
        }))
        .sort((left, right) =>
            left.code.localeCompare(right.code, "id-ID", {
                numeric: true,
                sensitivity: "base",
            }),
        );
});

const form = useForm({
    category_id: "",
    code: "",
    name: "",
    description: "",
    is_active: true,
});

useMasterFieldFormatting(form);

const { enabled: typeNumberingEnabled, preview: typeCodePreview, loading: typeCodeLoading } = useNumberingPreview("PRODUCT_TYPE");

const submit = () => {
    form.post(route("master.product.types.store"));
};

const cancel = () => {
    window.location.href = route("master.product.types.index");
};
</script>

<template>
    <Head title="Add Types" />

    <CrudForm
        title="Add Types"
        submit-label="Submit"
        submit-processing-label="Saving..."
        :processing="form.processing"
        @submit="submit"
        @cancel="cancel"
    >
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
                <label
                    for="category_id"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Category
                    <span class="text-red-500">*</span>
                </label>

                <select
                    id="category_id"
                    v-model="form.category_id"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="">Pilih Category</option>

                    <optgroup
                        v-for="group in categoryGroups"
                        :key="group.code"
                        :label="group.label"
                    >
                        <option
                            v-for="category in group.categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.code }} · {{ category.name }}
                        </option>
                    </optgroup>
                </select>

                <p
                    v-if="form.errors.category_id"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.category_id }}
                </p>
            </div>

            <div>
                <label
                    for="code"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Type Code
                    <span class="text-red-500">*</span>
                </label>

                <input
                    id="code"
                    :value="typeCodePreview ?? ''"
                    type="text"
                    maxlength="50"
                    :placeholder="typeCodeLoading ? 'Memuat preview kode...' : 'Aturan numbering belum tersedia'"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                    disabled
                    readonly
                />

                <p v-if="typeCodeLoading" class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Memuat preview kode dari Document Numbering...</p>
                <p v-else-if="!typeNumberingEnabled" class="mt-1.5 text-xs text-amber-700 dark:text-amber-400">Aturan PRODUCT_TYPE belum aktif. Minta administrator mengaktifkannya.</p>
                <p v-else-if="!typeCodePreview" class="mt-1.5 text-xs text-amber-700 dark:text-amber-400">Preview kode belum tersedia. Periksa aturan PRODUCT_TYPE.</p>
                <p v-else class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Kode dibuat otomatis dan tidak dapat diubah.</p>

                <p
                    v-if="form.errors.code"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.code }}
                </p>
            </div>

            <div class="md:col-span-2">
                <label
                    for="name"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Type Name
                    <span class="text-red-500">*</span>
                </label>

                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    maxlength="150"
                    placeholder="Contoh: Laptop"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                />

                <p
                    v-if="form.errors.name"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.name }}
                </p>
            </div>

            <div class="md:col-span-2">
                <label
                    for="description"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Description
                </label>

                <textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    maxlength="1000"
                    placeholder="Deskripsi type..."
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:text-white"
                ></textarea>

                <p
                    v-if="form.errors.description"
                    class="mt-1.5 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.description }}
                </p>
            </div>

            <div class="md:col-span-2">
                <MasterActiveToggle
                    v-model="form.is_active"
                    :error="form.errors.is_active"
                />
            </div>
        </div>
    </CrudForm>
</template>
