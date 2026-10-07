<?php

namespace App\Jobs;

use App\Mail\NotificationMail;
use App\Models\User;
use App\Services\Support\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public string $type,
        public string $title,
        public string $message,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user || ! $user->isActive()) {
            return;
        }

        if (! NotificationPreferenceService::isEmailEnabled($user, $this->type)) {
            return;
        }

        Mail::to($user->email)->send(new NotificationMail($this->title, $this->message, $this->type));
    }
}
