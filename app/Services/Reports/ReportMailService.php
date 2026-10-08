<?php

namespace App\Services\Reports;

use App\Enums\StockStatus;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportMailService
{
    /** @var array<int, string> */
    public const REPORTS = ['stock', 'low', 'movement', 'valuation', 'expiry'];

    /** @var array<int, string> */
    public const PERIODS = ['daily', 'weekly', 'monthly'];

    /**
     * Resolve the date range for a scheduling period.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(string $period): array
    {
        return match ($period) {
            'weekly' => [Carbon::today()->subDays(6)->startOfDay(), Carbon::today()->endOfDay()],
            'monthly' => [Carbon::now()->subMonth()->startOfMonth()->startOfDay(), Carbon::now()->subMonth()->endOfMonth()->endOfDay()],
            default => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
        };
    }

    /**
     * Build a report payload for email.
     *
     * @return array{title:string, columns:array<int,string>, rows:array<int,array<int,mixed>>, summary:array<string,string>, total:int}
     */
    public static function build(string $report, Carbon $from, Carbon $to): array
    {
        $limit = max(1, (int) (Setting::get('reports.mail_limit', 50) ?? 50));

        return match ($report) {
            'low' => self::low($limit),
            'movement' => self::movement($from, $to, $limit),
            'valuation' => self::valuation($limit),
            'expiry' => self::expiry($limit),
            default => self::stock($limit),
        };
    }

    /** @return array<string, mixed> */
    private static function stock(int $limit): array
    {
        $base = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->whereNull('items.deleted_at');

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->select([
                'items.sku', 'items.name as item_name', 'items.minimum_stock as min_stock', 'items.maximum_stock as max_stock',
                'warehouses.name as warehouse_name', 'stock_balances.quantity_on_hand',
            ])
            ->orderBy('items.sku')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                $r->sku,
                $r->item_name,
                $r->warehouse_name,
                (int) $r->quantity_on_hand,
                (int) $r->min_stock,
                StockStatus::evaluate((int) $r->quantity_on_hand, (int) $r->min_stock, (int) $r->max_stock)->label(),
            ])
            ->all();

        return [
            'title' => 'Laporan Stok Saat Ini',
            'columns' => ['SKU', 'Item', 'Warehouse', 'On Hand', 'Min', 'Status'],
            'rows' => $rows,
            'summary' => ['Total baris saldo' => (string) $total],
            'total' => $total,
        ];
    }

    /** @return array<string, mixed> */
    private static function low(int $limit): array
    {
        $base = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->whereNull('items.deleted_at')
            ->whereColumn('stock_balances.quantity_on_hand', '<=', 'items.minimum_stock');

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->select([
                'items.sku', 'items.name as item_name', 'items.minimum_stock as min_stock',
                'warehouses.name as warehouse_name', 'stock_balances.quantity_on_hand',
            ])
            ->orderBy('stock_balances.quantity_on_hand')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                $r->sku,
                $r->item_name,
                $r->warehouse_name,
                (int) $r->quantity_on_hand,
                (int) $r->min_stock,
                ((int) $r->quantity_on_hand <= 0) ? 'Out of stock' : 'Low stock',
            ])
            ->all();

        return [
            'title' => 'Laporan Stok Rendah',
            'columns' => ['SKU', 'Item', 'Warehouse', 'On Hand', 'Min', 'Status'],
            'rows' => $rows,
            'summary' => ['Total item di bawah minimum' => (string) $total],
            'total' => $total,
        ];
    }

    /** @return array<string, mixed> */
    private static function movement(Carbon $from, Carbon $to, int $limit): array
    {
        $base = DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->whereBetween('stock_movements.created_at', [$from, $to]);

        $total = (clone $base)->count();

        $in = (int) (clone $base)->sum('quantity_in');
        $out = (int) (clone $base)->sum('quantity_out');

        $rows = (clone $base)
            ->select([
                'stock_movements.created_at', 'stock_movements.transaction_type',
                'stock_movements.quantity_in', 'stock_movements.quantity_out',
                'items.sku', 'items.name as item_name',
            ])
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                Carbon::parse($r->created_at)->format('d M Y H:i'),
                $r->transaction_type,
                $r->sku.' — '.$r->item_name,
                (int) $r->quantity_in,
                (int) $r->quantity_out,
            ])
            ->all();

        return [
            'title' => 'Laporan Pergerakan Stok',
            'columns' => ['Waktu', 'Tipe', 'Item', 'In', 'Out'],
            'rows' => $rows,
            'summary' => [
                'Total pergerakan' => (string) $total,
                'Total masuk' => (string) $in,
                'Total keluar' => (string) $out,
            ],
            'total' => $total,
        ];
    }

    /** @return array<string, mixed> */
    private static function valuation(int $limit): array
    {
        $base = DB::table('inventory_valuations')
            ->join('warehouses', 'inventory_valuations.warehouse_id', '=', 'warehouses.id');

        $rows = (clone $base)
            ->groupBy('warehouses.id', 'warehouses.name')
            ->selectRaw('warehouses.name as warehouse_name, COALESCE(SUM(inventory_valuations.quantity),0) as qty, COALESCE(SUM(inventory_valuations.total_value),0) as value')
            ->orderByDesc('value')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                $r->warehouse_name,
                number_format((int) $r->qty),
                number_format((float) $r->value, 2, ',', '.'),
            ])
            ->all();

        $grand = (float) DB::table('inventory_valuations')->sum('total_value');

        return [
            'title' => 'Laporan Nilai Persediaan',
            'columns' => ['Warehouse', 'Qty', 'Nilai'],
            'rows' => $rows,
            'summary' => ['Total nilai persediaan' => number_format($grand, 2, ',', '.')],
            'total' => count($rows),
        ];
    }

    /** @return array<string, mixed> */
    private static function expiry(int $limit): array
    {
        $cutoff = Carbon::today()->addDays(30)->toDateString();
        $today = Carbon::today();

        $base = DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->where('stock_movements.transaction_type', 'incoming')
            ->whereNotNull('stock_movements.expiry_date')
            ->whereDate('stock_movements.expiry_date', '<=', $cutoff);

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->select(['items.sku', 'items.name as item_name', 'stock_movements.batch_number', 'stock_movements.expiry_date'])
            ->orderBy('stock_movements.expiry_date')
            ->limit($limit)
            ->get()
            ->map(function ($r) use ($today): array {
                $days = (int) $today->diffInDays(Carbon::parse($r->expiry_date)->startOfDay(), false);

                return [
                    $r->sku,
                    $r->item_name,
                    $r->batch_number ?? '-',
                    Carbon::parse($r->expiry_date)->format('d M Y'),
                    $days < 0 ? 'Lewat '.abs($days).' hari' : 'H-'.$days,
                ];
            })
            ->all();

        return [
            'title' => 'Laporan Batch Mendekati Kedaluwarsa',
            'columns' => ['SKU', 'Item', 'Batch', 'Expiry', 'Sisa'],
            'rows' => $rows,
            'summary' => ['Total batch (≤30 hari)' => (string) $total],
            'total' => $total,
        ];
    }
}
