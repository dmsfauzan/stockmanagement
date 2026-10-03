<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public int $updated = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $line = $index + 2;

            $sku = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($sku === '' || $name === '') {
                $this->errors[] = "Baris {$line}: SKU dan Nama wajib diisi.";
                continue;
            }

            $category = Category::where('code', trim((string) ($row['category_code'] ?? '')))->first();
            $unit = Unit::where('code', trim((string) ($row['unit_code'] ?? '')))->first();

            if (! $category || ! $unit) {
                $this->errors[] = "Baris {$line}: kategori/unit (code) tidak ditemukan.";
                continue;
            }

            $supplier = null;
            $supplierCode = trim((string) ($row['supplier_code'] ?? ''));
            if ($supplierCode !== '') {
                $supplier = Supplier::where('code', $supplierCode)->first();
                if (! $supplier) {
                    $this->errors[] = "Baris {$line}: supplier {$supplierCode} tidak ditemukan.";
                    continue;
                }
            }

            $barcode = trim((string) ($row['barcode'] ?? ''));
            $status = in_array($row['status'] ?? 'active', ['active', 'inactive'], true)
                ? $row['status']
                : 'active';

            $data = [
                'name' => $name,
                'barcode' => $barcode !== '' ? $barcode : null,
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'brand' => trim((string) ($row['brand'] ?? '')) ?: null,
                'minimum_stock' => max(0, (int) ($row['minimum_stock'] ?? 0)),
                'maximum_stock' => max(0, (int) ($row['maximum_stock'] ?? 0)),
                'primary_supplier_id' => $supplier?->id,
                'status' => $status,
            ];

            try {
                $existing = Item::where('sku', $sku)->first();

                if ($existing) {
                    $existing->update($data);
                    $this->updated++;
                } else {
                    Item::create(array_merge($data, ['sku' => $sku]));
                    $this->imported++;
                }
            } catch (\Throwable $e) {
                $this->errors[] = "Baris {$line}: ".$e->getMessage();
            }
        }
    }

    /** @return array<int, string> */
    public static function headings(): array
    {
        return ['sku', 'barcode', 'name', 'category_code', 'unit_code', 'brand', 'minimum_stock', 'maximum_stock', 'supplier_code', 'status'];
    }
}
