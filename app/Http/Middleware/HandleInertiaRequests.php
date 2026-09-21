<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Services\SettingService;

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

        return [
            ...parent::share($request),

            'app' => [
                'name' => $settings->get(
                    'company.name',
                    'ERP Platform',
                ),

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

            'auth' => [
                'user' => $request->user(),
            ],

            'flash' => [
                'success' => fn() => $request
                    ->session()
                    ->get('success'),

                'error' => fn() => $request
                    ->session()
                    ->get('error'),
            ],
        ];
    }
}
