<?php

namespace App\Services\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    public const MAX_KB = 2048;

    public function __construct(
        protected string $disk = 'public',
    ) {}

    /**
     * Store an uploaded image on the public disk and return the relative path.
     * Also generates a 256px thumbnail beside it ({name}_thumb.{ext}).
     */
    public function store(UploadedFile $file, string $folder): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = uniqid('img_', true).'.'.($extension !== '' ? $extension : 'jpg');
        $path = trim($folder, '/').'/'.$filename;

        Storage::disk($this->disk)->putFileAs($folder, $file, $filename);
        $this->makeThumbnail($path);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        try {
            Storage::disk($this->disk)->delete($path);
            Storage::disk($this->disk)->delete($this->thumbPath($path));
        } catch (\Throwable $e) {
        }
    }

    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        try {
            if (! Storage::disk($this->disk)->exists($path)) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return asset('storage/'.$path);
    }

    public function thumbUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $thumb = $this->thumbPath($path);

        try {
            if (Storage::disk($this->disk)->exists($thumb)) {
                return asset('storage/'.$thumb);
            }
        } catch (\Throwable $e) {
        }

        return $this->url($path);
    }

    protected function thumbPath(string $path): string
    {
        return preg_replace('/\.([a-zA-Z0-9]+)$/', '_thumb.$1', $path) ?? $path;
    }

    protected function makeThumbnail(string $path, int $size = 256): void
    {
        try {
            $source = imagecreatefromstring(Storage::disk($this->disk)->get($path));
        } catch (\Throwable $e) {
            return;
        }

        if ($source === false) {
            return;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);
            return;
        }

        $scale = min($size / $width, $size / $height, 1.0);
        $thumbWidth = max(1, (int) round($width * $scale));
        $thumbHeight = max(1, (int) round($height * $scale));

        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['png', 'gif', 'webp'], true)) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefill($thumb, 0, 0, $transparent);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
        imagedestroy($source);

        ob_start();

        match ($extension) {
            'png' => imagepng($thumb),
            'gif' => imagegif($thumb),
            'webp' => function_exists('imagewebp') ? imagewebp($thumb, null, 85) : imagejpeg($thumb, null, 85),
            default => imagejpeg($thumb, null, 85),
        };

        $data = ob_get_clean();

        if (is_string($data)) {
            Storage::disk($this->disk)->put($this->thumbPath($path), $data);
        }

        imagedestroy($thumb);
    }
}
