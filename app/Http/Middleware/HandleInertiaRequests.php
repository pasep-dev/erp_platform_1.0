<?php

namespace App\Http\Middleware;

use App\Services\MenuVisibilityService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = app(SettingService::class);
        $logoPath = $settings->get('system.logo_path');

        return [
            ...parent::share($request),

            'app' => [
                'name' => $settings->get(
                    'system.name',
                    $settings->get(
                        'company.name',
                        config('app.name'),
                    ),
                ),

                'company_name' => $settings->get(
                    'company.name',
                    config('app.name'),
                ),

                'logo_url' => is_string($logoPath) && $logoPath !== ''
                    ? Storage::disk('public')->url($logoPath)
                    : null,

                'email' => $settings->get(
                    'company.email',
                    null,
                ),

                'phone' => $settings->get(
                    'company.phone',
                    null,
                ),

                'address' => $settings->get(
                    'company.address',
                    null,
                ),

                'timezone' => $settings->get(
                    'system.timezone',
                    'Asia/Jakarta',
                ),

                'date_format' => $settings->get(
                    'system.date_format',
                    'd/m/Y',
                ),

                'datetime_format' => $settings->get(
                    'system.datetime_format',
                    'd/m/Y H:i',
                ),
            ],

            'csrf_token' => csrf_token(),

            'auth' => [
                'user' => $request->user(),
                'is_administrator' => $request->user()?->isAdministrator() ?? false,
                'menu_visibility' => fn () => $request->user() === null
                    ? null
                    : app(MenuVisibilityService::class)->visibleMenuKeysFor($request->user()),
                'permissions' => fn () => $request->user()
                    ?->getAllPermissions()
                    ->pluck('name')
                    ->values()
                    ->all() ?? [],
            ],

            'flash' => [
                'success' => fn () => $request
                    ->session()
                    ->get('success'),

                'error' => fn () => $request
                    ->session()
                    ->get('error'),

                'warning' => fn () => $request
                    ->session()
                    ->get('warning'),

                'info' => fn () => $request
                    ->session()
                    ->get('info'),
            ],
        ];
    }
}
