<?php

namespace App\Http\Requests\Administrator;

use App\Enums\NumberingModuleCode;
use App\Models\NumberingDocument;
use App\Models\NumberingModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NumberingDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->hasGlobalDepartmentAccess() || $user->can(
            $this->isMethod('POST')
                ? 'numbering.document.create'
                : 'numbering.document.update',
        );
    }

    public function rules(): array
    {
        $numberingDocument = $this->route('numbering_document');
        $numberingDocumentId = $numberingDocument instanceof NumberingDocument
            ? $numberingDocument->id
            : null;
        $isGeneratedMasterCode = in_array(
            $this->input('code'),
            NumberingDocument::GENERATED_MASTER_CODES,
            true,
        );
        $numberingModuleCode = NumberingModuleCode::tryFrom((string) $this->input('code'));
        $allowedPlaceholders = $numberingModuleCode?->supportedPlaceholders()
            ?? NumberingDocument::SUPPORTED_PLACEHOLDERS;
        $codeRules = [
            'required',
            'string',
            'max:50',
            'regex:/^[A-Z][A-Z0-9_]*$/',
            Rule::unique('numbering_documents', 'code')
                ->ignore($numberingDocumentId),
        ];

        if ($this->isMethod('POST')) {
            $codeRules[] = Rule::in(
                NumberingModule::query()
                    ->whereIn('code', NumberingModuleCode::values())
                    ->where('is_active', true)
                    ->pluck('code')
                    ->all(),
            );
        }

        return [
            'code' => [
                ...$codeRules,
                function (string $attribute, mixed $value, callable $fail) use (
                    $numberingDocument,
                ): void {
                    if (
                        $numberingDocument instanceof NumberingDocument
                        && $value !== $numberingDocument->code
                    ) {
                        $fail('Document code cannot be changed after creation.');
                    }
                },
            ],
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'pattern' => [
                'required',
                'string',
                $isGeneratedMasterCode ? 'max:43' : 'max:255',
                function (string $attribute, mixed $value, callable $fail) use (
                    $isGeneratedMasterCode,
                    $allowedPlaceholders,
                ): void {
                    if (! is_string($value)) {
                        return;
                    }

                    preg_match_all('/\{([A-Z_]+)\}/', $value, $matches);
                    $tokens = array_map(
                        static fn (string $placeholder): string => '{'.$placeholder.'}',
                        $matches[1],
                    );
                    $allowedTokens = array_map(
                        static fn (string $placeholder): string => '{'.$placeholder.'}',
                        $allowedPlaceholders,
                    );
                    $unmatchedBraces = preg_replace(
                        '/\{[A-Z_]+\}/',
                        '',
                        $value,
                    );

                    if (
                        str_contains($unmatchedBraces, '{')
                        || str_contains($unmatchedBraces, '}')
                    ) {
                        $fail('Pattern contains an invalid placeholder.');

                        return;
                    }

                    if (array_diff($tokens, $allowedTokens) !== []) {
                        $fail('Pattern contains a placeholder unavailable for the selected module.');

                        return;
                    }

                    if (count(array_filter(
                        $tokens,
                        static fn (string $token): bool => $token === '{SEQ}',
                    )) !== 1) {
                        $fail('Pattern must contain {SEQ} exactly once.');

                        return;
                    }

                    if (
                        $isGeneratedMasterCode
                        && (
                            array_diff(
                                $tokens,
                                ['{YYYY}', '{YY}', '{MM}', '{DD}', '{SEQ}'],
                            ) !== []
                            || preg_match(
                                '/[^A-Za-z0-9_-]/',
                                preg_replace('/\{[A-Z_]+\}/', '', $value),
                            ) === 1
                        )
                    ) {
                        $fail(
                            'Master code patterns may use only alphanumeric separators and date or sequence placeholders.',
                        );
                    }
                },
            ],
            'sequence_length' => [
                'required',
                'integer',
                'min:1',
                'max:12',
            ],
            'sequence_start' => [
                'required',
                'integer',
                'min:1',
                'max:2147483647',
            ],
            'reset_period' => [
                'required',
                Rule::in(['monthly', 'yearly', 'never']),
            ],
            'is_active' => [
                'required',
                'boolean',
                function (string $attribute, mixed $value, callable $fail) use (
                    $numberingDocument,
                ): void {
                    if (
                        $numberingDocument instanceof NumberingDocument
                        && NumberingModuleCode::tryFrom($numberingDocument->code) === null
                        && filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ) {
                        $fail('Document numbering cannot be enabled for an unintegrated module.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.in' => 'Pilih modul aktif yang terdaftar pada Settings.',
        ];
    }
}
