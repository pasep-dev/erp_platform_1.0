<?php

namespace Tests\Feature\Administrator;

use App\Models\NumberingDocument;
use App\Models\NumberingModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NumberingDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_from_numbering_page(): void
    {
        $this->get(route('administrator.numbering-documents.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_view_permission_is_forbidden_from_numbering_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('administrator.numbering-documents.index'))
            ->assertForbidden();
    }

    public function test_administrator_role_can_view_and_create_without_new_permission_seeded(): void
    {
        $user = User::factory()->create();
        $administrator = Role::findOrCreate('Administrator', 'web');
        $user->assignRole($administrator);

        $this->actingAs($user)
            ->get(route('administrator.numbering-documents.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administrator/Numbering/Index'));

        $this->actingAs($user)
            ->post(route('administrator.numbering-documents.store'), [
                'code' => 'VENDOR',
                'name' => 'Vendor Code',
                'pattern' => 'VND-{SEQ}',
                'sequence_length' => 6,
                'sequence_start' => 1,
                'reset_period' => 'never',
                'is_active' => true,
            ])
            ->assertRedirect(route('administrator.numbering-documents.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('numbering_documents', [
            'code' => 'VENDOR',
            'pattern' => 'VND-{SEQ}',
        ]);
    }

    public function test_user_without_create_permission_cannot_create_numbering_rules(): void
    {
        $user = $this->userWithPermissions('numbering.document.view');

        $this->actingAs($user)
            ->post(route('administrator.numbering-documents.store'), [
                'code' => 'VENDOR',
                'name' => 'Vendor Code',
                'pattern' => 'VND-{SEQ}',
                'sequence_length' => 6,
                'sequence_start' => 1,
                'reset_period' => 'never',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('numbering_documents', 0);
    }

    public function test_authorized_user_can_view_and_create_numbering_rules(): void
    {
        $user = $this->userWithPermissions(
            'numbering.document.view',
            'numbering.document.create',
        );

        $this->actingAs($user)
            ->get(route('administrator.numbering-documents.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administrator/Numbering/Index')
                ->has('documents.data', 0)
                ->where('placeholders.0', 'YYYY')
                ->where('placeholders.4', 'SEQ'));

        $this->actingAs($user)
            ->post(route('administrator.numbering-documents.store'), [
                'code' => 'VENDOR',
                'name' => 'Vendor Code',
                'pattern' => 'VND-{SEQ}',
                'sequence_length' => 6,
                'sequence_start' => 1,
                'reset_period' => 'never',
                'is_active' => true,
            ])
            ->assertRedirect(route('administrator.numbering-documents.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('numbering_documents', [
            'code' => 'VENDOR',
            'name' => 'Vendor Code',
            'pattern' => 'VND-{SEQ}',
            'sequence_start' => 1,
            'reset_period' => 'never',
            'is_active' => true,
        ]);
    }

    public function test_index_identifies_connected_modules_and_legacy_rules(): void
    {
        $user = $this->userWithPermissions('numbering.document.view');

        NumberingDocument::query()->create([
            'code' => 'VENDOR',
            'name' => 'Vendor Code',
            'pattern' => 'VND-{SEQ}',
            'sequence_length' => 6,
            'sequence_start' => 1,
            'reset_period' => 'never',
            'is_active' => true,
        ]);
        NumberingDocument::query()->create([
            'code' => 'LEGACY_VENDOR',
            'name' => 'Z Legacy Rule',
            'pattern' => 'LEG-{SEQ}',
            'sequence_length' => 6,
            'sequence_start' => 1,
            'reset_period' => 'never',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('administrator.numbering-documents.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administrator/Numbering/Index')
                ->where('modules.VENDOR.name', 'Vendor')
                ->where('documents.data.0.code', 'VENDOR')
                ->where('documents.data.0.module.name', 'Vendor')
                ->where('documents.data.1.code', 'LEGACY_VENDOR')
                ->where('documents.data.1.module', null));
    }

    public function test_user_cannot_create_a_rule_for_a_registered_but_unintegrated_module(): void
    {
        $user = $this->userWithPermissions(
            'numbering.document.view',
            'numbering.document.create',
        );
        NumberingModule::query()->create([
            'code' => 'SALES_ORDER',
            'name' => 'Sales Order',
            'category' => 'Sales',
            'default_pattern' => null,
            'sequence_length' => null,
            'reset_period' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('administrator.numbering-documents.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('modules.SALES_ORDER'));

        $this->actingAs($user)
            ->from(route('administrator.numbering-documents.index'))
            ->post(route('administrator.numbering-documents.store'), [
                'code' => 'SALES_ORDER',
                'name' => 'Sales Order',
                'pattern' => 'SO-{SEQ}',
                'sequence_length' => 6,
                'sequence_start' => 1,
                'reset_period' => 'never',
                'is_active' => true,
            ])
            ->assertSessionHasErrors([
                'code' => 'Pilih modul aktif yang terdaftar pada Settings.',
            ]);

        $this->assertDatabaseCount('numbering_documents', 0);
    }

    public function test_user_cannot_create_a_rule_for_a_disabled_module(): void
    {
        $user = $this->userWithPermissions(
            'numbering.document.view',
            'numbering.document.create',
        );
        NumberingModule::query()
            ->where('code', 'VENDOR')
            ->update(['is_active' => false]);

        $this->actingAs($user)
            ->from(route('administrator.numbering-documents.index'))
            ->post(route('administrator.numbering-documents.store'), [
                'code' => 'VENDOR',
                'name' => 'Vendor',
                'pattern' => 'VND-{SEQ}',
                'sequence_length' => 6,
                'sequence_start' => 1,
                'reset_period' => 'never',
                'is_active' => true,
            ])
            ->assertSessionHasErrors([
                'code' => 'Pilih modul aktif yang terdaftar pada Settings.',
            ]);

        $this->assertDatabaseCount('numbering_documents', 0);
    }

    public function test_authorized_user_can_update_rule_without_changing_its_code(): void
    {
        $user = $this->userWithPermissions('numbering.document.update');
        $document = $this->createDocument();

        $this->actingAs($user)
            ->put(
                route('administrator.numbering-documents.update', $document),
                [
                    'code' => 'PURCHASE_REQUEST',
                    'name' => 'Purchase Request Number',
                    'pattern' => 'PR-{YYYY}-{SEQ}',
                    'sequence_length' => 5,
                    'sequence_start' => 10,
                    'reset_period' => 'yearly',
                    'is_active' => false,
                ],
            )
            ->assertRedirect(route('administrator.numbering-documents.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('numbering_documents', [
            'id' => $document->id,
            'code' => 'PURCHASE_REQUEST',
            'name' => 'Purchase Request Number',
            'pattern' => 'PR-{YYYY}-{SEQ}',
            'sequence_length' => 5,
            'sequence_start' => 10,
            'reset_period' => 'yearly',
            'is_active' => false,
        ]);
    }

    public function test_unknown_pattern_placeholder_is_rejected(): void
    {
        $user = $this->userWithPermissions('numbering.document.create');

        $this->actingAs($user)
            ->from(route('administrator.numbering-documents.index'))
            ->post(route('administrator.numbering-documents.store'), [
                'code' => 'VENDOR',
                'name' => 'Vendor Code',
                'pattern' => 'VND-{UNKNOWN}-{SEQ}',
                'sequence_length' => 6,
                'sequence_start' => 1,
                'reset_period' => 'never',
                'is_active' => true,
            ])
            ->assertSessionHasErrors([
                'pattern' => 'Pattern contains an unsupported placeholder.',
            ]);

        $this->assertDatabaseCount('numbering_documents', 0);
    }

    public function test_pattern_must_contain_sequence_placeholder_exactly_once(): void
    {
        $user = $this->userWithPermissions('numbering.document.create');

        $this->actingAs($user)
            ->from(route('administrator.numbering-documents.index'))
            ->post(route('administrator.numbering-documents.store'), [
                'code' => 'VENDOR',
                'name' => 'Vendor Code',
                'pattern' => 'VND-{SEQ}-{SEQ}',
                'sequence_length' => 6,
                'sequence_start' => 1,
                'reset_period' => 'never',
                'is_active' => true,
            ])
            ->assertSessionHasErrors([
                'pattern' => 'Pattern must contain {SEQ} exactly once.',
            ]);

        $this->assertDatabaseCount('numbering_documents', 0);
    }

    public function test_document_code_cannot_be_changed_after_creation(): void
    {
        $user = $this->userWithPermissions('numbering.document.update');
        $document = $this->createDocument();

        $this->actingAs($user)
            ->from(route('administrator.numbering-documents.index'))
            ->put(
                route('administrator.numbering-documents.update', $document),
                [
                    'code' => 'NEW_CODE',
                    'name' => 'Purchase Request Number',
                    'pattern' => 'PR-{SEQ}',
                    'sequence_length' => 6,
                    'sequence_start' => 1,
                    'reset_period' => 'never',
                    'is_active' => true,
                ],
            )
            ->assertSessionHasErrors([
                'code' => 'Document code cannot be changed after creation.',
            ]);

        $this->assertDatabaseHas('numbering_documents', [
            'id' => $document->id,
            'code' => 'PURCHASE_REQUEST',
        ]);
    }

    public function test_legacy_rule_cannot_be_activated_for_an_unintegrated_module(): void
    {
        $user = $this->userWithPermissions('numbering.document.update');
        $document = NumberingDocument::query()->create([
            'code' => 'LEGACY_VENDOR',
            'name' => 'Legacy Vendor Rule',
            'pattern' => 'VND-{SEQ}',
            'sequence_length' => 6,
            'sequence_start' => 1,
            'reset_period' => 'never',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->from(route('administrator.numbering-documents.index'))
            ->put(route('administrator.numbering-documents.update', $document), [
                'code' => 'LEGACY_VENDOR',
                'name' => 'Legacy Vendor Rule',
                'pattern' => 'VND-{SEQ}',
                'sequence_length' => 6,
                'sequence_start' => 1,
                'reset_period' => 'never',
                'is_active' => true,
            ])
            ->assertSessionHasErrors([
                'is_active' => 'Document numbering cannot be enabled for an unintegrated module.',
            ]);

        $this->assertDatabaseHas('numbering_documents', [
            'id' => $document->id,
            'is_active' => false,
        ]);
    }

    private function userWithPermissions(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);

        return $user;
    }

    private function createDocument(): NumberingDocument
    {
        return NumberingDocument::query()->create([
            'code' => 'PURCHASE_REQUEST',
            'name' => 'Purchase Request',
            'pattern' => 'PR-{SEQ}',
            'sequence_length' => 6,
            'sequence_start' => 1,
            'reset_period' => 'never',
            'is_active' => true,
        ]);
    }
}
