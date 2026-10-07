<?php

namespace App\Imports;

use App\Models\Warehouse;

class WarehouseImport extends MasterDataImport
{
    /** @return array<int, string> */
    public static function headings(): array
    {
        return ['code', 'name', 'address', 'status'];
    }

    protected function handleRow(array $row, int $line): void
    {
        $code = $this->str($row, 'code');
        $name = $this->str($row, 'name');

        if ($code === '' || $name === '') {
            throw new \RuntimeException('Kode dan Nama wajib diisi.');
        }

        $status = in_array($this->str($row, 'status'), ['active', 'inactive'], true) ? $this->str($row, 'status') : 'active';

        $this->remember(Warehouse::class, $code, [
            'name' => $name,
            'address' => $this->str($row, 'address') ?: null,
            'status' => $status,
        ]);
    }
}
