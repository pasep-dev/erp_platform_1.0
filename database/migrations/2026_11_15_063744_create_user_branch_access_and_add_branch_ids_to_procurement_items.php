<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_branch_access', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained('mst_org_branches')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'branch_id']);
        });

        Schema::create('product_branch', function (Blueprint $table): void {
            $table->foreignUlid('product_id')->constrained('mst_products')->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained('mst_org_branches')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['product_id', 'branch_id']);
        });

        foreach (['purchase_request_items', 'purchase_order_items'] as $itemsTable) {
            Schema::table($itemsTable, function (Blueprint $table) use ($itemsTable): void {
                $afterColumn = $itemsTable === 'purchase_request_items' ? 'purchase_request_id' : 'purchase_order_id';
                $table->foreignUlid('branch_id')->nullable()->after($afterColumn)
                    ->constrained('mst_org_branches')->nullOnDelete();
                $table->index('branch_id');
            });
        }

        Schema::table('inventory_requests', function (Blueprint $table): void {
            $table->foreignUlid('branch_id')->nullable()->after('department_id')
                ->constrained('mst_org_branches')->nullOnDelete();
            $table->index('branch_id');
        });

        DB::table('users as user')
            ->joinSub(
                DB::table('mst_org_branches')
                    ->whereNull('deleted_at')
                    ->select('company_id', DB::raw('MIN(id) as branch_id'), DB::raw('COUNT(*) as branch_count'))
                    ->groupBy('company_id')
                    ->havingRaw('COUNT(*) = 1'),
                'single_branch',
                'single_branch.company_id',
                '=',
                'user.company_id',
            )
            ->whereNull('user.branch_id')
            ->select(['user.id', 'single_branch.branch_id'])
            ->orderBy('user.id')
            ->chunk(500, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update(['branch_id' => $user->branch_id]);
                }
            });

        DB::table('inventory_requests as request')
            ->join('users', 'users.id', '=', 'request.requested_by')
            ->whereNotNull('users.branch_id')
            ->select(['request.id', 'users.branch_id'])
            ->orderBy('request.id')
            ->chunk(500, function ($requests): void {
                foreach ($requests as $request) {
                    DB::table('inventory_requests')->where('id', $request->id)->update(['branch_id' => $request->branch_id]);
                }
            });

        DB::table('assets as asset')
            ->join('mst_org_locations as location', 'location.id', '=', 'asset.location_id')
            ->whereNull('asset.branch_id')
            ->whereNotNull('location.branch_id')
            ->select(['asset.id', 'location.branch_id'])
            ->orderBy('asset.id')
            ->chunk(500, function ($assets): void {
                foreach ($assets as $asset) {
                    DB::table('assets')->where('id', $asset->id)->update(['branch_id' => $asset->branch_id]);
                }
            });

        $this->backfillDeliveryBranches('purchase_request_items', 'purchase_request_id', 'purchase_requests');
        $this->backfillDeliveryBranches('purchase_order_items', 'purchase_order_id', 'purchase_orders');
        $this->backfillProductBranches();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['purchase_order_items', 'purchase_request_items'] as $itemsTable) {
            Schema::table($itemsTable, function (Blueprint $table) use ($itemsTable): void {
                $table->dropIndex($itemsTable.'_branch_id_index');
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        Schema::table('inventory_requests', function (Blueprint $table): void {
            $table->dropIndex('inventory_requests_branch_id_index');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('user_branch_access');
        Schema::dropIfExists('product_branch');
    }

    private function backfillDeliveryBranches(string $itemsTable, string $parentKey, string $parentTable): void
    {
        DB::table($itemsTable.' as item')
            ->join($parentTable.' as parent', 'parent.id', '=', 'item.'.$parentKey)
            ->whereNotNull('item.delivery_address')
            ->select(['item.id', 'item.delivery_address', 'parent.company_id'])
            ->orderBy('item.id')
            ->chunk(500, function ($items) use ($itemsTable): void {
                $branches = DB::table('mst_org_branches')
                    ->whereIn('company_id', $items->pluck('company_id')->unique()->all())
                    ->whereNull('deleted_at')
                    ->get(['id', 'company_id', 'name', 'address'])
                    ->groupBy('company_id');

                foreach ($items as $item) {
                    $address = mb_strtolower(trim($item->delivery_address));
                    $matches = $branches->get($item->company_id, collect())->filter(function (object $branch) use ($address): bool {
                        return mb_strtolower(trim($branch->name)) === $address
                            || ($branch->address !== null && mb_strtolower(trim($branch->address)) === $address);
                    });

                    if ($matches->count() === 1) {
                        DB::table($itemsTable)->where('id', $item->id)->update(['branch_id' => $matches->first()->id]);
                    }
                }
            });
    }

    private function backfillProductBranches(): void
    {
        $now = now();
        $mappings = collect();

        if (Schema::hasTable('assets')) {
            $mappings = $mappings->merge(DB::table('assets')
                ->whereNotNull('product_id')->whereNotNull('branch_id')
                ->get(['product_id', 'branch_id']));
        }

        foreach (['purchase_request_items', 'purchase_order_items'] as $items) {
            $mappings = $mappings->merge(DB::table($items)
                ->whereNotNull('product_id')->whereNotNull('branch_id')
                ->get(['product_id', 'branch_id']));
        }

        foreach (['inventory_stock_movements', 'inventory_receipt_integrations'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'location_id')) {
                $mappings = $mappings->merge(DB::table($table.' as source')
                    ->join('mst_org_locations as location', 'location.id', '=', 'source.location_id')
                    ->whereNotNull('source.product_id')
                    ->whereNotNull('location.branch_id')
                    ->select(['source.product_id', 'location.branch_id'])
                    ->get());
            }
        }

        foreach ($mappings->unique(fn (object $mapping): string => $mapping->product_id.'|'.$mapping->branch_id) as $mapping) {
            DB::table('product_branch')->insertOrIgnore([
                'product_id' => $mapping->product_id,
                'branch_id' => $mapping->branch_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
