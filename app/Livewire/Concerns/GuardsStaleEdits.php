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
        $updated = $model?->getAttribute('updated_at');
        $this->editUpdatedAt = $updated?->getTimestamp() !== null ? (string) $updated->getTimestamp() : null;
    }

    protected function isStale(?Model $model): bool
    {
        if ($this->editUpdatedAt === null || $model === null) {
            return false;
        }

        $updated = $model->getAttribute('updated_at');

        if ($updated === null) {
            return false;
        }

        return (string) $updated->getTimestamp() !== $this->editUpdatedAt;
    }

    /**
     * Detect a concurrent edit and warn the user instead of silently overwriting.
     */
    protected function abortIfStale(?Model $model): bool
    {
        if (! $this->isStale($model)) {
            return false;
        }

        $this->dispatch('toast', type: 'error', message: __('Perubahan tidak disimpan: data telah diubah pengguna lain. Muat ulang halaman lalu coba lagi.'));

        return true;
    }
}
