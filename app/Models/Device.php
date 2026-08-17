<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One installation of one app on one client handset, identified by a
 * client-generated `install_uuid`. Rows are upserted on every app launch.
 */
class Device extends Model
{
    use HasFactory;

    /** first_seen_at / last_seen_at are managed explicitly by the check-in. */
    public $timestamps = false;

    protected $fillable = [
        'app_id',
        'install_uuid',
        'fcm_token',
        'device_model',
        'android_version',
        'client_label',
        'current_version_code',
        'current_version_name',
        'first_seen_at',
        'last_seen_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_version_code' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Devices we can actually reach with a push. */
    public function scopePushable(Builder $query): void
    {
        $query->whereNotNull('fcm_token')->where('is_active', true);
    }

    /**
     * Behind the newest active build for its app. Requires
     * `latest_version_code` to have been selected onto the row (see
     * {@see scopeWithLatestVersionCode}) or the app relation to be loaded.
     */
    public function isOutdated(): bool
    {
        $latest = $this->latest_version_code
            ?? $this->app?->latestActiveVersion?->version_code;

        if ($latest === null || $this->current_version_code === null) {
            return false;
        }

        return $this->current_version_code < $latest;
    }

    /**
     * Adds a `latest_version_code` column so outdated-ness can be computed
     * without an N+1 lookup per device.
     */
    public function scopeWithLatestVersionCode(Builder $query): void
    {
        // Select devices.* explicitly first — addSelect() on a query with no
        // columns set would otherwise replace the implicit "*" entirely.
        $query->select('devices.*')->addSelect(['latest_version_code' => AppVersion::query()
            ->selectRaw('MAX(version_code)')
            ->whereColumn('app_versions.app_id', 'devices.app_id')
            ->where('is_active', true),
        ]);
    }

    /** Devices running something older than their app's newest active build. */
    public function scopeOutdated(Builder $query): void
    {
        $query->whereNotNull('current_version_code')
            ->where('current_version_code', '<', AppVersion::query()
                ->selectRaw('COALESCE(MAX(version_code), 0)')
                ->whereColumn('app_versions.app_id', 'devices.app_id')
                ->where('is_active', true));
    }
}
