<?php

namespace App\Imports;

use App\Models\Unit;

class UnitImport extends MasterDataImport
{
    /** @return array<int, string> */
    public static function headings(): array
    {
        return ['code', 'name'];
    }

    protected function handleRow(array $row, int $line): void
    {
        $code = $this->str($row, 'code');
        $name = $this->str($row, 'name');

        if ($code === '' || $name === '') {
            throw new \RuntimeException('Kode dan Nama wajib diisi.');
        }

        $this->remember(Unit::class, $code, ['name' => $name]);
    }
}
