import { watch } from "vue";

interface MasterFormFields {
    code: string;
    name: string;
}

function toProperCase(value: string): string {
    return value
        .toLocaleLowerCase("id-ID")
        .replace(
            /(^|[\s\-/'’])(\p{L})/gu,
            (_match, separator: string, letter: string) =>
                `${separator}${letter.toLocaleUpperCase("id-ID")}`,
        );
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

    watch(
        () => form.name,
        (name) => {
            const formattedName = toProperCase(name);

            if (name !== formattedName) {
                form.name = formattedName;
            }
        },
        { immediate: true },
    );
}
