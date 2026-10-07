<?php

namespace App\Jobs;

use App\Imports\MasterDataImport;
use App\Services\Support\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportMasterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @param  class-string<MasterDataImport>  $importClass
     */
    public function __construct(
        public string $importClass,
        public string $filePath,
        public int $userId,
        public string $disk = 'local',
        public string $label = 'Data',
        public string $module = 'master',
    ) {}

    public function handle(): void
    {
        /** @var MasterDataImport $import */
        $import = new $this->importClass;

        try {
            Excel::import($import, $this->filePath, $this->disk);
        } catch (\Throwable $e) {
            Log::error('ImportMasterJob failed: '.$e->getMessage());

            NotificationService::notify($this->userId, 'import.completed', 'Import gagal', 'Import '.$this->label.' gagal: '.substr($e->getMessage(), 0, 400));

            throw $e;
        } finally {
            try {
                Storage::disk($this->disk)->delete($this->filePath);
            } catch (\Throwable $e) {
            }
        }

        $message = "Import {$this->label} selesai: {$import->imported} dibuat, {$import->updated} diperbarui";
        $errors = $import->errors;

        if (count($errors) > 0) {
            $message .= ', '.count($errors).' baris gagal: '.implode('; ', array_slice($errors, 0, 5));
        }

        NotificationService::notify(
            $this->userId,
            'import.completed',
            count($errors) > 0 ? 'Import selesai dengan kesalahan' : 'Import selesai',
            $message,
        );

        if (count($errors) > 0) {
            Log::warning('Import completed with errors', ['module' => $this->module, 'errors' => $errors]);
        }
    }
}
