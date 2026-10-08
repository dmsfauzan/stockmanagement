<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;

trait GuardsStaleEdits
{
    /**
     * Snapshot of the record's updated_at when the form was opened for editing.
     */
    public ?string $editUpdatedAt = null;

    protected function captureUpdatedAt(?Model $model): void
    {
        $this->editUpdatedAt = $model?->getAttribute('updated_at')?->toIso8601String();
    }

    protected function isStale(?Model $model): bool
    {
        if ($this->editUpdatedAt === null || $model === null) {
            return false;
        }

        $current = $model->getAttribute('updated_at')?->toIso8601String();

        return $current !== null && $current !== $this->editUpdatedAt;
    }

    /**
     * Detect a concurrent edit and warn the user instead of silently overwriting.
     */
    protected function abortIfStale(?Model $model): bool
    {
        if (! $this->isStale($model)) {
            return false;
        }

        $this->dispatch('toast', type: 'error', message: 'Perubahan tidak disimpan: data telah diubah pengguna lain. Muat ulang halaman lalu coba lagi.');

        return true;
    }
}
