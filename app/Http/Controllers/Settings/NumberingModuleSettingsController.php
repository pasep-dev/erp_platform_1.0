<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreNumberingModuleRequest;
use App\Models\NumberingModule;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NumberingModuleSettingsController extends Controller
{
    public function store(
        StoreNumberingModuleRequest $request,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $validated = $request->validated();
        $module = NumberingModule::query()->create([
            ...$validated,
            'is_active' => true,
        ]);

        $activityLogger->log(
            action: 'CREATE',
            module: 'Module Settings',
            description: "System module [{$module->code}] was registered.",
            subjectType: NumberingModule::class,
            subjectId: $module->code,
            newValues: $module->only([
                'code',
                'name',
                'category',
                'is_active',
            ]),
        );

        return back()->with(
            'success',
            'System module was added successfully.',
        );
    }

    public function update(
        Request $request,
        NumberingModule $numberingModule,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $oldIsActive = $numberingModule->is_active;
        $numberingModule->update([
            'is_active' => $validated['is_active'],
        ]);

        if ($oldIsActive !== $numberingModule->is_active) {
            $activityLogger->log(
                action: 'UPDATE',
                module: 'Module Settings',
                description: "System module [{$numberingModule->code}] status updated.",
                subjectType: NumberingModule::class,
                subjectId: $numberingModule->code,
                oldValues: ['is_active' => $oldIsActive],
                newValues: ['is_active' => $numberingModule->is_active],
            );
        }

        return back()->with(
            'success',
            'Module availability was updated successfully.',
        );
    }
}
