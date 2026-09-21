<?php

namespace Tests\Feature;

use App\Models\NumberingDocument;
use App\Models\NumberingSequence;
use App\Services\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        NumberingDocument::create([
            'code' => 'ASSET',
            'name' => 'Asset Number',
            'pattern' => '{COMPANY}-{ASSET_TYPE}{SEQ}{BRANCH}',
            'sequence_length' => 3,
            'reset_period' => 'never',
            'is_active' => true,
        ]);

        NumberingDocument::create([
            'code' => 'PURCHASE_REQUEST',
            'name' => 'Purchase Request',
            'pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
            'sequence_length' => 3,
            'reset_period' => 'yearly',
            'is_active' => true,
        ]);
    }

    public function test_generates_asset_numbers_based_on_asset_type_and_branch(): void
    {
        $service = app(NumberingService::class);

        $this->assertSame(
            'CITRA-LTP001JKT',
            $service->generate('ASSET', [
                'company' => 'CITRA',
                'asset_type' => 'LTP',
                'branch' => 'JKT',
            ]),
        );

        $this->assertSame(
            'CITRA-LTP002JKT',
            $service->generate('ASSET', [
                'company' => 'CITRA',
                'asset_type' => 'LTP',
                'branch' => 'JKT',
            ]),
        );

        $this->assertSame(
            'CITRA-MON001JKT',
            $service->generate('ASSET', [
                'company' => 'CITRA',
                'asset_type' => 'MON',
                'branch' => 'JKT',
            ]),
        );

        $this->assertSame(
            'CITRA-LTP001BDG',
            $service->generate('ASSET', [
                'company' => 'CITRA',
                'asset_type' => 'LTP',
                'branch' => 'BDG',
            ]),
        );
    }

    public function test_generates_purchase_request_numbers(): void
    {
        $service = app(NumberingService::class);
        $year = now()->format('Y');

        $this->assertSame(
            "CAA/PR/IT/{$year}/001",
            $service->generate('PURCHASE_REQUEST', [
                'company' => 'CAA',
                'document_type' => 'PR',
                'department' => 'IT',
            ]),
        );

        $this->assertSame(
            "CAA/PR/IT/{$year}/002",
            $service->generate('PURCHASE_REQUEST', [
                'company' => 'CAA',
                'document_type' => 'PR',
                'department' => 'IT',
            ]),
        );
    }

    public function test_keeps_sequences_separate_for_different_scopes(): void
    {
        $service = app(NumberingService::class);

        $service->generate('ASSET', [
            'company' => 'CITRA',
            'asset_type' => 'LTP',
            'branch' => 'JKT',
        ]);

        $service->generate('ASSET', [
            'company' => 'CITRA',
            'asset_type' => 'MON',
            'branch' => 'JKT',
        ]);

        $this->assertSame(2, NumberingSequence::query()->count());

        $this->assertSame(
            1,
            NumberingSequence::query()
                ->where(
                    'scope_key',
                    'COMPANY=CITRA|ASSET_TYPE=LTP|BRANCH=JKT',
                )
                ->value('current_value'),
        );

        $this->assertSame(
            1,
            NumberingSequence::query()
                ->where(
                    'scope_key',
                    'COMPANY=CITRA|ASSET_TYPE=MON|BRANCH=JKT',
                )
                ->value('current_value'),
        );
    }

    public function test_rejects_an_unsupported_placeholder(): void
    {
        NumberingDocument::create([
            'code' => 'INVALID',
            'name' => 'Invalid Rule',
            'pattern' => '{COMPANY}/{UNKNOWN}/{SEQ}',
            'sequence_length' => 3,
            'reset_period' => 'never',
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Unsupported numbering placeholder',
        );

        app(NumberingService::class)->generate('INVALID', [
            'company' => 'CAA',
        ]);
    }

    public function test_rejects_a_missing_required_parameter(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Missing numbering parameter [asset_type]',
        );

        app(NumberingService::class)->generate('ASSET', [
            'company' => 'CITRA',
            'branch' => 'JKT',
        ]);
    }

    public function test_rejects_an_inactive_numbering_rule(): void
    {
        NumberingDocument::query()
            ->where('code', 'ASSET')
            ->update([
                'is_active' => false,
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found or inactive');

        app(NumberingService::class)->generate('ASSET', [
            'company' => 'CITRA',
            'asset_type' => 'LTP',
            'branch' => 'JKT',
        ]);
    }

    public function test_uses_the_configured_sequence_length(): void
    {
        NumberingDocument::query()
            ->where('code', 'ASSET')
            ->update([
                'sequence_length' => 6,
            ]);

        $this->assertSame(
            'CITRA-LTP000001JKT',
            app(NumberingService::class)->generate('ASSET', [
                'company' => 'CITRA',
                'asset_type' => 'LTP',
                'branch' => 'JKT',
            ]),
        );
    }

    public function test_rejects_a_numbering_rule_without_sequence_placeholder(): void
    {
        NumberingDocument::create([
            'code' => 'NO_SEQUENCE',
            'name' => 'No Sequence Rule',
            'pattern' => '{COMPANY}/{BRANCH}',
            'sequence_length' => 3,
            'reset_period' => 'never',
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must contain {SEQ}');

        app(NumberingService::class)->generate('NO_SEQUENCE', [
            'company' => 'CAA',
            'branch' => 'JKT',
        ]);
    }

    public function test_rejects_an_empty_numbering_pattern(): void
    {
        NumberingDocument::create([
            'code' => 'EMPTY_PATTERN',
            'name' => 'Empty Pattern Rule',
            'pattern' => '',
            'sequence_length' => 3,
            'reset_period' => 'never',
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('empty pattern');

        app(NumberingService::class)->generate('EMPTY_PATTERN', [
            'company' => 'CAA',
        ]);
    }

    public function test_rejects_an_invalid_sequence_length(): void
    {
        NumberingDocument::create([
            'code' => 'INVALID_LENGTH',
            'name' => 'Invalid Length Rule',
            'pattern' => '{COMPANY}/{SEQ}',
            'sequence_length' => 0,
            'reset_period' => 'never',
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid sequence length');

        app(NumberingService::class)->generate('INVALID_LENGTH', [
            'company' => 'CAA',
        ]);
    }
}
