<?php

namespace App\Http\Requests\Api;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! ($user?->hasPermission('transfer.create') ?? false)) {
            return false;
        }

        $from = (int) ($this->input('from_warehouse_id') ?? 0);
        $to = (int) ($this->input('to_warehouse_id') ?? 0);

        foreach ([$from, $to] as $warehouseId) {
            if ($warehouseId > 0 && ! $user->canAccessWarehouse($warehouseId)) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'transfer_date' => ['required', 'date'],
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'from_location_id' => ['required', 'exists:locations,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_location_id' => ['required', 'exists:locations,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $fromWarehouse = (int) ($this->input('from_warehouse_id') ?? 0);
            $fromLocation = (int) ($this->input('from_location_id') ?? 0);
            $toWarehouse = (int) ($this->input('to_warehouse_id') ?? 0);
            $toLocation = (int) ($this->input('to_location_id') ?? 0);

            if ($fromWarehouse > 0 && $fromWarehouse === $toWarehouse && $fromLocation > 0 && $fromLocation === $toLocation) {
                $validator->errors()->add('to_location_id', 'Lokasi asal dan tujuan tidak boleh sama.');
            }

            foreach ([['warehouse' => $fromWarehouse, 'location' => $fromLocation, 'field' => 'from_location_id'], ['warehouse' => $toWarehouse, 'location' => $toLocation, 'field' => 'to_location_id']] as $pair) {
                if ($pair['warehouse'] <= 0 || $pair['location'] <= 0) {
                    continue;
                }

                $belongs = Location::whereKey($pair['location'])
                    ->whereHas('rack.zone', fn ($query) => $query->where('warehouse_id', $pair['warehouse']))
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add($pair['field'], 'Lokasi tidak termasuk warehouse yang dipilih.');
                }
            }
        });
    }
}
