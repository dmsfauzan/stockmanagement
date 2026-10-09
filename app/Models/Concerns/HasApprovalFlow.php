<?php

namespace App\Models\Concerns;

use App\Models\ApprovalHistory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasApprovalFlow
{
    public function approvalHistories(): MorphMany
    {
        return $this->morphMany(ApprovalHistory::class, 'approvable')->orderBy('created_at')->orderBy('id');
    }

    public function approvalProgress(): array
    {
        return [
            'current' => max(0, (int) ($this->current_level ?? 0)),
            'required' => max(1, (int) ($this->required_levels ?? 1)),
        ];
    }

    public function approvalLabel(): string
    {
        $progress = $this->approvalProgress();

        return $progress['current'].'/'.$progress['required'];
    }
}
