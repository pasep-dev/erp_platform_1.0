<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Auditable business writes must use Eloquent model events. Query-builder bulk writes are
 * reserved for technical counters or require an explicit activity log entry.
 */
class ActivityLogModelEventRecorder
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function created(Model $model): void
    {
        $this->record('CREATE', $model, [], $this->auditValues($model->getAttributes()));
    }

    public function updating(Model $model): void
    {
        $changedAttributes = $this->withoutTimestamps($model->getDirty());

        if ($changedAttributes === []) {
            return;
        }

        $oldValues = [];

        foreach (array_keys($changedAttributes) as $attribute) {
            $oldValues[$attribute] = $model->getRawOriginal($attribute);
        }

        $pendingUpdates = $this->request()->attributes->get('activity_log_pending_updates', []);
        $pendingUpdates[spl_object_id($model)] = $oldValues;

        $this->request()->attributes->set('activity_log_pending_updates', $pendingUpdates);
    }

    public function updated(Model $model): void
    {
        $request = $this->request();
        $pendingUpdates = $request->attributes->get('activity_log_pending_updates', []);
        $oldValues = $pendingUpdates[spl_object_id($model)] ?? [];
        unset($pendingUpdates[spl_object_id($model)]);
        $request->attributes->set('activity_log_pending_updates', $pendingUpdates);

        $newValues = $this->withoutTimestamps($model->getChanges());
        $oldValues = array_intersect_key($oldValues, $newValues);

        if ($newValues === [] || $oldValues === []) {
            return;
        }

        $this->record('UPDATE', $model, $oldValues, $newValues);
    }

    public function deleted(Model $model): void
    {
        $this->record('DELETE', $model, $this->auditValues($model->getAttributes()), []);
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function record(string $action, Model $model, array $oldValues, array $newValues): void
    {
        $request = $this->request();

        if (
            ! $request->attributes->get('activity_log_capture_enabled')
            || $model instanceof ActivityLog
            || $model instanceof Pivot
        ) {
            return;
        }

        $oldValues = $this->activityLogger->sanitizeValues($oldValues) ?? [];
        $newValues = $this->activityLogger->sanitizeValues($newValues) ?? [];
        $label = Str::headline(class_basename($model));
        $identifier = $this->identifierFor($model);
        $requestAction = $request->attributes->get('activity_log_action');
        $action = in_array($requestAction, ['CREATE', 'UPDATE', 'DELETE'], true)
            ? $action
            : ($requestAction ?? $action);

        $request->attributes->add([
            'activity_log_model_changes' => [
                ...$request->attributes->get('activity_log_model_changes', []),
                [
                    'action' => $action,
                    'subject_type' => $model->getMorphClass(),
                    'subject_id' => $identifier,
                    'description' => $this->descriptionFor(
                        $action,
                        $label,
                        $identifier,
                        $oldValues,
                        $newValues,
                    ),
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function auditValues(array $values): array
    {
        return $this->withoutTimestamps($values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutTimestamps(array $values): array
    {
        return array_diff_key($values, array_flip(['created_at', 'updated_at', 'deleted_at']));
    }

    private function identifierFor(Model $model): string|int|null
    {
        foreach (['number', 'document_number', 'document_no', 'code', 'name'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        $key = $model->getKey();

        return is_scalar($key) ? $key : null;
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function descriptionFor(
        string $action,
        string $modelLabel,
        string|int|null $identifier,
        array $oldValues,
        array $newValues,
    ): string {
        $verb = match ($action) {
            'CREATE' => 'Created',
            'UPDATE' => 'Updated',
            'DELETE' => 'Deleted',
            'APPROVE' => 'Approved',
            'REJECT' => 'Rejected',
            'SUBMIT' => 'Submitted',
            'CANCEL' => 'Cancelled',
            default => Str::headline($action),
        };
        $description = "{$verb} {$modelLabel}";

        if ($identifier !== null) {
            $description .= " {$identifier}";
        }

        $changedAttributes = array_keys($newValues);

        if ($changedAttributes !== [] && $action !== 'CREATE') {
            $description .= ' ('.implode(', ', array_map(
                Str::headline(...),
                $changedAttributes,
            )).')';
        }

        if ($oldValues !== [] && $newValues !== []) {
            $changes = [];

            foreach ($newValues as $attribute => $newValue) {
                if (array_key_exists($attribute, $oldValues)) {
                    $changes[] = Str::headline((string) $attribute)
                        .' from '.$this->displayValue($oldValues[$attribute])
                        .' to '.$this->displayValue($newValue);
                }
            }

            if ($changes !== []) {
                $description .= ': '.implode('; ', $changes);
            }
        }

        return Str::limit($description, 500, '');
    }

    private function displayValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = json_encode($value);
        } elseif (! is_scalar($value) && $value !== null) {
            $value = '[value]';
        }

        return '"'.Str::limit((string) ($value ?? '—'), 80, '...').'"';
    }

    private function request(): Request
    {
        return request();
    }
}
