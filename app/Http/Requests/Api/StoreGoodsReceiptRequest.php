<?php

namespace App\Http\Requests\Api;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('goods_receipt.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'po_number' => ['nullable', 'string', 'max:60'],
            'delivery_note' => ['nullable', 'string', 'max:60'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'received_by' => ['nullable', 'string', 'max:100'],
            'requires_inspection' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.location_id' => ['required', 'exists:locations,id'],
            'items.*.batch_number' => ['nullable', 'string', 'max:60'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $warehouseId = (int) ($this->input('warehouse_id') ?? 0);

            if ($warehouseId <= 0) {
                return;
            }

            foreach ((array) $this->input('items', []) as $index => $row) {
                $locationId = (int) ($row['location_id'] ?? 0);

                if ($locationId <= 0) {
                    continue;
                }

                $belongs = Location::whereKey($locationId)
                    ->whereHas('rack.zone', fn ($query) => $query->where('warehouse_id', $warehouseId))
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add("items.{$index}.location_id", 'Lokasi tidak termasuk warehouse yang dipilih.');
                }
            }
        });
    }
}
