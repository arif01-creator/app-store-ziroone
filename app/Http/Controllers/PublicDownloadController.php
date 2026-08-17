<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Services\ApkStorage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only thing a client ever sees. No auth, and deliberately no admin data:
 * no device list, no api_key, no version history.
 */
class PublicDownloadController extends Controller
{
    public function __construct(private readonly ApkStorage $apks) {}

    public function show(App $app): View
    {
        abort_unless($app->is_active, 404);

        return view('public.download', [
            'app' => $app,
            'version' => $app->latestActiveVersion()->first(),
        ]);
    }

    /**
     * Gated download for the public page.
     *
     * A POST rather than a plain link so the APK is never sitting behind a
     * crawlable GET, and it streams from the private disk — this route is
     * separate from the api_key-scoped API download precisely so the shared
     * secret never has to appear on a public page.
     */
    public function download(App $app): StreamedResponse
    {
        abort_unless($app->is_active, 404);

        $version = $app->latestActiveVersion()->first();

        abort_if($version === null, 404, 'No build has been published for this app yet.');
        abort_unless($this->apks->exists($version), 404, 'The APK file is missing from storage.');

        $version->setRelation('app', $app);

        return $this->apks->download($version);
    }
}
