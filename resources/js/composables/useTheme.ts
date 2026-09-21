import { onMounted, ref } from 'vue';

const STORAGE_KEY = 'erp-theme';

const isDark = ref(false);

const applyTheme = (dark: boolean) => {
    isDark.value = dark;

    document.documentElement.classList.toggle('dark', dark);
};

export function useTheme() {
    const toggleTheme = () => {
        const nextTheme = !isDark.value;

        applyTheme(nextTheme);
        localStorage.setItem(
            STORAGE_KEY,
            nextTheme ? 'dark' : 'light',
        );
    };

    onMounted(() => {
        const savedTheme = localStorage.getItem(STORAGE_KEY);

        applyTheme(savedTheme === 'dark');
    });

    return {
        isDark,
        toggleTheme,
    };
}