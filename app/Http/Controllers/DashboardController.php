<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\Device;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $totalDevices = Device::query()->count();
        $outdatedDevices = Device::query()->outdated()->count();

        return view('dashboard', [
            'stats' => [
                'apps' => App::query()->count(),
                'active_apps' => App::query()->where('is_active', true)->count(),
                'devices' => $totalDevices,
                'outdated' => $outdatedDevices,
                'up_to_date' => max($totalDevices - $outdatedDevices, 0),
                'no_push' => Device::query()->whereNull('fcm_token')->count(),
            ],
            'recentCheckIns' => Device::query()
                ->with('app:id,name,slug,icon_path')
                ->withLatestVersionCode()
                ->whereNotNull('last_seen_at')
                ->orderByDesc('last_seen_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
