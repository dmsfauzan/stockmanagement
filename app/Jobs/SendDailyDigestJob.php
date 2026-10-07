<?php

namespace App\Jobs;

use App\Mail\DailyDigestMail;
use App\Models\User;
use App\Services\Support\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendDailyDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  array<int, array{title:string, message:string, type:string}>  $items
     */
    public function __construct(
        public int $userId,
        public array $items,
        public string $date,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user || ! $user->isActive()) {
            return;
        }

        if ($this->items === []) {
            return;
        }

        if (! NotificationPreferenceService::isDigestEnabled($user)) {
            return;
        }

        Mail::to($user->email)->send(new DailyDigestMail($this->items, $this->date));
    }
}
