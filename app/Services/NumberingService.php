<?php

namespace App\Services;

use App\Models\NumberingDocument;
use App\Models\NumberingSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class NumberingService
{
    /**
     * Generate a business number based on a numbering rule.
     *
     * @param  array<string, scalar|null>  $parameters
     */
    public function generate(
        string $documentCode,
        array $parameters = [],
    ): string {
        $now = now();

        return DB::transaction(function () use (
            $documentCode,
            $parameters,
            $now,
        ): string {
            $document = NumberingDocument::query()
                ->where('code', $documentCode)
                ->where('is_active', true)
                ->first();

            if (! $document) {
                throw new RuntimeException(
                    "Numbering document [{$documentCode}] not found or inactive.",
                );
            }

            $this->validateNumberingDocument($document);

            $this->validatePatternParameters(
                $document->pattern,
                $parameters,
            );

            $scopeKey = $this->resolveScopeKey(
                $document->pattern,
                $parameters,
            );

            $periodKey = $this->resolvePeriodKey(
                $document->reset_period,
                $now,
            );

            /*
             * Create the sequence row if it does not exist.
             *
             * PostgreSQL handles the unique conflict using upsert.
             * This avoids duplicate sequence rows during concurrent requests.
             */
            NumberingSequence::query()->upsert(
                [
                    [
                        'id' => (string) Str::ulid(),
                        'numbering_document_id' => $document->id,
                        'scope_key' => $scopeKey,
                        'period_key' => $periodKey,
                        'current_value' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ],
                [
                    'numbering_document_id',
                    'scope_key',
                    'period_key',
                ],
                [
                    'updated_at',
                ],
            );

            $sequence = NumberingSequence::query()
                ->where('numbering_document_id', $document->id)
                ->where('scope_key', $scopeKey)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('current_value');

            $sequence->refresh();

            return $this->formatNumber(
                pattern: $document->pattern,
                sequence: $sequence->current_value,
                parameters: $parameters,
                now: $now,
                sequenceLength: $document->sequence_length,
            );
        });
    }

    private function resolveScopeKey(
        string $pattern,
        array $parameters,
    ): string {
        $scopeParts = [];

        foreach (
            [
                'COMPANY',
                'ASSET_TYPE',
                'BRANCH',
                'DOCUMENT_TYPE',
                'DEPARTMENT',
            ] as $placeholder
        ) {
            $token = '{' . $placeholder . '}';

            if (str_contains($pattern, $token)) {
                $scopeParts[] = $placeholder . '='
                    . (string) $parameters[strtolower($placeholder)];
            }
        }

        return $scopeParts === []
            ? 'global'
            : implode('|', $scopeParts);
    }

    private function resolvePeriodKey(
        string $resetPeriod,
        CarbonInterface $now,
    ): string {
        return match ($resetPeriod) {
            'monthly' => $now->format('Y-m'),
            'yearly' => $now->format('Y'),
            'never' => 'global',
            default => throw new RuntimeException(
                "Unsupported reset period [{$resetPeriod}].",
            ),
        };
    }

    private function validatePatternParameters(
        string $pattern,
        array $parameters,
    ): void {
        preg_match_all(
            '/\{([A-Z_]+)\}/',
            $pattern,
            $matches,
        );

        $placeholders = array_unique($matches[1]);

        $supportedPlaceholders = [
            'YYYY',
            'YY',
            'MM',
            'DD',
            'SEQ',
            'COMPANY',
            'ASSET_TYPE',
            'BRANCH',
            'DOCUMENT_TYPE',
            'DEPARTMENT',
        ];

        foreach ($placeholders as $placeholder) {
            if (! in_array($placeholder, $supportedPlaceholders, true)) {
                throw new RuntimeException(
                    "Unsupported numbering placeholder [{$placeholder}].",
                );
            }

            if (in_array($placeholder, [
                'YYYY',
                'YY',
                'MM',
                'DD',
                'SEQ',
            ], true)) {
                continue;
            }

            $parameterKey = strtolower($placeholder);

            if (
                ! array_key_exists($parameterKey, $parameters)
                || $parameters[$parameterKey] === null
                || $parameters[$parameterKey] === ''
            ) {
                throw new RuntimeException(
                    "Missing numbering parameter [{$parameterKey}].",
                );
            }
        }
    }

    private function formatNumber(
        string $pattern,
        int $sequence,
        array $parameters,
        CarbonInterface $now,
        int $sequenceLength,
    ): string {
        $sequenceNumber = str_pad(
            (string) $sequence,
            $sequenceLength,
            '0',
            STR_PAD_LEFT,
        );

        $replacements = [
            '{YYYY}' => $now->format('Y'),
            '{YY}' => $now->format('y'),
            '{MM}' => $now->format('m'),
            '{DD}' => $now->format('d'),
            '{SEQ}' => $sequenceNumber,
        ];

        foreach (
            [
                'COMPANY',
                'ASSET_TYPE',
                'BRANCH',
                'DOCUMENT_TYPE',
                'DEPARTMENT',
            ] as $placeholder
        ) {
            $token = '{' . $placeholder . '}';
            $parameterKey = strtolower($placeholder);

            if (str_contains($pattern, $token)) {
                $replacements[$token] = (string) $parameters[$parameterKey];
            }
        }

        return strtr($pattern, $replacements);
    }

    private function validateNumberingDocument(
        NumberingDocument $document,
    ): void {
        if (trim($document->pattern) === '') {
            throw new RuntimeException(
                "Numbering document [{$document->code}] has an empty pattern.",
            );
        }

        if ($document->sequence_length < 1) {
            throw new RuntimeException(
                "Numbering document [{$document->code}] has an invalid sequence length.",
            );
        }

        if (! str_contains($document->pattern, '{SEQ}')) {
            throw new RuntimeException(
                "Numbering document [{$document->code}] must contain {SEQ}.",
            );
        }
    }
}
