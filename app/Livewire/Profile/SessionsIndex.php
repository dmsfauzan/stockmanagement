<?php

namespace App\Livewire\Profile;

use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sesi Aktif')]
class SessionsIndex extends Component
{
    public function logoutOthers(): void
    {
        $currentId = session()->getId();

        DB::table('sessions')
            ->where('user_id', auth()->id())
            ->where('id', '!=', $currentId)
            ->delete();

        AuditLogger::log('LOGOUT_OTHERS', 'session', auth()->user());

        $this->dispatch('toast', type: 'success', message: 'Sesi lain berhasil dilogout.');
    }

    public function render()
    {
        $sessions = DB::table('sessions')
            ->where('user_id', auth()->id())
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'current' => $row->id === session()->getId(),
                    'ip' => $row->ip_address,
                    'agent' => $row->user_agent ?? '—',
                    'last' => \Illuminate\Support\Carbon::createFromTimestamp((int) $row->last_activity),
                ];
            });

        return view('livewire.profile.sessions-index', [
            'sessions' => $sessions,
        ]);
    }
}
