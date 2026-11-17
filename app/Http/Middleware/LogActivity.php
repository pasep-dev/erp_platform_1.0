<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->getAuthIdentifier();
        $route = $request->route();
        $routeName = $route?->getName() ?? $request->method().' '.$request->path();
        $action = $this->actionFor($request, $route?->getActionMethod(), $routeName);
        $subject = collect($route?->parameters() ?? [])
            ->first(fn (mixed $parameter): bool => $parameter instanceof Model);
        $subjectValuesBefore = $subject?->getAttributes();

        if ($userId !== null && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $request->attributes->set('activity_log_capture_enabled', true);
            $request->attributes->set('activity_log_action', $action);
        }

        $response = $next($request);

        if (
            $userId === null
            || $request->attributes->get('activity_log_recorded')
            || $response->getStatusCode() >= 400
            || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
        ) {
            return $response;
        }

        $module = $this->moduleFor($routeName);
        $modelChanges = $request->attributes->get('activity_log_model_changes', []);

        if ($modelChanges !== []) {
            foreach ($modelChanges as $change) {
                $this->activityLogger->log(
                    action: $change['action'],
                    module: $module,
                    description: $change['description'],
                    subjectType: $change['subject_type'],
                    subjectId: $change['subject_id'],
                    oldValues: $change['old_values'] ?: null,
                    newValues: $change['new_values'] ?: null,
                    request: $request,
                );
            }

            return $response;
        }

        $requestValues = $request->except(['_method']);
        $oldValues = null;
        $newValues = null;

        if ($action === 'CREATE') {
            $newValues = $requestValues;
        } elseif ($action === 'DELETE') {
            $oldValues = $subjectValuesBefore;
        } elseif ($subject instanceof Model) {
            $subjectAfter = $subject->fresh();

            if ($subjectAfter instanceof Model) {
                $subject = $subjectAfter;
            }

            [$oldValues, $newValues] = $this->changedValues(
                $subjectValuesBefore ?? [],
                $subject->getAttributes(),
            );
        } elseif (in_array($action, ['ACTION', 'UPDATE'], true)) {
            $newValues = $requestValues;
        }

        $this->activityLogger->log(
            action: $action,
            module: $module,
            description: $this->descriptionFor(
                $action,
                $routeName,
                $subject,
                $oldValues,
                $newValues,
            ),
            subjectType: $subject?->getMorphClass(),
            subjectId: $subject ? $this->subjectIdentifier($subject) : null,
            oldValues: $oldValues,
            newValues: $newValues,
            request: $request,
        );

        return $response;
    }

    private function actionFor(Request $request, ?string $routeAction, string $routeName): string
    {
        $endpointAction = $routeAction && $routeAction !== '__invoke'
            ? Str::snake($routeAction)
            : Str::afterLast($routeName, '.');

        if ($request->method() === 'POST') {
            return match ($endpointAction) {
                'store', 'create' => 'CREATE',
                'update' => 'UPDATE',
                'destroy', 'delete' => 'DELETE',
                'approve', 'approved' => 'APPROVE',
                'reject', 'rejected' => 'REJECT',
                'submit', 'submitted' => 'SUBMIT',
                'cancel', 'cancelled', 'canceled' => 'CANCEL',
                'restore' => 'RESTORE',
                'archive' => 'ARCHIVE',
                'activate' => 'ACTIVATE',
                'deactivate' => 'DEACTIVATE',
                'close' => 'CLOSE',
                'reopen' => 'REOPEN',
                'receive' => 'RECEIVE',
                'issue' => 'ISSUE',
                'return' => 'RETURN',
                'complete' => 'COMPLETE',
                'release' => 'RELEASE',
                'confirm' => 'CONFIRM',
                'assign' => 'ASSIGN',
                'revoke' => 'REVOKE',
                default => preg_match('/^[a-z][a-z0-9_]*$/', $endpointAction)
                    ? Str::upper($endpointAction)
                    : 'ACTION',
            };
        }

        return match ($request->method()) {
            'PUT', 'PATCH' => 'UPDATE',
            'DELETE' => 'DELETE',
            default => 'ACTION',
        };
    }

    private function moduleFor(string $routeName): string
    {
        $segments = explode('.', $routeName);
        $moduleSegments = in_array(
            $segments[0],
            ['administrator', 'master', 'operational', 'transaction'],
            true,
        )
            ? array_slice($segments, 0, 2)
            : array_slice($segments, 0, 1);

        return Str::headline(implode(' ', $moduleSegments));
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function changedValues(array $oldValues, array $newValues): array
    {
        $ignoredAttributes = array_flip(['created_at', 'updated_at', 'deleted_at']);
        $oldValues = array_diff_key($oldValues, $ignoredAttributes);
        $newValues = array_diff_key($newValues, $ignoredAttributes);
        $changedValues = [];

        foreach ($newValues as $attribute => $newValue) {
            if (! array_key_exists($attribute, $oldValues) || $oldValues[$attribute] !== $newValue) {
                $changedValues[$attribute] = $newValue;
            }
        }

        return [
            array_intersect_key($oldValues, $changedValues),
            $changedValues,
        ];
    }

    private function subjectIdentifier(Model $subject): string|int|null
    {
        foreach (['number', 'document_number', 'document_no', 'code', 'name'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        $key = $subject->getKey();

        return is_scalar($key) ? $key : null;
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function descriptionFor(
        string $action,
        string $routeName,
        ?Model $subject,
        ?array $oldValues,
        ?array $newValues,
    ): string {
        $verb = match ($action) {
            'CREATE' => 'Created',
            'UPDATE' => 'Updated',
            'DELETE' => 'Deleted',
            default => Str::headline($action),
        };
        $routeSegments = explode('.', $routeName);

        if (count($routeSegments) > 1) {
            array_pop($routeSegments);
        }

        $description = $subject
            ? "{$verb} ".Str::headline(class_basename($subject))
                .($this->subjectIdentifier($subject) !== null ? ' '.$this->subjectIdentifier($subject) : '')
            : "{$verb} ".Str::headline(implode(' ', $routeSegments));
        $safeOldValues = $this->activityLogger->sanitizeValues($oldValues) ?? [];
        $safeNewValues = $this->activityLogger->sanitizeValues($newValues) ?? [];
        $changes = [];

        foreach ($safeNewValues as $attribute => $newValue) {
            if (array_key_exists($attribute, $safeOldValues)) {
                $changes[] = Str::headline((string) $attribute)
                    .' from "'.$this->displayValue($safeOldValues[$attribute]).'"'
                    .' to "'.$this->displayValue($newValue).'"';
            }
        }

        if ($changes !== []) {
            $description .= ': '.implode('; ', $changes);
        }

        return Str::limit($description.'.', 500, '');
    }

    private function displayValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = json_encode($value);
        } elseif (! is_scalar($value) && $value !== null) {
            $value = '[value]';
        }

        return Str::limit((string) ($value ?? '—'), 80, '...');
    }
}
