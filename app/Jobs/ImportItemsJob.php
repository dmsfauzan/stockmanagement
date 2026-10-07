<?php

namespace App\Jobs;

use App\Imports\ItemsImport;
use App\Services\Support\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ImportItemsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $filePath,
        public int $userId,
        public string $disk = 'local',
    ) {}

    public function handle(): void
    {
        $import = new ItemsImport;

        try {
            Excel::import($import, $this->filePath, $this->disk);
        } catch (\Throwable $e) {
            Log::error('ImportItemsJob failed: '.$e->getMessage());

            NotificationService::notify($this->userId, 'import.completed', 'Import gagal', 'Import barang gagal: '.substr($e->getMessage(), 0, 400), null, null);

            throw $e;
        } finally {
            try {
                \Illuminate\Support\Facades\Storage::disk($this->disk)->delete($this->filePath);
            } catch (\Throwable $e) {
            }
        }

        $message = "Import selesai: {$import->imported} dibuat, {$import->updated} diperbarui";
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
            Log::warning('Import completed with errors', ['errors' => $errors]);
        }
    }
}
