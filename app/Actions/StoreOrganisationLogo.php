<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreOrganisationLogo
{
    /**
     * Store an organisation logo on the public disk.
     * Returns the disk-relative path (e.g. organisations/abc.jpg).
     */
    public function handle(UploadedFile $file): string
    {
        return $file->store('organisations', 'public');
    }

    /**
     * Public URL for a stored logo path or legacy absolute URL.
     */
    public function url(?string $logo): ?string
    {
        if (blank($logo)) {
            return null;
        }

        // Legacy invalid / short values
        if (! str_contains($logo, '/') && ! str_contains($logo, '\\')) {
            return null;
        }

        $path = $this->toDiskPath($logo);

        if ($path === null) {
            return null;
        }

        // Prefer request-aware asset URL so it works on any host/port.
        return asset('storage/'.$path);
    }

    /**
     * Delete a previously stored logo by its public URL or disk path.
     */
    public function delete(?string $logo): void
    {
        if (blank($logo)) {
            return;
        }

        $path = $this->toDiskPath($logo);

        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function toDiskPath(string $logo): ?string
    {
        // Already a disk path: organisations/xxx.jpg
        if (str_starts_with($logo, 'organisations/')) {
            return $logo;
        }

        $path = parse_url($logo, PHP_URL_PATH) ?: $logo;
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (! str_starts_with($path, 'organisations/')) {
            return null;
        }

        return $path;
    }
}
