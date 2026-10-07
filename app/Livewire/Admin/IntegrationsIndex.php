<?php

namespace App\Livewire\Admin;

use App\Jobs\SendWebhookJob;
use App\Models\Setting;
use App\Models\WebhookDelivery;
use App\Services\Integration\WebhookService;
use App\Services\Support\AuditLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Integrations')]
class IntegrationsIndex extends Component
{
    use WithPagination;

    public string $webhookSecret = '';

    /** @var array<int, string> */
    public array $events = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);

        $this->events = WebhookService::events();
    }

    public function saveSecret(): void
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);

        $this->validate(['webhookSecret' => ['nullable', 'string', 'max:255']]);

        WebhookService::setSecret(trim($this->webhookSecret));
        AuditLogger::log('update', 'integration', null, null, ['webhook_secret' => 'updated']);
        $this->reset('webhookSecret');

        $this->dispatch('toast', type: 'success', message: 'Webhook secret diperbarui.');
    }

    public function updatedEvents(): void
    {
        Setting::set('integration.webhook_events', implode(',', array_values($this->events)), 'integration');
        $this->dispatch('toast', type: 'success', message: 'Event webhook disimpan.');
    }

    public function sendTest(): void
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);

        $delivery = WebhookService::sendTest();

        if (! $delivery) {
            $this->dispatch('toast', type: 'error', message: 'Isi Webhook URL dulu.');

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Test webhook dikirim (lihat daftar di bawah).');
    }

    public function retry(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);

        $delivery = WebhookDelivery::findOrFail($id);
        SendWebhookJob::dispatch($delivery->id);

        $this->dispatch('toast', type: 'success', message: 'Retry dijadwalkan.');
    }

    public function render()
    {
        return view('livewire.admin.integrations-index', [
            'allEvents' => WebhookService::EVENTS,
            'deliveries' => WebhookDelivery::orderByDesc('created_at')->paginate(15),
            'secretSet' => WebhookService::secret() !== '',
        ]);
    }
}
