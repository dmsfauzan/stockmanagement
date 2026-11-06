<?php

namespace Tests\Feature;

use App\Livewire\Admin\CurrencyIndex;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ExchangeRateSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function fakeProvider(array $rates): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'base_code' => 'IDR',
                'rates' => $rates,
            ]),
        ]);
    }

    public function test_sync_inverts_provider_rate_and_stores_source(): void
    {
        $this->fakeProvider([
            'IDR' => 1,
            'USD' => 0.0000553,
            'EUR' => 0.0000510,
        ]);

        $eur = Currency::create([
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'is_base' => false,
            'is_active' => true,
        ]);

        $this->artisan('currency:sync-rates')->assertExitCode(0);

        $usd = Currency::where('code', 'USD')->firstOrFail();
        $usdRate = ExchangeRate::where('currency_id', $usd->id)->firstOrFail();

        $this->assertEqualsWithDelta(1 / 0.0000553, (float) $usdRate->rate, 0.01);
        $this->assertSame('open.er-api.com', $usdRate->source);
        $this->assertNotNull($usdRate->fetched_at);

        $eurRate = ExchangeRate::where('currency_id', $eur->id)->firstOrFail();
        $this->assertEqualsWithDelta(1 / 0.0000510, (float) $eurRate->rate, 0.01);

        // Base currency tidak diberi baris exchange rate.
        $idr = Currency::where('code', 'IDR')->firstOrFail();
        $this->assertDatabaseMissing('exchange_rates', ['currency_id' => $idr->id]);
    }

    public function test_failed_sync_keeps_previous_rates(): void
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();
        ExchangeRate::create([
            'currency_id' => $usd->id,
            'effective_date' => today(),
            'rate' => 16000,
        ]);

        Http::fake([
            'open.er-api.com/*' => Http::response(['message' => 'down'], 500),
        ]);

        $this->artisan('currency:sync-rates')->assertExitCode(1);

        $this->assertSame(
            16000.0,
            (float) ExchangeRate::where('currency_id', $usd->id)->latest('id')->value('rate'),
        );
    }

    public function test_sync_skips_currencies_missing_from_provider(): void
    {
        $this->fakeProvider(['IDR' => 1, 'USD' => 0.0000553]);

        $eur = Currency::create([
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'is_base' => false,
            'is_active' => true,
        ]);

        $this->artisan('currency:sync-rates')->assertExitCode(0);

        $this->assertDatabaseMissing('exchange_rates', ['currency_id' => $eur->id]);
    }

    public function test_admin_can_trigger_sync_from_livewire(): void
    {
        $this->fakeProvider(['IDR' => 1, 'USD' => 0.0000553]);
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());

        Livewire::test(CurrencyIndex::class)
            ->call('syncRates')
            ->assertDispatched('toast');

        $usd = Currency::where('code', 'USD')->firstOrFail();
        $rate = ExchangeRate::where('currency_id', $usd->id)->firstOrFail();

        $this->assertEqualsWithDelta(1 / 0.0000553, (float) $rate->rate, 0.01);
    }

    public function test_api_exposes_source_and_updated_at(): void
    {
        $this->fakeProvider(['IDR' => 1, 'USD' => 0.0000553]);

        $this->artisan('currency:sync-rates')->assertExitCode(0);

        $token = User::where('email', 'admin@stock.test')->firstOrFail()
            ->createToken('test', ['*'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/currencies')
            ->assertOk()
            ->assertJsonPath('data.base', 'IDR')
            ->assertJsonFragment([
                'code' => 'USD',
                'source' => 'open.er-api.com',
            ])
            ->assertJsonStructure([
                'data' => [
                    'currencies' => [
                        '*' => ['code', 'rate_to_base', 'source', 'updated_at'],
                    ],
                ],
            ]);
    }
}
