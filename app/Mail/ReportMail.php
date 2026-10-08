<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $report,
        public string $periodLabel,
        public string $fromLabel,
        public string $toLabel,
        /** @var array{title:string, columns:array<int,string>, rows:array<int,array<int,mixed>>, summary:array<string,string>, total:int} */
        public array $payload,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->payload['title'].' — '.$this->periodLabel.' ('.$this->fromLabel.' s/d '.$this->toLabel.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.report',
            with: [
                'title' => $this->payload['title'],
                'periodLabel' => $this->periodLabel,
                'fromLabel' => $this->fromLabel,
                'toLabel' => $this->toLabel,
                'columns' => $this->payload['columns'],
                'rows' => $this->payload['rows'],
                'summary' => $this->payload['summary'],
                'total' => $this->payload['total'],
                'appUrl' => config('app.url'),
                'appName' => config('app.name', 'Warehouse Stock Management'),
            ],
        );
    }
}
