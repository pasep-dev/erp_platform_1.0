<?php

namespace Tests\Feature;

use App\Models\NumberingDocument;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NumberingPreviewControllerTest extends TestCase
{
    public function test_user_with_module_create_permission_can_preview_without_consuming_the_sequence(): void
    {
        $user = $this->userWithPermissions('master.product.create');
        NumberingDocument::query()->create([
            'code' => 'PRODUCT',
            'name' => 'Product Master',
            'pattern' => 'PRD-{SEQ}',
            'sequence_length' => 6,
            'sequence_start' => 1,
            'reset_period' => 'never',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('numbering.preview', [
                'numberingModule' => 'PRODUCT',
            ]))
            ->assertOk()
            ->assertJson([
                'enabled' => true,
                'preview' => 'PRD-000001',
            ]);

        $this->assertDatabaseCount('numbering_sequences', 0);
    }

    public function test_user_without_module_create_permission_cannot_preview_a_number(): void
    {
        $user = $this->userWithPermissions('master.product.view');

        $this->actingAs($user)
            ->get(route('numbering.preview', [
                'numberingModule' => 'PRODUCT',
            ]))
            ->assertForbidden();
    }

    public function test_unintegrated_module_cannot_be_used_for_number_preview(): void
    {
        $user = $this->userWithPermissions('asset.transfer');

        $this->actingAs($user)
            ->get(route('numbering.preview', [
                'numberingModule' => 'INVENTORY_ITEM',
            ]))
            ->assertNotFound();
    }

    private function userWithPermissions(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }
}
