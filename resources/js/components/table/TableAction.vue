<script setup lang="ts">
import Button from "primevue/button";

withDefaults(
    defineProps<{
        showView?: boolean;
        showEdit?: boolean;
        showSubmit?: boolean;
        showPrint?: boolean;
        showDelete?: boolean;
        disabled?: boolean;
        loading?: boolean;
    }>(),
    {
        showView: false,
        showEdit: false,
        showSubmit: false,
        showPrint: false,
        showDelete: false,
        disabled: false,
        loading: false,
    },
);

const emit = defineEmits<{
    view: [];
    edit: [];
    submit: [];
    print: [];
    delete: [];
}>();
</script>

<template>
    <!-- Tambahkan gap lebih besar (gap-2) agar tidak terlalu berdempetan -->
    <div class="flex items-center justify-center gap-2">
        <Button
            v-if="showView"
            icon="pi pi-eye"
            severity="secondary"
            text
            rounded
            :disabled="disabled"
            aria-label="Lihat"
            v-tooltip.top="'Lihat'"
            class="hover:text-blue-600 hover:bg-blue-50"
            @click="emit('view')"
        />

        <Button
            v-if="showEdit"
            icon="pi pi-pencil"
            severity="secondary"
            text
            rounded
            :disabled="disabled"
            aria-label="Edit"
            v-tooltip.top="'Edit'"
            class="hover:text-amber-600 hover:bg-amber-50"
            @click="emit('edit')"
        />

        <Button
            v-if="showSubmit"
            icon="pi pi-send"
            severity="secondary"
            text
            rounded
            :disabled="disabled || loading"
            aria-label="Submit"
            v-tooltip.top="'Submit'"
            class="hover:text-emerald-600 hover:bg-emerald-50"
            @click="emit('submit')"
        />

        <Button
            v-if="showPrint"
            icon="pi pi-print"
            severity="secondary"
            text
            rounded
            :disabled="disabled"
            aria-label="Print"
            v-tooltip.top="'Print'"
            class="hover:text-purple-600 hover:bg-purple-50"
            @click="emit('print')"
        />

        <Button
            v-if="showDelete"
            icon="pi pi-trash"
            severity="secondary"
            text
            rounded
            :disabled="disabled || loading"
            aria-label="Hapus"
            v-tooltip.top="'Hapus'"
            class="hover:text-red-600 hover:bg-red-50"
            @click="emit('delete')"
        />
    </div>
</template>
