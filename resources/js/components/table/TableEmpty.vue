<script setup lang="ts">
withDefaults(
    defineProps<{
        message?: string;
        icon?: string;
    }>(),
    {
        message: "Tidak ada data.",
        icon: "pi pi-inbox",
    },
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center py-10 text-center text-muted-color"
    >
        <i :class="[icon, 'mb-3 text-3xl']" />

        <p class="font-medium">
            {{ message }}
        </p>
    </div>
</template>
