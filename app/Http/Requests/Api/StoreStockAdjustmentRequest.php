<?php

namespace App\Http\Requests\Api;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('stock.adjustment') ?? false;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'reason' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.system_quantity' => ['nullable', 'integer'],
            'items.*.actual_quantity' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $warehouseId = (int) ($this->input('warehouse_id') ?? 0);
            $locationId = (int) ($this->input('location_id') ?? 0);

            if ($warehouseId <= 0 || $locationId <= 0) {
                return;
            }

            $belongs = Location::whereKey($locationId)
                ->whereHas('rack.zone', fn ($query) => $query->where('warehouse_id', $warehouseId))
                ->exists();

            if (! $belongs) {
                $validator->errors()->add('location_id', 'Lokasi tidak termasuk warehouse yang dipilih.');
            }
        });
    }
}
