<?php

namespace App\Imports;

use App\Models\Supplier;

class SupplierImport extends MasterDataImport
{
    /** @return array<int, string> */
    public static function headings(): array
    {
        return ['code', 'name', 'contact_person', 'phone', 'email', 'address', 'status', 'lead_time_days', 'payment_terms', 'region'];
    }

    protected function handleRow(array $row, int $line): void
    {
        $code = $this->str($row, 'code');
        $name = $this->str($row, 'name');

        if ($code === '' || $name === '') {
            throw new \RuntimeException('Kode dan Nama wajib diisi.');
        }

        $status = in_array($this->str($row, 'status'), ['active', 'inactive'], true) ? $this->str($row, 'status') : 'active';
        $leadTime = (int) $this->str($row, 'lead_time_days');

        $this->remember(Supplier::class, $code, [
            'name' => $name,
            'contact_person' => $this->str($row, 'contact_person') ?: null,
            'phone' => $this->str($row, 'phone') ?: null,
            'email' => $this->str($row, 'email') ?: null,
            'address' => $this->str($row, 'address') ?: null,
            'status' => $status,
            'lead_time_days' => $leadTime > 0 ? $leadTime : 7,
            'payment_terms' => $this->str($row, 'payment_terms') ?: 'NET 30',
            'region' => $this->str($row, 'region') ?: null,
        ]);
    }
}
