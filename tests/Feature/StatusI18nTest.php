<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class StatusI18nTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @return array<int, string> */
    private function newStatusKeys(): array
    {
        return [
            'picking', 'picked', 'packed', 'short', 'cancelled', 'partial', 'fulfilled', 'closed',
            'quarantine', 'good', 'recalled', 'expired', 'expiring_30', 'expiring_90', 'valid',
            '0_30', '31_60', '61_90', '90_plus', 'no_receipt', 'disassembly', 'assembly',
        ];
    }

    public function test_status_keys_exist_in_both_locales(): void
    {
        $en = Lang::get('status', [], 'en');
        $id = Lang::get('status', [], 'id');

        foreach ($this->newStatusKeys() as $key) {
            $this->assertArrayHasKey($key, $en, "Missing en status.$key");
            $this->assertArrayHasKey($key, $id, "Missing id status.$key");
        }
    }

    public function test_status_translations_differ_per_locale(): void
    {
        $this->assertSame('Packed', Lang::get('status.packed', [], 'en'));
        $this->assertSame('Dikemas', Lang::get('status.packed', [], 'id'));
        $this->assertSame('Karantina', Lang::get('status.quarantine', [], 'id'));
    }

    public function test_new_pages_render_under_english_locale(): void
    {
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());

        app()->setLocale('en');

        $this->get('/picking')->assertOk();
        $this->get('/stock/quarantine')->assertOk();
        $this->get('/stock/trace')->assertOk();
        $this->get('/reports/inventory-analytics')->assertOk();
    }
}
