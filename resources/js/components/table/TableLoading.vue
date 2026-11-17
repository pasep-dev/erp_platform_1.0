<script setup lang="ts">
withDefaults(
    defineProps<{
        message?: string;
    }>(),
    {
        message: "Memuat data...",
    },
);
</script>

<template>
    <div class="flex items-center justify-center py-10 text-muted-color">
        <i class="pi pi-spin pi-spinner mr-2" />
        {{ message }}
    </div>
</template>
