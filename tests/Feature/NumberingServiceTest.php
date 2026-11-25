<?php

namespace Tests\Feature;

use App\Enums\NumberingModuleCode;
use App\Models\NumberingDocument;
use App\Models\NumberingModule;
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
            $service->generate(NumberingModuleCode::Asset, [
                'company' => 'CITRA',
                'asset_type' => 'LTP',
                'branch' => 'JKT',
            ]),
        );

        $this->assertSame(
            'CITRA-LTP002JKT',
            $service->generate(NumberingModuleCode::Asset, [
                'company' => 'CITRA',
                'asset_type' => 'LTP',
                'branch' => 'JKT',
            ]),
        );

        $this->assertSame(
            'CITRA-MON001JKT',
            $service->generate(NumberingModuleCode::Asset, [
                'company' => 'CITRA',
                'asset_type' => 'MON',
                'branch' => 'JKT',
            ]),
        );

        $this->assertSame(
            'CITRA-LTP001BDG',
            $service->generate(NumberingModuleCode::Asset, [
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
            $service->generate(NumberingModuleCode::PurchaseRequest, [
                'company' => 'CAA',
                'document_type' => 'PR',
                'department' => 'IT',
            ]),
        );

        $this->assertSame(
            "CAA/PR/IT/{$year}/002",
            $service->generate(NumberingModuleCode::PurchaseRequest, [
                'company' => 'CAA',
                'document_type' => 'PR',
                'department' => 'IT',
            ]),
        );
    }

    public function test_generates_inventory_number_from_multi_company_branch_and_department_context(): void
    {
        NumberingDocument::query()
            ->where('code', NumberingModuleCode::InventoryIssue->value)
            ->update([
                'pattern' => 'GI/{COMPANY}/{BRANCH}/{DEPARTMENT}/{YYYY}/{SEQ}',
                'sequence_length' => 3,
                'reset_period' => 'yearly',
            ]);

        $this->assertSame(
            'GI/CAA/SBY/IT/'.now()->format('Y').'/001',
            app(NumberingService::class)->generate(
                NumberingModuleCode::InventoryIssue,
                [
                    'company' => 'CAA',
                    'branch' => 'SBY',
                    'department' => 'IT',
                    'document_type' => 'ISSUE',
                    'direction' => 'OUT',
                    'source_company' => 'CAA',
                    'source_branch' => 'SBY',
                    'source_department' => 'IT',
                    'source_location' => 'WH-SBY',
                ],
            ),
        );
    }

    public function test_keeps_sequences_separate_for_different_scopes(): void
    {
        $service = app(NumberingService::class);

        $service->generate(NumberingModuleCode::Asset, [
            'company' => 'CITRA',
            'asset_type' => 'LTP',
            'branch' => 'JKT',
        ]);

        $service->generate(NumberingModuleCode::Asset, [
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
        NumberingDocument::query()
            ->where('code', 'ASSET')
            ->update(['pattern' => '{COMPANY}/{UNKNOWN}/{SEQ}']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Unsupported numbering placeholder',
        );

        app(NumberingService::class)->generate(NumberingModuleCode::Asset, [
            'company' => 'CAA',
        ]);
    }

    public function test_rejects_a_context_placeholder_not_supported_by_the_module(): void
    {
        NumberingDocument::query()->create([
            'code' => NumberingModuleCode::Product->value,
            'name' => 'Product Master',
            'pattern' => '{COMPANY}-{SEQ}',
            'sequence_length' => 4,
            'reset_period' => 'never',
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Numbering placeholder [COMPANY] is unavailable for module [PRODUCT].',
        );

        app(NumberingService::class)->generate(NumberingModuleCode::Product, [
            'company' => 'CAA',
        ]);
    }

    public function test_rejects_a_missing_required_parameter(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Missing numbering parameter [asset_type]',
        );

        app(NumberingService::class)->generate(NumberingModuleCode::Asset, [
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

        app(NumberingService::class)->generate(NumberingModuleCode::Asset, [
            'company' => 'CITRA',
            'asset_type' => 'LTP',
            'branch' => 'JKT',
        ]);
    }

    public function test_rejects_number_generation_when_module_integration_is_disabled(): void
    {
        NumberingModule::query()
            ->where('code', 'ASSET')
            ->update(['is_active' => false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found or inactive');

        app(NumberingService::class)->generate(NumberingModuleCode::Asset, [
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
            app(NumberingService::class)->generate(NumberingModuleCode::Asset, [
                'company' => 'CITRA',
                'asset_type' => 'LTP',
                'branch' => 'JKT',
            ]),
        );
    }

    public function test_starts_the_sequence_at_the_configured_value(): void
    {
        NumberingDocument::query()
            ->where('code', 'PURCHASE_REQUEST')
            ->update([
                'sequence_start' => 8,
            ]);

        $year = now()->format('Y');

        $this->assertSame(
            "CAA/PR/IT/{$year}/008",
            app(NumberingService::class)->generate(NumberingModuleCode::PurchaseRequest, [
                'company' => 'CAA',
                'document_type' => 'PR',
                'department' => 'IT',
            ]),
        );
    }

    public function test_rejects_a_numbering_rule_without_sequence_placeholder(): void
    {
        NumberingDocument::query()
            ->where('code', 'ASSET')
            ->update(['pattern' => '{COMPANY}/{BRANCH}']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must contain {SEQ}');

        app(NumberingService::class)->generate(NumberingModuleCode::Asset, [
            'company' => 'CAA',
            'branch' => 'JKT',
        ]);
    }

    public function test_rejects_an_empty_numbering_pattern(): void
    {
        NumberingDocument::query()
            ->where('code', 'ASSET')
            ->update(['pattern' => '']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('empty pattern');

        app(NumberingService::class)->generate(NumberingModuleCode::Asset);
    }

    public function test_rejects_an_invalid_sequence_length(): void
    {
        NumberingDocument::query()
            ->where('code', 'ASSET')
            ->update(['sequence_length' => 0]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid sequence length');

        app(NumberingService::class)->generate(NumberingModuleCode::Asset);
    }
}
