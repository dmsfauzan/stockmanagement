<?php

namespace App\Imports;

use App\Models\Category;

class CategoryImport extends MasterDataImport
{
    /** @return array<int, string> */
    public static function headings(): array
    {
        return ['code', 'name', 'description', 'status'];
    }

    protected function handleRow(array $row, int $line): void
    {
        $code = $this->str($row, 'code');
        $name = $this->str($row, 'name');

        if ($code === '' || $name === '') {
            throw new \RuntimeException('Kode dan Nama wajib diisi.');
        }

        $status = in_array($this->str($row, 'status'), ['active', 'inactive'], true) ? $this->str($row, 'status') : 'active';

        $this->remember(Category::class, $code, [
            'name' => $name,
            'description' => $this->str($row, 'description') ?: null,
            'status' => $status,
        ]);
    }
}
