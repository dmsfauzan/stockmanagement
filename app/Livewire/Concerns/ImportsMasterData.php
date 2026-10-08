<?php

namespace App\Livewire\Concerns;

use App\Imports\MasterDataImport;
use App\Jobs\ImportMasterJob;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

trait ImportsMasterData
{
    use WithFileUploads;

    public $importFile = null;

    public bool $showImportModal = false;

    /** @var array<int, string> */
    public array $importErrors = [];

    public int $importedCount = 0;

    public int $updatedCount = 0;

    /** @var array<int, string> */
    public array $importHeadings = [];

    public string $importLabel = '';

    public ?string $importStoredPath = null;

    public bool $importAnalyzed = false;

    /** @return class-string<MasterDataImport> */
    abstract protected function importClass(): string;

    abstract protected function importLabelText(): string;

    abstract protected function importModule(): string;

    abstract protected function importPermission(): string;

    /** @return array<int, string|int|null> */
    abstract protected function importSampleRow(): array;

    public function openImportModal(): void
    {
        abort_unless(auth()->user()->hasPermission($this->importPermission()), 403);

        $this->reset('importFile', 'importErrors', 'importedCount', 'updatedCount', 'importStoredPath', 'importAnalyzed');
        $this->importHeadings = ($this->importClass())::headings();
        $this->importLabel = $this->importLabelText();
        $this->showImportModal = true;
    }

    public function updatedImportFile(): void
    {
        $this->importAnalyzed = false;
        $this->importErrors = [];
        $this->importedCount = 0;
        $this->updatedCount = 0;
    }

    public function analyzeImport(): void
    {
        abort_unless(auth()->user()->hasPermission($this->importPermission()), 403);

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        // Run the real import pipeline inside a transaction, then roll back so
        // the preview exactly matches what a confirmed import would do.
        DB::beginTransaction();

        try {
            $import = new ($this->importClass());
            Excel::import($import, $this->importFile);

            $this->importErrors = $import->errors;
            $this->importedCount = $import->imported;
            $this->updatedCount = $import->updated;
            $this->importAnalyzed = true;

            if (count($this->importErrors) > 5) {
                $this->importErrors = array_slice($this->importErrors, 0, 5);
            }
        } catch (Throwable $e) {
            $this->addError('importFile', mb_substr($e->getMessage(), 0, 400));
        } finally {
            try {
                DB::rollBack();
            } catch (Throwable) {
            }
        }
    }

    public function import(): void
    {
        abort_unless(auth()->user()->hasPermission($this->importPermission()), 403);

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $disk = 'local';
        $extension = $this->importFile->getClientOriginalExtension() ?: 'xlsx';
        $fileName = 'imports/'.uniqid('master_', true).'.'.$extension;
        $storedPath = $this->importFile->storeAs(path: $fileName, options: ['disk' => $disk]);

        if ($storedPath === false || $storedPath === null) {
            $this->dispatch('toast', type: 'error', message: __('Gagal menyimpan file import.'));

            return;
        }

        AuditLogger::log('IMPORT', $this->importModule(), null, null, [
            'queued' => true,
            'file' => $storedPath,
            'class' => $this->importClass(),
        ]);

        ImportMasterJob::dispatch($this->importClass(), $storedPath, (int) auth()->id(), $disk, $this->importLabelText(), $this->importModule());

        $this->reset('importFile', 'importErrors', 'importedCount', 'updatedCount', 'importStoredPath', 'importAnalyzed');
        $this->showImportModal = false;

        $this->dispatch('toast', type: 'success', message: __('Import dijadwalkan — Anda akan menerima notifikasi saat selesai.'));
    }

    public function downloadImportTemplate(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission($this->importPermission()), 403);

        $headings = ($this->importClass())::headings();
        $sample = $this->importSampleRow();

        return response()->streamDownload(function () use ($headings, $sample): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings);

            if ($sample !== []) {
                fputcsv($handle, $sample);
            }

            fclose($handle);
        }, strtolower($this->importLabelText()).'-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}
