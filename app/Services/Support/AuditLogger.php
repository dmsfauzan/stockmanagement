<?php

namespace App\Services\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    public static function log(string $action, string $module, mixed $auditable = null, ?array $old = null, ?array $new = null): void
    {
        $type = null;
        $id = null;

        if ($auditable instanceof Model) {
            $type = get_class($auditable);
            $id = $auditable->getKey();
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => $module,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    public static function logModel(string $action, mixed $model, ?array $old = null, ?array $new = null, string $module = ''): void
    {
        if ($module === '' && is_object($model)) {
            $module = Str::plural(Str::snake(class_basename($model)));
        }

        static::log($action, $module, $model, $old, $new);
    }
}
