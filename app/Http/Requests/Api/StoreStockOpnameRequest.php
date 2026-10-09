<?php

namespace App\Http\Requests\Api;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! ($user?->hasPermission('stock_opname.create') ?? false)) {
            return false;
        }

        $warehouseId = (int) ($this->input('warehouse_id') ?? 0);

        return $warehouseId <= 0 || $user->canAccessWarehouse($warehouseId);
    }

    public function rules(): array
    {
        return [
            'opname_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.system_quantity' => ['required', 'integer', 'min:0'],
            'items.*.physical_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
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
