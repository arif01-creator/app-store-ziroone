<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;

abstract class ApiController extends Controller
{
    /**
     * Resolve the app a Flutter binary is calling on behalf of.
     *
     * The api_key is a shared secret baked into one specific binary. It exists
     * to keep these URLs unguessable and uncrawlable — not to withstand a
     * determined attacker — so the only check here is an exact match against
     * an app that is still being distributed.
     */
    protected function resolveApp(string $apiKey): App
    {
        $app = App::query()->where('api_key', $apiKey)->first();

        abort_if($app === null, 403, 'Invalid API key.');
        abort_unless($app->is_active, 403, 'This app is no longer distributed.');

        return $app;
    }
}
