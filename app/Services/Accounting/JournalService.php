<?php

namespace App\Services\Accounting;

use App\Enums\TransactionType;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JournalService
{
    /**
     * Resolve the configured account mapping, falling back to sane defaults.
     *
     * @return array<string, string>
     */
    public static function accounts(): array
    {
        return [
            'inventory' => (string) Setting::get('account.inventory', '1300'),
            'cogs' => (string) Setting::get('account.cogs', '5100'),
            'adjustment_gain' => (string) Setting::get('account.adjustment_gain', '4210'),
            'adjustment_loss' => (string) Setting::get('account.adjustment_loss', '5210'),
            'transfer_clearing' => (string) Setting::get('account.transfer_clearing', '1310'),
            'goods_receipt_clearing' => (string) Setting::get('account.goods_receipt_clearing', '2000'),
        ];
    }

    /**
     * Map a transaction type to a [debit account, credit account] pair.
     *
     * @return array{0: string, 1: string}
     */
    public static function mapping(string $transactionType): array
    {
        $a = static::accounts();

        return match ($transactionType) {
            TransactionType::Incoming->value, TransactionType::Opening->value => [$a['inventory'], $a['goods_receipt_clearing']],
            TransactionType::Outgoing->value => [$a['cogs'], $a['inventory']],
            TransactionType::AdjustmentIn->value => [$a['inventory'], $a['adjustment_gain']],
            TransactionType::AdjustmentOut->value => [$a['adjustment_loss'], $a['inventory']],
            TransactionType::TransferOut->value => [$a['transfer_clearing'], $a['inventory']],
            TransactionType::TransferIn->value => [$a['inventory'], $a['transfer_clearing']],
            default => [$a['inventory'], $a['inventory']],
        };
    }

    /**
     * Build journal entries derived from stock movements in the given period.
     *
     * Each movement maps to a single (double-entry) journal line where the
     * debit account equals the credit account amount (total_cost). For an
     * incoming/outgoing movement the value posted is the value already used
     * by the moving-average valuation (stock_movements.total_cost).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function journalRows(?Carbon $from, ?Carbon $to, ?int $warehouseId = null): Collection
    {
        $rows = DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->whereNull('items.deleted_at')
            ->when($from !== null, fn ($query) => $query->where('stock_movements.created_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->where('stock_movements.created_at', '<=', $to))
            ->when($warehouseId !== null, fn ($query) => $query->where('stock_movements.warehouse_id', $warehouseId))
            ->select([
                'stock_movements.id',
                'stock_movements.created_at',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'stock_movements.transaction_type',
                'stock_movements.quantity_in',
                'stock_movements.quantity_out',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
                'stock_movements.notes',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                'warehouses.id as warehouse_id',
            ])
            ->orderBy('stock_movements.created_at')
            ->orderBy('stock_movements.id')
            ->get();

        return $rows->map(function ($row): array {
            [$debit, $credit] = static::mapping((string) $row->transaction_type);

            $quantity = (int) $row->quantity_in > 0 ? (int) $row->quantity_in : (int) $row->quantity_out;

            return [
                'date' => $row->created_at,
                'reference' => $row->reference_type.'#'.$row->reference_id,
                'type' => (string) $row->transaction_type,
                'sku' => $row->sku,
                'item_name' => $row->item_name,
                'warehouse_name' => $row->warehouse_name,
                'warehouse_id' => (int) $row->warehouse_id,
                'quantity' => $quantity,
                'unit_cost' => (float) $row->unit_cost,
                'total_cost' => (float) $row->total_cost,
                'debit_account' => $debit,
                'credit_account' => $credit,
                'description' => (string) ($row->notes ?? ''),
            ];
        });
    }

    /**
     * Compute journal totals. Debits equal credits by construction.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{debits: float, credits: float, net: float, rows: int}
     */
    public static function totals(Collection $rows): array
    {
        $amount = (float) $rows->sum(fn (array $row) => (float) $row['total_cost']);

        return [
            'debits' => $amount,
            'credits' => $amount,
            'net' => 0.0,
            'rows' => $rows->count(),
        ];
    }
}
