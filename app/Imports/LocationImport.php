<?php

namespace App\Imports;

use App\Models\Location;
use App\Models\Rack;

class LocationImport extends MasterDataImport
{
    /** @return array<int, string> */
    public static function headings(): array
    {
        return ['warehouse_code', 'zone_code', 'rack_code', 'code', 'name'];
    }

    protected function handleRow(array $row, int $line): void
    {
        $code = $this->str($row, 'code');
        $name = $this->str($row, 'name');
        $warehouseCode = $this->str($row, 'warehouse_code');
        $zoneCode = $this->str($row, 'zone_code');
        $rackCode = $this->str($row, 'rack_code');

        if ($code === '' || $name === '') {
            throw new \RuntimeException('Kode dan Nama wajib diisi.');
        }

        $rack = Rack::query()
            ->where('code', $rackCode)
            ->whereHas('zone', function ($query) use ($zoneCode, $warehouseCode): void {
                $query->where('code', $zoneCode)
                    ->whereHas('warehouse', fn ($warehouse) => $warehouse->where('code', $warehouseCode));
            })
            ->first();

        if (! $rack) {
            throw new \RuntimeException("Rack/Zone/Warehouse ({$warehouseCode}/{$zoneCode}/{$rackCode}) tidak ditemukan.");
        }

        $this->remember(Location::class, $code, [
            'rack_id' => $rack->id,
            'name' => $name,
        ]);
    }
}
