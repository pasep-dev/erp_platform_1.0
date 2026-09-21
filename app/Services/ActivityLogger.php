<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function log(
        string $action,
        string $module,
        ?string $description = null,
        ?string $subjectType = null,
        string|int|null $subjectId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
    ): ActivityLog {
        $request ??= request();

        return ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'module' => $module,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId !== null
                ? (string) $subjectId
                : null,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->attributes->get('request_id'),
            'created_at' => now(),
        ]);
    }
}
