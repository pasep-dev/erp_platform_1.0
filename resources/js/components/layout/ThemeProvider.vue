<script setup lang="ts">
import { onMounted, ref } from "vue";

const STORAGE_KEY = "erp-theme";

const isDark = ref(false);

const applyTheme = (dark: boolean) => {
    isDark.value = dark;

    document.documentElement.classList.toggle("dark", dark);
};

const toggleTheme = () => {
    const nextTheme = !isDark.value;

    applyTheme(nextTheme);
    localStorage.setItem(STORAGE_KEY, nextTheme ? "dark" : "light");
};

onMounted(() => {
    const savedTheme = localStorage.getItem(STORAGE_KEY);

    if (savedTheme === "dark") {
        applyTheme(true);
        return;
    }

    applyTheme(false);
});

defineExpose({
    isDark,
    toggleTheme,
});
</script>

<template>
    <slot></slot>
</template>
