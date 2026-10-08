<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function csv(array $rows): UploadedFile
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['code', 'name', 'description', 'status']);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);

        $content = stream_get_contents($handle);
        fclose($handle);

        return UploadedFile::fake()->createWithContent('preview.csv', $content);
    }

    public function test_preview_counts_without_persisting(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $file = $this->csv([
            ['IMP-PREV-1', 'Preview Satu', '', 'active'],
            ['IMP-PREV-2', 'Preview Dua', '', 'active'],
            ['', 'Tanpa Kode', '', 'active'],
        ]);

        Livewire::actingAs($admin)->test('master-data.category-index')
            ->set('importFile', $file)
            ->call('analyzeImport')
            ->assertSet('importAnalyzed', true)
            ->assertSet('importedCount', 2)
            ->assertSet('updatedCount', 0);

        $this->assertDatabaseMissing('categories', ['code' => 'IMP-PREV-1']);
        $this->assertDatabaseMissing('categories', ['code' => 'IMP-PREV-2']);
    }

    public function test_confirming_import_after_preview_persists(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $file = $this->csv([['IMP-SAVE-1', 'Simpan Satu', '', 'active']]);

        Livewire::actingAs($admin)->test('master-data.category-index')
            ->set('importFile', $file)
            ->call('analyzeImport')
            ->call('import')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('categories', ['code' => 'IMP-SAVE-1']);
    }

    public function test_preview_counts_existing_as_updates(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $existing = Category::where('code', 'ELEC')->firstOrFail();

        $file = $this->csv([[$existing->code, 'Nama Diperbarui', 'desc', 'active']]);

        Livewire::actingAs($admin)->test('master-data.category-index')
            ->set('importFile', $file)
            ->call('analyzeImport')
            ->assertSet('importedCount', 0)
            ->assertSet('updatedCount', 1);

        $this->assertSame('Electronics', $existing->fresh()->name);
    }
}
