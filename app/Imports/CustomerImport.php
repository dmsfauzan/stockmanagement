<?php

namespace App\Imports;

use App\Models\Customer;

class CustomerImport extends MasterDataImport
{
    /** @return array<int, string> */
    public static function headings(): array
    {
        return ['code', 'name', 'type', 'contact_person', 'phone', 'email', 'address', 'status'];
    }

    protected function handleRow(array $row, int $line): void
    {
        $code = $this->str($row, 'code');
        $name = $this->str($row, 'name');

        if ($code === '' || $name === '') {
            throw new \RuntimeException('Kode dan Nama wajib diisi.');
        }

        $type = in_array($this->str($row, 'type'), ['customer', 'department'], true) ? $this->str($row, 'type') : 'customer';
        $status = in_array($this->str($row, 'status'), ['active', 'inactive'], true) ? $this->str($row, 'status') : 'active';

        $this->remember(Customer::class, $code, [
            'name' => $name,
            'type' => $type,
            'contact_person' => $this->str($row, 'contact_person') ?: null,
            'phone' => $this->str($row, 'phone') ?: null,
            'email' => $this->str($row, 'email') ?: null,
            'address' => $this->str($row, 'address') ?: null,
            'status' => $status,
        ]);
    }
}
