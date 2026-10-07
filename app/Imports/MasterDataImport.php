<?php

namespace App\Imports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

abstract class MasterDataImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public int $updated = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $line = $index + 2;

            try {
                $this->handleRow($row instanceof Collection ? $row->toArray() : (array) $row, $line);
            } catch (\Throwable $e) {
                $this->errors[] = "Baris {$line}: ".$e->getMessage();
            }
        }
    }

    abstract protected function handleRow(array $row, int $line): void;

    /** @return array<int, string> */
    abstract public static function headings(): array;

    protected function str(array $row, string $key): string
    {
        return trim((string) ($row[$key] ?? ''));
    }

    /**
     * Upsert a master record keyed by its unique `code` column.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $attributes
     */
    protected function remember(string $modelClass, string $code, array $attributes): void
    {
        $existing = $modelClass::query()->where('code', $code)->first();

        if ($existing) {
            $existing->update($attributes);
            $this->updated++;

            return;
        }

        $modelClass::query()->create(array_merge($attributes, ['code' => $code]));
        $this->imported++;
    }
}
