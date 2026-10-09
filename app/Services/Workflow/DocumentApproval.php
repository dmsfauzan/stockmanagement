<?php

namespace App\Services\Workflow;

use App\Models\ApprovalHistory;
use App\Models\CustomerReturn;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class DocumentApproval
{
    /** @var array<class-string, string> */
    public const TYPES = [
        GoodsReceipt::class => 'goods_receipt',
        GoodsIssue::class => 'goods_issue',
        StockAdjustment::class => 'stock_adjustment',
        StockOpname::class => 'stock_opname',
        StockTransfer::class => 'stock_transfer',
        PurchaseOrder::class => 'purchase_order',
        SalesOrder::class => 'sales_order',
        CustomerReturn::class => 'customer_return',
        SupplierReturn::class => 'supplier_return',
    ];

    public static function typeFor(Model $doc): string
    {
        return self::TYPES[$doc::class] ?? strtolower(class_basename($doc));
    }

    public static function enabled(): bool
    {
        try {
            return (string) Setting::get('approval.enabled', config('approval.enabled', false) ? '1' : '0') === '1';
        } catch (\Throwable) {
            return (bool) config('approval.enabled', false);
        }
    }

    public static function makerChecker(): bool
    {
        try {
            return (string) Setting::get('approval.maker_checker', config('approval.maker_checker', true) ? '1' : '0') === '1';
        } catch (\Throwable) {
            return (bool) config('approval.maker_checker', true);
        }
    }

    /** @return array<int, array{level:int, role:string, min_total:float}> */
    public static function levelsFor(string $type): array
    {
        $configured = null;

        try {
            $json = Setting::get('approval.flows', null);

            if (is_string($json) && $json !== '') {
                $decoded = json_decode($json, true);

                if (is_array($decoded) && isset($decoded[$type]) && is_array($decoded[$type]) && $decoded[$type] !== []) {
                    $configured = $decoded[$type];
                }
            }
        } catch (\Throwable) {
        }

        $levels = $configured ?? config('approval.flows.'.$type) ?? null;

        if (! is_array($levels) || $levels === []) {
            return [['level' => 1, 'role' => 'supervisor', 'min_total' => 0.0]];
        }

        usort($levels, fn ($a, $b) => (int) ($a['level'] ?? 0) <=> (int) ($b['level'] ?? 0));

        return array_values(array_map(fn ($l) => [
            'level' => (int) ($l['level'] ?? 1),
            'role' => (string) ($l['role'] ?? 'supervisor'),
            'min_total' => (float) ($l['min_total'] ?? 0),
        ], $levels));
    }

    public static function totalFor(Model $doc): float
    {
        return match (true) {
            $doc instanceof PurchaseOrder, $doc instanceof SalesOrder => (float) $doc->items->sum(fn ($r) => (float) $r->quantity * (float) $r->unit_price),
            $doc instanceof GoodsReceipt => (float) $doc->receiptItems->sum(fn ($r) => (float) $r->quantity * (float) ($r->unit_cost ?? 0)),
            $doc instanceof GoodsIssue => (float) $doc->issueItems->sum(fn ($r) => (float) $r->quantity * (float) ($r->item?->cost ?? 0)),
            $doc instanceof StockAdjustment, $doc instanceof StockOpname => (float) $doc->items->sum(fn ($r) => abs((int) $r->difference) * (float) ($r->item?->cost ?? 0)),
            $doc instanceof StockTransfer => (float) $doc->items->sum(fn ($r) => (float) $r->quantity * (float) ($r->item?->cost ?? 0)),
            $doc instanceof CustomerReturn, $doc instanceof SupplierReturn => (float) $doc->items->sum(fn ($r) => (float) $r->quantity * (float) ($r->unit_cost ?? $r->item?->cost ?? 0)),
            default => 0.0,
        };
    }

    public static function requiredLevelsFor(Model $doc): int
    {
        if (! static::enabled()) {
            return 1;
        }

        $total = static::totalFor($doc);
        $required = 0;

        foreach (static::levelsFor(static::typeFor($doc)) as $level) {
            if ($total >= (float) $level['min_total']) {
                $required++;
            }
        }

        return max(1, $required);
    }

    /** Snapshot total value + required levels when a document is submitted. */
    public static function snapshot(Model $doc): void
    {
        $doc->forceFill([
            'approval_total' => static::totalFor($doc),
            'required_levels' => static::requiredLevelsFor($doc),
            'current_level' => 0,
        ])->save();
    }

    /** @return array{current:int, required:int} */
    public static function progress(Model $doc): array
    {
        return [
            'current' => max(0, (int) ($doc->current_level ?? 0)),
            'required' => max(1, (int) ($doc->required_levels ?? 1)),
        ];
    }

    /** @return array{level:int, role:string, min_total:float} */
    public static function nextLevelDef(Model $doc): array
    {
        $levels = static::levelsFor(static::typeFor($doc));
        $level = ((int) ($doc->current_level ?? 0)) + 1;
        $index = min(max(0, $level - 1), count($levels) - 1);

        return $levels[$index];
    }

    public static function nextRole(Model $doc): string
    {
        return static::nextLevelDef($doc)['role'];
    }

    public static function assertCanApprove(Model $doc, ?User $user = null): void
    {
        if (! static::enabled()) {
            return;
        }

        $user = $user ?? auth()->user();

        if (! $user) {
            throw new RuntimeException('Tidak ada pengguna yang meng-approve.');
        }

        $level = ((int) ($doc->current_level ?? 0)) + 1;
        $role = static::nextLevelDef($doc)['role'];
        $isAdmin = method_exists($user, 'hasRole') && $user->hasRole('admin');

        if (! $isAdmin && (! method_exists($user, 'hasRole') || ! $user->hasRole($role))) {
            throw new RuntimeException("Approval level {$level} memerlukan role: {$role}.");
        }

        if (static::makerChecker()) {
            if ((int) $doc->created_by === (int) $user->getKey()) {
                throw new RuntimeException('Pembuat dokumen tidak dapat menyetujui dokumennya sendiri.');
            }

            $prior = ApprovalHistory::query()
                ->where('approvable_type', $doc::class)
                ->where('approvable_id', $doc->getKey())
                ->where('action', 'approved')
                ->pluck('user_id')
                ->all();

            if (in_array((int) $user->getKey(), array_map('intval', $prior), true)) {
                throw new RuntimeException('Anda sudah menyetujui level sebelumnya untuk dokumen ini.');
            }
        }
    }

    public static function canApprove(Model $doc, ?User $user = null): bool
    {
        try {
            static::assertCanApprove($doc, $user ?? auth()->user());

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Record an approval. Returns true when the document reached its final
     * level (and status was set to approved), false when more levels remain.
     */
    public static function approve(Model $doc, ?User $user = null): bool
    {
        static::assertCanApprove($doc, $user);

        $user = $user ?? auth()->user();
        $level = ((int) ($doc->current_level ?? 0)) + 1;
        $required = max(1, (int) ($doc->required_levels ?? 1));

        ApprovalHistory::create([
            'approvable_type' => $doc::class,
            'approvable_id' => $doc->getKey(),
            'level' => $level,
            'user_id' => $user?->getKey(),
            'action' => 'approved',
            'created_at' => now(),
        ]);

        if ($level >= $required) {
            $doc->forceFill([
                'current_level' => $level,
                'status' => 'approved',
                'approved_by' => $user?->getKey(),
                'approved_at' => now(),
            ])->save();

            return true;
        }

        $doc->forceFill(['current_level' => $level])->save();

        return false;
    }

    public static function reject(Model $doc, string $reason, ?User $user = null): void
    {
        static::assertCanApprove($doc, $user);

        $user = $user ?? auth()->user();
        $level = ((int) ($doc->current_level ?? 0)) + 1;

        ApprovalHistory::create([
            'approvable_type' => $doc::class,
            'approvable_id' => $doc->getKey(),
            'level' => $level,
            'user_id' => $user?->getKey(),
            'action' => 'rejected',
            'notes' => $reason,
            'created_at' => now(),
        ]);

        $doc->forceFill([
            'status' => 'rejected',
            'rejected_by' => $user?->getKey(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();
    }
}
