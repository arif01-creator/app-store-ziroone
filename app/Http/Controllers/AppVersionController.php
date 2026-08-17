<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppVersionRequest;
use App\Jobs\NotifyDevicesOfUpdate;
use App\Models\App;
use App\Models\AppVersion;
use App\Services\ApkStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AppVersionController extends Controller
{
    public function __construct(private readonly ApkStorage $apks) {}

    public function store(StoreAppVersionRequest $request, App $app): RedirectResponse
    {
        $file = $request->file('apk');
        $versionCode = (int) $request->validated('version_code');

        // Write the binary first so a failed DB insert can't leave a row
        // pointing at a file that was never stored.
        $path = $this->apks->store($file, $app, $versionCode);

        try {
            $version = DB::transaction(fn () => $app->versions()->create([
                'version_name' => $request->validated('version_name'),
                'version_code' => $versionCode,
                'apk_path' => $path,
                'file_size' => $file->getSize(),
                'release_notes' => $request->validated('release_notes'),
                'is_force_update' => $request->boolean('is_force_update'),
                'is_active' => true,
            ]));
        } catch (Throwable $e) {
            $this->apks->disk()->delete($path);

            throw $e;
        }

        $targeted = $app->devices()->pushable()->count();

        NotifyDevicesOfUpdate::dispatch($version);

        return redirect()
            ->route('apps.show', $app)
            ->with('success', $targeted > 0
                ? "Version {$version->version_name} uploaded. Push queued for {$targeted} ".str('device')->plural($targeted).'.'
                : "Version {$version->version_name} uploaded. No devices are registered for push yet.");
    }

    /**
     * Pull or restore a build. A bad build is deactivated rather than deleted
     * so the version history and its push log stay intact.
     */
    public function update(Request $request, App $app, AppVersion $version): RedirectResponse
    {
        abort_unless($version->app_id === $app->id, 404);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $version->update(['is_active' => $validated['is_active']]);

        return back()->with('success', $version->is_active
            ? "Version {$version->version_name} is live again."
            : "Version {$version->version_name} pulled — clients will no longer be offered it.");
    }

    /**
     * Admin-side download, for verifying what was actually uploaded.
     */
    public function download(App $app, AppVersion $version): StreamedResponse
    {
        abort_unless($version->app_id === $app->id, 404);
        abort_unless($this->apks->exists($version), 404, 'The APK file is missing from storage.');

        $version->setRelation('app', $app);

        return $this->apks->download($version);
    }
}
