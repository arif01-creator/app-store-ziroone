<?php

namespace App\Http\Controllers\Api;

use App\Services\ApkStorage;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VersionController extends ApiController
{
    public function __construct(private readonly ApkStorage $apks) {}

    /**
     * The build clients should be running right now.
     */
    public function latest(string $apiKey): JsonResponse
    {
        $app = $this->resolveApp($apiKey);
        $latest = $app->latestActiveVersion()->first();

        if ($latest === null) {
            return response()->json([
                'message' => 'No active version has been published for this app.',
            ], 404);
        }

        return response()->json([
            'version_name' => $latest->version_name,
            'version_code' => $latest->version_code,
            'release_notes' => $latest->release_notes,
            'is_force_update' => $latest->is_force_update,
            'file_size' => $latest->file_size,
            'download_url' => route('api.versions.download', [
                'api_key' => $app->api_key,
                'version_code' => $latest->version_code,
            ]),
        ]);
    }

    /**
     * Stream one APK. The binary is never reachable by any other path — there
     * is no public symlink into the APK directory.
     */
    public function download(string $apiKey, int $versionCode): StreamedResponse
    {
        $app = $this->resolveApp($apiKey);

        $version = $app->versions()
            ->where('version_code', $versionCode)
            ->where('is_active', true)
            ->first();

        // A version code that doesn't belong to this app is a mismatch, not a
        // missing page — same 403 as a bad key, so probing tells you nothing.
        abort_if($version === null, 403, 'That version is not available for this app.');
        abort_unless($this->apks->exists($version), 404, 'The APK file is missing from storage.');

        $version->setRelation('app', $app);

        return $this->apks->download($version);
    }
}
