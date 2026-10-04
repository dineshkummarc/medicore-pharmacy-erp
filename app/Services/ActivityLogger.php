<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes the audit trail. Sensitive keys are always stripped from metadata.
 */
class ActivityLogger
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'remember_token', 'token', '_token',
    ];

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?int $userId = null): ActivityLog
    {
        $request = app()->runningInConsole() ? null : request();

        return ActivityLog::query()->create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => Str::limit($description, 250),
            'properties' => $properties === [] ? null : $this->sanitize($properties),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
        ]);
    }

    /**
     * Changed attributes of a model, suitable for an "updated" log entry.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function changes(Model $model): array
    {
        $changes = [];

        foreach (Arr::except($model->getChanges(), ['updated_at']) as $key => $value) {
            $changes[$key] = ['from' => $model->getOriginal($key), 'to' => $value];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }
}
