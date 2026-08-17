<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * A managed Flutter app. Each one owns its own pool of devices and its own
 * version history, and is addressed publicly by `slug` and by the Flutter
 * binary via `api_key`.
 */
class App extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'package_id',
        'icon_path',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // `api_key` is never user-supplied — it is minted here so no controller
        // or seeder can accidentally create an app without one.
        static::creating(function (self $app): void {
            $app->api_key ??= self::generateApiKey();
        });
    }

    public static function generateApiKey(): string
    {
        return Str::random(40);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AppVersion::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * The version the clients should be running: highest `version_code` among
     * active versions. Inactive versions are pulled builds and never served.
     */
    public function latestActiveVersion(): HasOne
    {
        return $this->hasOne(AppVersion::class)->ofMany(
            ['version_code' => 'max'],
            fn ($query) => $query->where('is_active', true),
        );
    }

    /**
     * Highest version_code ever used for this app, active or not. New uploads
     * must exceed this — reusing a pulled build's code would break clients that
     * already installed it.
     */
    public function maxVersionCode(): int
    {
        return (int) $this->versions()->max('version_code');
    }

    public function nextVersionCode(): int
    {
        return $this->maxVersionCode() + 1;
    }

    public function publicUrl(): string
    {
        return route('public.apps.show', $this);
    }
}
