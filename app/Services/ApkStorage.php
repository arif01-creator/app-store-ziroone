<?php

namespace App\Services;

use App\Models\App;
use App\Models\AppVersion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Single point of contact between the app and wherever APK binaries live.
 *
 * Everything goes through Storage::disk(config('apk.disk')), so pointing
 * APK_DISK at an S3 disk moves the binaries without touching any controller,
 * job, or model.
 */
class ApkStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk(config('apk.disk'));
    }

    /**
     * Store an uploaded APK for a given app + version code, returning the
     * disk-relative path to persist on the app_versions row.
     */
    public function store(UploadedFile $file, App $app, int $versionCode): string
    {
        $directory = trim(config('apk.directory'), '/')."/{$app->id}";

        return $this->disk()->putFileAs($directory, $file, "{$versionCode}.apk");
    }

    public function exists(AppVersion $version): bool
    {
        return $this->disk()->exists($version->apk_path);
    }

    public function size(AppVersion $version): int
    {
        return $this->exists($version) ? (int) $this->disk()->size($version->apk_path) : 0;
    }

    /**
     * Stream the binary to the client. Never exposes the underlying path.
     */
    public function download(AppVersion $version): StreamedResponse
    {
        return $this->disk()->download(
            $version->apk_path,
            $this->downloadFilename($version),
            ['Content-Type' => 'application/vnd.android.package-archive'],
        );
    }

    /**
     * Filename the handset sees, e.g. "ziroone-crm-1.4.2.apk".
     */
    public function downloadFilename(AppVersion $version): string
    {
        $slug = $version->app?->slug ?? 'app';

        return $slug.'-'.Str::slug($version->version_name, '.').'.apk';
    }

    public function delete(AppVersion $version): void
    {
        $this->disk()->delete($version->apk_path);
    }
}
