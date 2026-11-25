<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NumberingModuleCode;
use App\Http\Controllers\Controller;
use App\Models\NumberingModule;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NumberingModuleSettingsController extends Controller
{
    public function update(
        Request $request,
        NumberingModule $numberingModule,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        abort_unless(
            NumberingModuleCode::tryFrom($numberingModule->code) !== null,
            404,
        );

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
