<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page' => ['nullable', Rule::in([5, 15, 25, 50, 100])],
        ]);

        $search = trim($filters['search'] ?? '');
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        $logs = ActivityLog::query()
            ->where('action', '!=', 'PAGE_VIEW')
            ->select([
                'id',
                'user_id',
                'action',
                'module',
                'subject_type',
                'subject_id',
                'description',
                'old_values',
                'new_values',
                'ip_address',
                'user_agent',
                'request_id',
                'created_at',
            ])
            ->with('user:id,name,email')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%")
                        ->orWhere('subject_id', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when(($filters['module'] ?? '') !== '', fn (Builder $query) => $query->where('module', $filters['module']))
            ->when(($filters['action'] ?? '') !== '', fn (Builder $query) => $query->where('action', $filters['action']))
            ->when($startDate, fn (Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'module' => $log->module,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'description' => $log->description,
                'old_values' => $this->activityLogger->sanitizeValues($log->old_values),
                'new_values' => $this->activityLogger->sanitizeValues($log->new_values),
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'request_id' => $log->request_id,
                'created_at' => $log->created_at?->toIso8601String(), // Format tanggal aman dikirim ke Vue
                'user' => $log->user
                    ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ]
                    : null,
            ]);

        return Inertia::render('Administrator/ActivityLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'search' => $search,
                'module' => $filters['module'] ?? '',
                'action' => $filters['action'] ?? '',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'per_page' => $perPage,
            ],
            'options' => [
                'modules' => ActivityLog::query()
                    ->whereNotNull('module')
                    ->distinct()
                    ->orderBy('module')
                    ->pluck('module')
                    ->values(),
                'actions' => ActivityLog::query()
                    ->whereNotNull('action')
                    ->where('action', '!=', 'PAGE_VIEW')
                    ->distinct()
                    ->orderBy('action')
                    ->pluck('action')
                    ->values(),
            ],
        ]);
    }

    // ... (method pendukung lainnya di bawah tetap sama)
}
