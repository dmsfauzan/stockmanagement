<?php

namespace App\Livewire\Reports\Concerns;

use App\Models\SavedFilter;

trait HandlesSavedFilters
{
    public string $savedFilterName = '';

    public ?int $savedFilterId = null;

    /**
     * Override in the report component: keys listed here are persisted and
     * can be restored by applySavedFilter(). Keep to public filter props.
     *
     * @return array<int, string>
     */
    protected function filterKeys(): array
    {
        return [
            'search',
            'statusFilter',
            'warehouseFilter',
            'locationFilter',
            'categoryFilter',
            'batchSearch',
            'fromDate',
            'toDate',
        ];
    }

    protected function reportKey(): string
    {
        return class_basename(static::class);
    }

    public function savedFilters(): array
    {
        return SavedFilter::where('user_id', auth()->id())
            ->where('report', $this->reportKey())
            ->orderBy('name')
            ->get(['id', 'name', 'payload'])
            ->all();
    }

    public function saveCurrentFilter(): void
    {
        $name = trim($this->savedFilterName);

        $this->validate([
            'savedFilterName' => ['required', 'string', 'max:80'],
        ]);

        $payload = [];

        foreach ($this->filterKeys() as $key) {
            if (property_exists($this, $key)) {
                $payload[$key] = $this->{$key};
            }
        }

        SavedFilter::updateOrCreate(
            ['user_id' => auth()->id(), 'report' => $this->reportKey(), 'name' => $name],
            ['payload' => $payload],
        );

        $this->savedFilterName = '';

        $this->dispatch('toast', type: 'success', message: 'Filter tersimpan.');
    }

    public function applySavedFilter(int $id): void
    {
        $filter = SavedFilter::where('user_id', auth()->id())
            ->where('report', $this->reportKey())
            ->findOrFail($id);

        $payload = is_array($filter->payload) ? $filter->payload : [];

        foreach ($payload as $key => $value) {
            if (property_exists($this, $key) && ! is_array($value) && ! is_object($value)) {
                $this->{$key} = $value;
            }
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }

        $this->dispatch('toast', type: 'success', message: 'Filter diterapkan: '.$filter->name);
    }

    public function deleteSavedFilter(int $id): void
    {
        SavedFilter::where('user_id', auth()->id())
            ->where('report', $this->reportKey())
            ->whereKey($id)
            ->delete();

        $this->dispatch('toast', type: 'success', message: 'Filter tersimpan dihapus.');
    }
}
