<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(
        SettingService $settings,
    ): Response {
        return Inertia::render('Settings/Index', [
            'settings' => [
                'company' => [
                    'name' => $settings->get('company.name', ''),
                    'email' => $settings->get('company.email', ''),
                    'phone' => $settings->get('company.phone', ''),
                    'address' => $settings->get('company.address', ''),
                ],

                'regional' => [
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
            ],
        ]);
    }

    public function update(
        Request $request,
        SettingService $settings,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'company.name' => [
                'required',
                'string',
                'max:150',
            ],

            'company.email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'company.phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'company.address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'regional.timezone' => [
                'required',
                'timezone',
            ],

            'regional.date_format' => [
                'required',
                'string',
                'max:50',
            ],

            'regional.datetime_format' => [
                'required',
                'string',
                'max:50',
            ],
        ]);

        $oldValues = [
            'company.name' => $settings->get('company.name'),
            'company.email' => $settings->get('company.email'),
            'company.phone' => $settings->get('company.phone'),
            'company.address' => $settings->get('company.address'),
            'system.timezone' => $settings->get('system.timezone'),
            'system.date_format' => $settings->get('system.date_format'),
            'system.datetime_format' => $settings->get('system.datetime_format'),
        ];

        $settings->set(
            key: 'company.name',
            value: $validated['company']['name'],
            group: 'company',
            description: 'Company or organization name.',
            isPublic: true,
        );

        $settings->set(
            key: 'company.email',
            value: $validated['company']['email'],
            group: 'company',
            description: 'Primary company email address.',
            isPublic: true,
        );

        $settings->set(
            key: 'company.phone',
            value: $validated['company']['phone'],
            group: 'company',
            description: 'Primary company phone number.',
            isPublic: true,
        );

        $settings->set(
            key: 'company.address',
            value: $validated['company']['address'],
            group: 'company',
            description: 'Company address.',
            isPublic: true,
        );

        $settings->set(
            key: 'system.timezone',
            value: $validated['regional']['timezone'],
            group: 'system',
            description: 'Application timezone.',
            isPublic: true,
        );

        $settings->set(
            key: 'system.date_format',
            value: $validated['regional']['date_format'],
            group: 'system',
            description: 'Default application date format.',
            isPublic: true,
        );

        $settings->set(
            key: 'system.datetime_format',
            value: $validated['regional']['datetime_format'],
            group: 'system',
            description: 'Default application date and time format.',
            isPublic: true,
        );

        $newValues = [
            'company.name' => $validated['company']['name'],
            'company.email' => $validated['company']['email'],
            'company.phone' => $validated['company']['phone'],
            'company.address' => $validated['company']['address'],
            'system.timezone' => $validated['regional']['timezone'],
            'system.date_format' => $validated['regional']['date_format'],
            'system.datetime_format' => $validated['regional']['datetime_format'],
        ];

        if ($oldValues !== $newValues) {
            $activityLogger->log(
                action: 'UPDATE',
                module: 'Settings',
                description: 'Application settings updated.',
                subjectType: \App\Models\Setting::class,
                oldValues: $oldValues,
                newValues: $newValues,
            );
        }

        return back()->with(
            'success',
            'Settings berhasil disimpan.',
        );
    }
}
