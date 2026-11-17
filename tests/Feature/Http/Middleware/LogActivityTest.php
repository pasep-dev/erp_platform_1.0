<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LogActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_transaction_and_administrator_requests_are_logged_by_module(): void
    {
        $user = User::factory()->create();

        Route::middleware('web')
            ->post('/_test/activity-log/transaction', fn () => response('', 200))
            ->name('transaction.inventory.requests.store');
        Route::middleware('web')
            ->post('/_test/activity-log/administrator', fn () => response('', 200))
            ->name('administrator.users.store');
        Route::middleware('web')
            ->get('/_test/activity-log/view', fn () => response('', 200))
            ->name('transaction.inventory.requests.index');

        $this->actingAs($user)
            ->post('/_test/activity-log/transaction', [
                'name' => 'Inventory request',
                'password' => 'must-not-be-recorded',
                'api_token' => 'must-not-be-recorded',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->post('/_test/activity-log/administrator')
            ->assertOk();

        $this->actingAs($user)
            ->get('/_test/activity-log/view')
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'module' => 'Transaction Inventory',
            'description' => 'Created Transaction Inventory Requests.',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'module' => 'Administrator Users',
            'description' => 'Created Administrator Users.',
        ]);

        $transactionLog = ActivityLog::query()
            ->where('description', 'Created Transaction Inventory Requests.')
            ->firstOrFail();

        $this->assertSame([
            'name' => 'Inventory request',
            'password' => '[REDACTED]',
            'api_token' => '[REDACTED]',
        ], $transactionLog->new_values);
        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_unsuccessful_authenticated_requests_do_not_create_activity_logs(): void
    {
        $user = User::factory()->create();

        Route::middleware('web')
            ->post('/_test/activity-log/forbidden', fn () => response('', 403))
            ->name('transaction.inventory.requests.submit');

        $this->actingAs($user)
            ->post('/_test/activity-log/forbidden')
            ->assertForbidden();

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_model_creates_are_logged_with_record_identity_and_masked_credentials(): void
    {
        $user = User::factory()->create();

        Route::middleware('web')
            ->post('/_test/activity-log/users', function () {
                $createdUser = User::query()->create([
                    'name' => 'New Employee',
                    'email' => 'new.employee@example.test',
                    'password' => 'secret-password',
                ]);

                return response()->json(['id' => $createdUser->id]);
            })
            ->name('administrator.users.store');

        $response = $this->actingAs($user)
            ->post('/_test/activity-log/users')
            ->assertOk();

        $createdUser = User::query()->findOrFail($response->json('id'));
        $activityLog = ActivityLog::query()->sole();

        $this->assertModelExists($createdUser);
        $this->assertSame('CREATE', $activityLog->action);
        $this->assertSame('New Employee', $activityLog->subject_id);
        $this->assertSame('Created User New Employee', $activityLog->description);
        $this->assertSame('[REDACTED]', $activityLog->new_values['password']);
        $this->assertStringNotContainsString('secret-password', json_encode($activityLog->new_values));
    }

    public function test_model_updates_are_logged_with_only_actual_changes_and_masked_credentials(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create([
            'name' => 'Old Employee',
            'password' => 'old-password',
        ]);

        Route::middleware('web')
            ->patch('/_test/activity-log/users/{user}', function (User $user, Request $request) {
                $user->update($request->only(['name', 'password']));

                return response('', 200);
            })
            ->name('administrator.users.update');

        $this->actingAs($user)
            ->patch('/_test/activity-log/users/'.$targetUser->id, [
                'name' => 'Updated Employee',
                'password' => 'new-secret',
            ])
            ->assertOk();

        $activityLog = ActivityLog::query()->sole();

        $this->assertSame([
            'name' => 'Old Employee',
            'password' => '[REDACTED]',
        ], $activityLog->old_values);
        $this->assertSame([
            'name' => 'Updated Employee',
            'password' => '[REDACTED]',
        ], $activityLog->new_values);
        $this->assertStringContainsString('Name from "Old Employee" to "Updated Employee"', $activityLog->description);
        $this->assertStringContainsString('Password from "[REDACTED]" to "[REDACTED]"', $activityLog->description);
    }

    public function test_per_model_bulk_deletes_are_audited_as_deletes(): void
    {
        $actor = User::factory()->create();
        $deletedUsers = User::factory()->count(2)->create();

        Route::middleware('web')
            ->post('/_test/activity-log/users/bulk-delete', function () use ($deletedUsers) {
                User::query()
                    ->whereKey($deletedUsers->modelKeys())
                    ->get()
                    ->each
                    ->delete();

                return response('', 200);
            })
            ->name('administrator.users.destroy');

        $this->actingAs($actor)
            ->post('/_test/activity-log/users/bulk-delete')
            ->assertOk();

        $this->assertDatabaseCount('activity_logs', 2);
        $this->assertSame(
            ['DELETE', 'DELETE'],
            ActivityLog::query()->orderBy('subject_id')->pluck('action')->all(),
        );
        $this->assertSame(
            $deletedUsers->pluck('name')->sort()->values()->all(),
            ActivityLog::query()->orderBy('subject_id')->pluck('subject_id')->all(),
        );
    }
}
