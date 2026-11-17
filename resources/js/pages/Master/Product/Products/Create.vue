<script setup lang="ts">
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { useMasterFieldFormatting } from "@/composables/useMasterFieldFormatting";

import AppLayout from "@/layout/AppLayout.vue";
import MasterActiveToggle from "@/components/common/MasterActiveToggle.vue";
import CrudForm from "@/components/crud/CrudForm.vue";

defineOptions({
    layout: AppLayout,
});

interface Category {
    id: string;
    code: string;
    name: string;
}

interface Type {
    id: string;
    category_id: string;
    code: string;
    name: string;
}

interface Brand {
    id: string;
    code: string;
    name: string;
    categories: Category[];
}

interface ProductModel {
    id: string;
    brand_id: string;
    type_id: string;
    code: string;
    name: string;
}

interface UnitOfMeasure {
    id: string;
    code: string;
    name: string;
}

interface Department {
    id: string;
    name: string;
}

interface Branch { id: string; name: string; code: string; }

const props = defineProps<{
    categories: Category[];
    types: Type[];
    brands: Brand[];
    models: ProductModel[];
    unitsOfMeasure: UnitOfMeasure[];
    departments: Department[];
    branches: Branch[];
}>();

const form = useForm({
    category_id: "",
    type_id: "",
    brand_id: "",
    model_id: "",
    group: "",
    inventory_tracking_mode: "QUANTITY",
    base_uom_id: "",
    code: "",
    name: "",
    description: "",
    is_active: true,
    department_ids: [] as string[],
    branch_ids: [] as string[],
});

useMasterFieldFormatting(form);

const inputClass = "h-11 w-full rounded-xl border border-gray-300 bg-white px-3.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 hover:border-gray-400 focus:border-brand-700 focus:ring-2 focus:ring-brand-700/15 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 dark:hover:border-gray-600 dark:disabled:bg-gray-800";
const labelClass = "mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200";
const errorClass = "mt-1.5 text-xs text-red-600 dark:text-red-400";

const filteredTypes = computed(() =>
    props.types.filter((item) => item.category_id === form.category_id),
);

const filteredBrands = computed(() =>
    props.brands.filter((brand) =>
        brand.categories.some((category) => category.id === form.category_id),
    ),
);

const filteredModels = computed(() =>
    props.models.filter(
        (item) => item.brand_id === form.brand_id && item.type_id === form.type_id,
    ),
);

watch(
    () => form.category_id,
    (categoryId) => {
        const selectedType = props.types.find((item) => item.id === form.type_id);
        if (!categoryId || selectedType?.category_id !== categoryId) {
            form.type_id = "";
        }

        const selectedBrand = props.brands.find((item) => item.id === form.brand_id);
        if (!categoryId || !selectedBrand?.categories.some((category) => category.id === categoryId)) {
            form.brand_id = "";
            form.model_id = "";
        }
    },
);

watch(
    () => form.brand_id,
    (brandId) => {
        const selectedModel = props.models.find((item) => item.id === form.model_id);
        if (!brandId || selectedModel?.brand_id !== brandId || selectedModel?.type_id !== form.type_id) {
            form.model_id = "";
        }
    },
);

watch(
    () => form.type_id,
    (typeId) => {
        const selectedModel = props.models.find((item) => item.id === form.model_id);
        if (!typeId || selectedModel?.type_id !== typeId || selectedModel?.brand_id !== form.brand_id) {
            form.model_id = "";
        }
    },
);

watch(
    () => form.group,
    (group) => {
        if (group !== "CONSUMABLE") {
            form.inventory_tracking_mode = "QUANTITY";
            form.base_uom_id = "";
        }
    },
);

function submit(): void {
    form.post(route("master.product.products.store"));
}

function cancel(): void {
    window.location.href = route("master.product.products.index");
}
</script>

<template>
    <CrudForm
        title="Add Product"
        description="Tambahkan produk dan lengkapi klasifikasi agar data konsisten di seluruh modul."
        stacked-header
        submit-processing-label="Saving..."
        :processing="form.processing"
        @submit="submit"
        @cancel="cancel"
    >
        <div class="space-y-5">
            <section class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-gray-900/50">
                <div class="mb-5">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Informasi produk</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Identitas dan kelompok utama produk.</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="department_ids" :class="labelClass">Department pemilik <span class="text-red-500">*</span></label>
                        <select id="department_ids" v-model="form.department_ids" multiple :class="[inputClass, 'min-h-28']" required>
                            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Product hanya tersedia bagi department yang dipilih. Pilih beberapa hanya jika product memang digunakan bersama.</p>
                        <p v-if="form.errors.department_ids" :class="errorClass">{{ form.errors.department_ids }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label for="branch_ids" :class="labelClass">Branch tersedia <span class="text-red-500">*</span></label>
                        <select id="branch_ids" v-model="form.branch_ids" multiple :class="[inputClass, 'min-h-28']" required>
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.code }} · {{ branch.name }}</option>
                        </select>
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Pilih branch tempat produk ini boleh digunakan.</p>
                        <p v-if="form.errors.branch_ids" :class="errorClass">{{ form.errors.branch_ids }}</p>
                    </div>

                    <div>
                        <label for="group" :class="labelClass">Group <span class="text-red-500">*</span></label>
                        <select id="group" v-model="form.group" :class="inputClass" required>
                            <option value="" disabled>Pilih group produk</option>
                            <option value="ASSET">Asset</option>
                            <option value="CONSUMABLE">Consumable</option>
                            <option value="LICENSE">License</option>
                        </select>
                        <p v-if="form.errors.group" :class="errorClass">{{ form.errors.group }}</p>
                    </div>

                    <div>
                        <label for="code" :class="labelClass">Product Code</label>
                        <input id="code" v-model="form.code" type="text" maxlength="50" placeholder="Kosongkan untuk dibuat otomatis" :class="inputClass" />
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Kode akan mengikuti aturan aktif pada Administrator - Document Numbering.</p>
                        <p v-if="form.errors.code" :class="errorClass">{{ form.errors.code }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label for="name" :class="labelClass">Product Name <span class="text-red-500">*</span></label>
                        <input id="name" v-model="form.name" type="text" maxlength="150" placeholder="Contoh: Laptop bisnis 14 inci" :class="inputClass" required />
                        <p v-if="form.errors.name" :class="errorClass">{{ form.errors.name }}</p>
                    </div>

                    <div v-if="form.group === 'CONSUMABLE'" class="md:col-span-2">
                        <label for="inventory_tracking_mode" :class="labelClass">Inventory Tracking <span class="text-red-500">*</span></label>
                        <select id="inventory_tracking_mode" v-model="form.inventory_tracking_mode" :class="inputClass" required>
                            <option value="QUANTITY">Quantity based</option>
                            <option value="SERIAL">Serial number per unit</option>
                        </select>
                        <p v-if="form.errors.inventory_tracking_mode" :class="errorClass">{{ form.errors.inventory_tracking_mode }}</p>
                    </div>
                    <div v-if="form.group === 'CONSUMABLE'" class="md:col-span-2">
                        <label for="base_uom_id" :class="labelClass">Satuan Stok <span class="text-red-500">*</span></label>
                        <select id="base_uom_id" v-model="form.base_uom_id" :class="inputClass" required>
                            <option value="" disabled>Pilih satuan dasar stok</option>
                            <option v-for="unit in unitsOfMeasure" :key="unit.id" :value="unit.id">{{ unit.code }} · {{ unit.name }}</option>
                        </select>
                        <p v-if="form.errors.base_uom_id" :class="errorClass">{{ form.errors.base_uom_id }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-5">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Klasifikasi produk</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilihan brand dan type menyesuaikan kategori yang dipilih.</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="category_id" :class="labelClass">Category <span class="text-red-500">*</span></label>
                        <select id="category_id" v-model="form.category_id" :class="inputClass" required>
                            <option value="" disabled>Pilih category</option>
                            <option v-for="category in props.categories" :key="category.id" :value="category.id">{{ category.name }} ({{ category.code }})</option>
                        </select>
                        <p v-if="form.errors.category_id" :class="errorClass">{{ form.errors.category_id }}</p>
                    </div>

                    <div>
                        <label for="type_id" :class="labelClass">Type <span class="text-red-500">*</span></label>
                        <select id="type_id" v-model="form.type_id" :class="inputClass" :disabled="!form.category_id" required>
                            <option value="" disabled>{{ form.category_id ? 'Pilih type' : 'Pilih category terlebih dahulu' }}</option>
                            <option v-for="item in filteredTypes" :key="item.id" :value="item.id">{{ item.name }} ({{ item.code }})</option>
                        </select>
                        <p v-if="form.errors.type_id" :class="errorClass">{{ form.errors.type_id }}</p>
                    </div>

                    <div>
                        <label for="brand_id" :class="labelClass">Brand <span class="text-red-500">*</span></label>
                        <select id="brand_id" v-model="form.brand_id" :class="inputClass" :disabled="!form.category_id" required>
                            <option value="" disabled>{{ form.category_id ? 'Pilih brand' : 'Pilih category terlebih dahulu' }}</option>
                            <option v-for="brand in filteredBrands" :key="brand.id" :value="brand.id">{{ brand.name }} ({{ brand.code }})</option>
                        </select>
                        <p v-if="form.errors.brand_id" :class="errorClass">{{ form.errors.brand_id }}</p>
                    </div>

                    <div>
                        <label for="model_id" :class="labelClass">Model <span class="ml-1 text-xs font-normal text-gray-400">Opsional</span></label>
                        <select id="model_id" v-model="form.model_id" :class="inputClass" :disabled="!form.brand_id || !form.type_id">
                            <option value="">Tanpa model</option>
                            <option v-for="item in filteredModels" :key="item.id" :value="item.id">{{ item.name }} ({{ item.code }})</option>
                        </select>
                        <p v-if="form.errors.model_id" :class="errorClass">{{ form.errors.model_id }}</p>
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Model tersedia setelah brand dan type dipilih.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-5">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Informasi tambahan</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Catatan opsional dan status ketersediaan produk.</p>
                </div>

                <div>
                    <label for="description" :class="labelClass">Description</label>
                    <textarea id="description" v-model="form.description" rows="4" maxlength="1000" placeholder="Tambahkan detail produk jika diperlukan..." class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 hover:border-gray-400 focus:border-brand-700 focus:ring-2 focus:ring-brand-700/15 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500"></textarea>
                    <p v-if="form.errors.description" :class="errorClass">{{ form.errors.description }}</p>
                </div>

                <div class="mt-5 border-t border-gray-100 pt-5 dark:border-gray-800">
                    <MasterActiveToggle v-model="form.is_active" :error="form.errors.is_active" />
                </div>
            </section>
        </div>
    </CrudForm>
</template>
