<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

        $activityLog = ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'module' => $module,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId !== null
                ? (string) $subjectId
                : null,
            'description' => $description,
            'old_values' => $this->sanitizeValues($oldValues),
            'new_values' => $this->sanitizeValues($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->attributes->get('request_id'),
            'created_at' => now(),
        ]);

        $request->attributes->set('activity_log_recorded', true);

        return $activityLog;
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    public function sanitizeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $safeValues = [];

        foreach ($values as $key => $value) {
            if (preg_match(
                '/password|passphrase|token|secret|credential|(?:api|access|private|encryption|signing)[_-]?key|authorization|cookie/i',
                (string) $key,
            )) {
                $safeValues[$key] = '[REDACTED]';
            } elseif ($value instanceof UploadedFile) {
                continue;
            } elseif (is_array($value)) {
                $safeValues[$key] = $this->sanitizeValues($value) ?? [];
            } elseif (is_scalar($value) || $value === null) {
                $safeValues[$key] = $value;
            }
        }

        return $safeValues;
    }
}
