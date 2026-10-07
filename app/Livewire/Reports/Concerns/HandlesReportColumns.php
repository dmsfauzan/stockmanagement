<?php

namespace App\Livewire\Reports\Concerns;

use App\Models\ReportPreference;

trait HandlesReportColumns
{
    /** @var array<int, string> */
    public array $hiddenColumns = [];

    /**
     * Override in the report component: key => [label, align, right?].
     * Keys map to column identifiers used by the partial.
     *
     * @return array<string, array{label: string, align?: string}>
     */
    public function reportColumns(): array
    {
        return [];
    }

    protected function columnsReportKey(): string
    {
        return class_basename(static::class);
    }

    public function mountReportColumns(): void
    {
        $this->hiddenColumns = array_values(array_intersect(
            (array) (ReportPreference::where('user_id', auth()->id())
                ->where('report', $this->columnsReportKey())
                ->value('hidden_columns') ?? []),
            array_keys($this->reportColumns()),
        ));
    }

    public function showColumn(string $key): bool
    {
        return ! in_array($key, $this->hiddenColumns, true);
    }

    public function toggleColumn(string $key): void
    {
        if (! array_key_exists($key, $this->reportColumns())) {
            return;
        }

        if (in_array($key, $this->hiddenColumns, true)) {
            $this->hiddenColumns = array_values(array_diff($this->hiddenColumns, [$key]));
        } else {
            $this->hiddenColumns[] = $key;
        }

        ReportPreference::updateOrCreate(
            ['user_id' => auth()->id(), 'report' => $this->columnsReportKey()],
            ['hidden_columns' => array_values($this->hiddenColumns)],
        );
    }

    public function resetColumns(): void
    {
        $this->hiddenColumns = [];

        ReportPreference::where('user_id', auth()->id())
            ->where('report', $this->columnsReportKey())
            ->delete();
    }

    /** @return array<string, array{label: string, align?: string}> */
    public function visibleColumns(): array
    {
        return array_filter(
            $this->reportColumns(),
            fn (string $key) => $this->showColumn($key),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
