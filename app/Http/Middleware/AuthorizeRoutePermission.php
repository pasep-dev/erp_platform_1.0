<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeRoutePermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        abort_unless(is_string($routeName), 403);

        $permissions = $this->permissionsFor($routeName, $request->method());

        abort_if($permissions === null, 403);

        if ($permissions !== []) {
            $user = $request->user();

            abort_unless(
                $user !== null && (
                    $user->hasRole(['Administrator', 'Owner'])
                    || collect($permissions)->contains(
                        fn (string $permission): bool => $user->can($permission),
                    )
                ),
                403,
            );
        }

        return $next($request);
    }

    /**
     * An empty permission list means the controller applies object-level authorization.
     * A null result means the route is not in the explicit access map and must be denied.
     *
     * @return list<string>|null
     */
    private function permissionsFor(string $routeName, string $method): ?array
    {
        if ($routeName === 'dashboard') {
            return ['dashboard.view'];
        }

        if ($routeName === 'profile.show') {
            return [];
        }

        if (str_starts_with($routeName, 'master.')) {
            $segments = explode('.', $routeName);
            $action = end($segments);

            return isset($segments[1])
                ? [$this->resourcePermission("master.{$segments[1]}", $method, $action)]
                : null;
        }

        foreach ([
            'administrator.users' => 'users',
            'administrator.roles' => 'roles',
            'administrator.approval-workflows' => 'approval.workflow',
            'administrator.numbering-documents' => 'numbering.document',
        ] as $routePrefix => $permissionPrefix) {
            if (str_starts_with($routeName, $routePrefix.'.')) {
                $action = substr($routeName, strrpos($routeName, '.') + 1);

                return [$this->actionPermission($permissionPrefix, $method, $action)];
            }
        }

        if (str_starts_with($routeName, 'administrator.access-control.')) {
            return ['roles.update'];
        }

        if (str_starts_with($routeName, 'administrator.menu-control.')) {
            return ['roles.update'];
        }

        if ($routeName === 'administrator.activity-logs.index') {
            return ['activity-logs.view'];
        }

        if ($routeName === 'settings.edit') {
            return ['system.settings.view'];
        }

        if (in_array($routeName, ['settings.update', 'settings.modules.store', 'settings.modules.update'], true)) {
            return ['system.settings.update'];
        }

        if (str_starts_with($routeName, 'settings.backup')) {
            return ['system.settings.backup'];
        }

        if (str_starts_with($routeName, 'approval.')) {
            return [];
        }

        if (str_starts_with($routeName, 'transaction.procurement.')) {
            return $this->procurementPermissions($routeName, $method);
        }

        if (str_starts_with($routeName, 'transaction.asset-management.')) {
            return $this->assetManagementPermissions($routeName, $method);
        }

        if (str_starts_with($routeName, 'transaction.inventory.')) {
            return $this->inventoryPermissions($routeName, $method);
        }

        if ($routeName === 'logout') {
            return [];
        }

        return null;
    }

    private function resourcePermission(string $prefix, string $method, string $action): string
    {
        $action = match ($method) {
            'GET', 'HEAD' => match ($action) {
                'create' => 'create',
                'edit' => 'update',
                default => 'view',
            },
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'view',
        };

        return "{$prefix}.{$action}";
    }

    private function actionPermission(string $prefix, string $method, string $action): string
    {
        if (in_array($method, ['GET', 'HEAD'], true)) {
            return match ($action) {
                'create' => "{$prefix}.create",
                'edit' => "{$prefix}.update",
                default => "{$prefix}.view",
            };
        }

        if ($method === 'DELETE') {
            return "{$prefix}.delete";
        }

        return match ($action) {
            'store' => "{$prefix}.create",
            'update' => "{$prefix}.update",
            'destroy' => "{$prefix}.delete",
            default => "{$prefix}.update",
        };
    }

    /**
     * @return list<string>
     */
    private function procurementPermissions(string $routeName, string $method): array
    {
        $permissionPrefix = match (true) {
            str_contains($routeName, '.purchase-requests.') => 'purchase-request',
            str_contains($routeName, '.purchase-orders.') => 'purchase-order',
            str_contains($routeName, '.goods-receipts.') => 'good-receipt',
            default => '',
        };

        if ($permissionPrefix === '') {
            return [];
        }

        $action = substr($routeName, strrpos($routeName, '.') + 1);

        if ($permissionPrefix === 'purchase-request' && in_array($action, ['cancel', 'destroy', 'mark-approved'], true)) {
            return [];
        }

        if (in_array($action, ['submit', 'cancel'], true)) {
            return ["{$permissionPrefix}.{$action}"];
        }

        return [$this->actionPermission($permissionPrefix, $method, $action)];
    }

    /**
     * @return list<string>|null
     */
    private function assetManagementPermissions(string $routeName, string $method): ?array
    {
        $segments = explode('.', $routeName);
        $module = $segments[2] ?? '';
        $action = end($segments);
        $isRead = in_array($method, ['GET', 'HEAD'], true);

        return match ($module) {
            'directory', 'reports' => $isRead || $action !== 'import'
                ? ['asset.view']
                : ['asset.create'],
            'registrations' => $isRead && $action !== 'create'
                ? ['registration.view']
                : ['registration.create'],
            'assignments' => $isRead && $action !== 'create'
                ? ['asset.view']
                : ['asset.transfer'],
            'audits' => $isRead && $action !== 'create'
                ? ['asset.view']
                : (in_array($action, ['store', 'create'], true) && $method !== 'PUT'
                    ? ['asset.create']
                    : ['asset.update']),
            'incidents' => $isRead && $action !== 'create'
                ? ['asset.view']
                : (in_array($action, ['store', 'create'], true)
                    ? ['asset.create']
                    : ['asset.update']),
            'transfers' => $isRead && $action !== 'create'
                ? ['asset.view']
                : ['asset.transfer'],
            'disposals' => $isRead && $action !== 'create'
                ? ['asset.view']
                : ['asset.dispose'],
            'maintenance' => $isRead && $action !== 'create' && $action !== 'edit'
                ? ['asset.view']
                : (in_array($action, ['store', 'create'], true)
                    ? ['asset.create']
                    : ['asset.update']),
            default => null,
        };
    }

    /**
     * @return list<string>|null
     */
    private function inventoryPermissions(string $routeName, string $method): ?array
    {
        $segments = explode('.', $routeName);
        $action = end($segments);
        $operation = implode('.', array_slice($segments, -2));

        if (str_contains($routeName, '.requests.')) {
            return match ($action) {
                'index' => ['consumable.view', 'consumable.request'],
                'show' => ['consumable.view', 'consumable.request', 'approval.approve'],
                'create', 'store', 'submit' => ['consumable.request'],
                'fulfill' => ['consumable.issue'],
                default => null,
            };
        }

        if (str_contains($routeName, '.receipts.manual.')) {
            return match ($action) {
                'create', 'store', 'mark-approved' => ['consumable.update', 'approval.approve'],
                'show', 'approval-document' => ['consumable.view', 'approval.approve'],
                default => null,
            };
        }

        if (str_contains($routeName, '.stocktakes.')) {
            return in_array($action, ['create', 'store', 'update', 'post'], true)
                ? ['consumable.update']
                : ['consumable.view'];
        }

        if (in_array($operation, [
            'issues.create', 'issues.store',
            'transfers.create', 'transfers.store',
            'returns.create', 'returns.store',
        ], true)) {
            return ['consumable.issue'];
        }

        if (in_array($operation, [
            'minimum-stock.update', 'receipt-integrations.retry', 'receipt-integrations.serials',
        ], true)) {
            return ['consumable.update'];
        }

        if (in_array($operation, ['adjustments.create', 'adjustments.store'], true)) {
            return ['consumable.issue'];
        }

        if ($action === 'settings') {
            return ['consumable.update'];
        }

        return in_array($method, ['GET', 'HEAD'], true)
            ? ['consumable.view']
            : ['consumable.issue'];
    }
}
