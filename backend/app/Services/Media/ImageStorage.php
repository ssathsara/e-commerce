<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores validated uploads on the "public" disk. Files are saved under a random
 * name whose extension is derived from the detected MIME type (never from the
 * client-supplied filename), so uploads cannot choose their own path or extension.
 */
class ImageStorage
{
    private const DISK = 'public';

    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        return $path ? Storage::disk(self::DISK)->url($path) : null;
    }
}
