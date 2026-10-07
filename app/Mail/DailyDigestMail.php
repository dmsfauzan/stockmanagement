<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array{title:string, message:string, type:string}> $items
     */
    public function __construct(
        public array $items,
        public string $date,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ringkasan Harian Gudang — '.$this->date,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.digest',
            with: [
                'items' => $this->items,
                'date' => $this->date,
                'appUrl' => config('app.url'),
                'appName' => config('app.name', 'Warehouse Stock Management'),
            ],
        );
    }
}
