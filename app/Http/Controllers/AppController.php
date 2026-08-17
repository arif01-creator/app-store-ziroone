<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppRequest;
use App\Http\Requests\UpdateAppRequest;
use App\Models\App;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AppController extends Controller
{
    /** Disk holding app icons. Public on purpose — icons are not secrets. */
    private const ICON_DISK = 'public';

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $apps = App::query()
            ->withCount('devices')
            ->withCount(['versions as versions_count'])
            ->with('latestActiveVersion')
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('package_id', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('apps.index', compact('apps', 'search'));
    }

    public function create(): View
    {
        return view('apps.create', ['app' => new App(['is_active' => true])]);
    }

    public function store(StoreAppRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('icon');

        if ($request->hasFile('icon')) {
            $data['icon_path'] = $request->file('icon')->store('app-icons', self::ICON_DISK);
        }

        $app = App::create($data);

        return redirect()
            ->route('apps.show', $app)
            ->with('success', "App \"{$app->name}\" created. Upload your first APK below.");
    }

    /**
     * The main working screen for one app: version history, upload form,
     * device list, and the public share link + QR code.
     */
    public function show(Request $request, App $app): View
    {
        $app->load('latestActiveVersion');

        $versions = $app->versions()
            ->withCount('pushLogs')
            ->with('pushLogs')
            ->orderByDesc('version_code')
            ->paginate(10, ['*'], 'versions')
            ->withQueryString();

        $deviceSearch = trim((string) $request->query('device_search', ''));
        $deviceFilter = (string) $request->query('device_filter', '');

        $devices = $app->devices()
            ->withLatestVersionCode()
            ->when($deviceSearch !== '', fn ($query) => $query->where(function ($q) use ($deviceSearch) {
                $q->where('client_label', 'like', "%{$deviceSearch}%")
                    ->orWhere('device_model', 'like', "%{$deviceSearch}%")
                    ->orWhere('install_uuid', 'like', "%{$deviceSearch}%");
            }))
            ->when($deviceFilter === 'outdated', fn ($query) => $query->outdated())
            ->when($deviceFilter === 'no_push', fn ($query) => $query->whereNull('fcm_token'))
            ->orderByDesc('last_seen_at')
            ->paginate(20, ['*'], 'devices')
            ->withQueryString();

        $stats = [
            'devices' => $app->devices()->count(),
            'outdated' => $app->devices()->outdated()->count(),
            'no_push' => $app->devices()->whereNull('fcm_token')->count(),
        ];

        return view('apps.show', [
            'app' => $app,
            'versions' => $versions,
            'devices' => $devices,
            'deviceSearch' => $deviceSearch,
            'deviceFilter' => $deviceFilter,
            'stats' => $stats,
            'nextVersionCode' => $app->nextVersionCode(),
        ]);
    }

    public function edit(App $app): View
    {
        return view('apps.edit', compact('app'));
    }

    public function update(UpdateAppRequest $request, App $app): RedirectResponse
    {
        $data = $request->safe()->except('icon');

        if ($request->hasFile('icon')) {
            $old = $app->icon_path;
            $data['icon_path'] = $request->file('icon')->store('app-icons', self::ICON_DISK);

            if ($old) {
                Storage::disk(self::ICON_DISK)->delete($old);
            }
        }

        $app->update($data);

        return redirect()
            ->route('apps.show', $app)
            ->with('success', 'App updated.');
    }

    /**
     * Inline edit of a device's friendly label from the device table.
     */
    public function updateDevice(Request $request, App $app, Device $device): RedirectResponse
    {
        abort_unless($device->app_id === $app->id, 404);

        $validated = $request->validate([
            'client_label' => ['nullable', 'string', 'max:255'],
        ]);

        $device->update(['client_label' => $validated['client_label'] ?: null]);

        return back()->with('success', 'Device label saved.');
    }
}
