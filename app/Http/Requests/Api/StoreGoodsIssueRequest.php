<?php

namespace App\Http\Requests\Api;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! ($user?->hasPermission('goods_issue.create') ?? false)) {
            return false;
        }

        $warehouseId = (int) ($this->input('warehouse_id') ?? 0);

        return $warehouseId <= 0 || $user->canAccessWarehouse($warehouseId);
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'destination' => ['required', 'string', 'max:150'],
            'sales_order_number' => ['nullable', 'string', 'max:60'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'issued_by' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.location_id' => ['required', 'exists:locations,id'],
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
