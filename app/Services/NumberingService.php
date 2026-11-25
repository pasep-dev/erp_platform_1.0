<?php

namespace App\Services;

use App\Enums\NumberingModuleCode;
use App\Models\NumberingDocument;
use App\Models\NumberingSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class NumberingService
{
    /**
     * Preview the next number without reserving or incrementing its sequence.
     *
     * @param  array<string, scalar|null>  $parameters
     */
    public function preview(NumberingModuleCode $documentCode, array $parameters = []): ?string
    {
        $document = $this->activeDocument($documentCode);

        if ($document === null) {
            return null;
        }

        $this->validateNumberingDocument($document);

        try {
            $this->validatePatternParameters($document->pattern, $parameters, $documentCode);
        } catch (RuntimeException) {
            return null;
        }

        $now = now();
        $scopeKey = $this->resolveScopeKey($document->pattern, $parameters);
        $periodKey = $this->resolvePeriodKey($document->reset_period, $now);
        $currentValue = NumberingSequence::query()
            ->where('numbering_document_id', $document->id)
            ->where('scope_key', $scopeKey)
            ->where('period_key', $periodKey)
            ->value('current_value');
        $nextSequence = $currentValue === null
            ? $document->sequence_start
            : (int) $currentValue + 1;

        return $this->formatNumber(
            pattern: $document->pattern,
            sequence: $nextSequence,
            parameters: $parameters,
            now: $now,
            sequenceLength: $document->sequence_length,
        );
    }

    public function hasActiveDocument(NumberingModuleCode $documentCode): bool
    {
        return NumberingDocument::query()
            ->whereHas('module', fn ($query) => $query->where('is_active', true))
            ->where('code', $documentCode->value)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Generate a business number based on a numbering rule.
     *
     * @param  array<string, scalar|null>  $parameters
     */
    public function generate(
        NumberingModuleCode $documentCode,
        array $parameters = [],
    ): string {
        $now = now();

        return DB::transaction(function () use (
            $documentCode,
            $parameters,
            $now,
        ): string {
            $document = $this->activeDocument($documentCode);

            if (! $document) {
                throw new RuntimeException(
                    "Numbering document [{$documentCode->value}] not found or inactive.",
                );
            }

            $this->validateNumberingDocument($document);

            $this->validatePatternParameters(
                $document->pattern,
                $parameters,
                $documentCode,
            );

            $scopeKey = $this->resolveScopeKey(
                $document->pattern,
                $parameters,
            );

            $periodKey = $this->resolvePeriodKey(
                $document->reset_period,
                $now,
            );

            /**
             * Numbering sequences are technical counters and are intentionally excluded from
             * per-model activity logs; the business document receiving the number is audited.
             * PostgreSQL handles the unique conflict using upsert to avoid duplicate rows.
             */
            NumberingSequence::query()->upsert(
                [
                    [
                        'id' => (string) Str::ulid(),
                        'numbering_document_id' => $document->id,
                        'scope_key' => $scopeKey,
                        'period_key' => $periodKey,
                        'current_value' => $document->sequence_start - 1,
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

    private function activeDocument(NumberingModuleCode $documentCode): ?NumberingDocument
    {
        return NumberingDocument::query()
            ->whereHas('module', fn ($query) => $query->where('is_active', true))
            ->where('code', $documentCode->value)
            ->where('is_active', true)
            ->first();
    }

    private function resolveScopeKey(
        string $pattern,
        array $parameters,
    ): string {
        $scopeParts = [];

        foreach (NumberingDocument::SCOPE_PLACEHOLDERS as $placeholder) {
            $token = '{'.$placeholder.'}';

            if (str_contains($pattern, $token)) {
                $scopeParts[] = $placeholder.'='
                    .(string) $parameters[strtolower($placeholder)];
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
        NumberingModuleCode $documentCode,
    ): void {
        preg_match_all(
            '/\{([A-Z_]+)\}/',
            $pattern,
            $matches,
        );

        $placeholders = array_unique($matches[1]);

        foreach ($placeholders as $placeholder) {
            if (! in_array(
                $placeholder,
                NumberingDocument::SUPPORTED_PLACEHOLDERS,
                true,
            )) {
                throw new RuntimeException(
                    "Unsupported numbering placeholder [{$placeholder}].",
                );
            }

            if (! in_array($placeholder, $documentCode->supportedPlaceholders(), true)) {
                throw new RuntimeException(
                    "Numbering placeholder [{$placeholder}] is unavailable for module [{$documentCode->value}].",
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

        foreach (NumberingDocument::SCOPE_PLACEHOLDERS as $placeholder) {
            $token = '{'.$placeholder.'}';
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
