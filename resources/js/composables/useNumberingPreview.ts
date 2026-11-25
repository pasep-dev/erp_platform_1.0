import { computed, ref, watch, type MaybeRefOrGetter, toValue } from "vue";
import { route } from "ziggy-js";

interface NumberingPreviewResponse {
    enabled: boolean;
    preview: string | null;
}

export function useNumberingPreview(
    moduleCode: string,
    parameters: MaybeRefOrGetter<Record<string, string | null | undefined>> = {},
) {
    const enabled = ref(false);
    const preview = ref<string | null>(null);
    const loading = ref(false);
    let requestVersion = 0;

    const query = computed(() => {
        const values = toValue(parameters);
        return Object.fromEntries(
            Object.entries(values).filter(([, value]) => value !== null && value !== undefined && value !== ""),
        );
    });

    watch(
        query,
        async (values) => {
            const version = ++requestVersion;
            loading.value = true;

            try {
                const url = new URL(route("numbering.preview", { numberingModule: moduleCode }), window.location.origin);
                for (const [key, value] of Object.entries(values)) {
                    url.searchParams.set(`parameters[${key}]`, String(value));
                }

                const response = await fetch(url, {
                    headers: { Accept: "application/json" },
                    credentials: "same-origin",
                });
                if (!response.ok) {
                    throw new Error("Numbering preview request failed.");
                }

                const data = (await response.json()) as NumberingPreviewResponse;
                if (version === requestVersion) {
                    enabled.value = data.enabled;
                    preview.value = data.preview;
                }
            } catch {
                if (version === requestVersion) {
                    enabled.value = false;
                    preview.value = null;
                }
            } finally {
                if (version === requestVersion) {
                    loading.value = false;
                }
            }
        },
        { immediate: true, deep: true },
    );

    return { enabled, preview, loading };
}
