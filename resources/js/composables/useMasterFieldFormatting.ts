import { watch } from "vue";

interface MasterFormFields {
    code: string;
    name: string;
}

export function useMasterFieldFormatting(form: MasterFormFields): void {
    watch(
        () => form.code,
        (code) => {
            const formattedCode = code.toLocaleUpperCase("id-ID");

            if (code !== formattedCode) {
                form.code = formattedCode;
            }
        },
        { immediate: true },
    );
}
