import { router } from "@inertiajs/vue3";
import type { FormDataConvertible } from "@inertiajs/core";
import { ref } from "vue";

interface CrudOptions {
    indexRoute: string;
    onSuccess?: () => void;
}

type SubmitMethod = "post" | "put" | "patch";

export function useCrud(options: CrudOptions) {
    const processing = ref(false);

    const finish = (): void => {
        processing.value = false;
    };

    const handleSuccess = (): void => {
        options.onSuccess?.();
    };

    const destroy = (url: string): void => {
        if (processing.value) {
            return;
        }

        processing.value = true;

        router.delete(url, {
            preserveScroll: true,
            onSuccess: handleSuccess,
            onFinish: finish,
        });
    };

    const submit = (
        method: SubmitMethod,
        url: string,
        data: Record<string, FormDataConvertible>,
    ): void => {
        if (processing.value) {
            return;
        }

        processing.value = true;

        const callbacks = {
            onSuccess: handleSuccess,
            onFinish: finish,
        };

        switch (method) {
            case "post":
                router.post(url, data, callbacks);
                break;

            case "put":
                router.put(url, data, callbacks);
                break;

            case "patch":
                router.patch(url, data, callbacks);
                break;
        }
    };

    const goToIndex = (): void => {
        router.visit(options.indexRoute);
    };

    return {
        processing,
        submit,
        destroy,
        goToIndex,
    };
}