<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One uploaded APK build. Rows are never deleted when a build goes bad —
 * `is_active` is flipped off instead, so history and push logs stay intact.
 */
class AppVersion extends Model
{
    use HasFactory;

    /** This table only tracks creation — there is nothing to update on a build. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'app_id',
        'version_name',
        'version_code',
        'apk_path',
        'file_size',
        'release_notes',
        'is_force_update',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'version_code' => 'integer',
            'file_size' => 'integer',
            'is_force_update' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function pushLogs(): HasMany
    {
        return $this->hasMany(PushLog::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Human-readable file size, e.g. "24.3 MB". */
    public function formattedFileSize(): string
    {
        $bytes = (int) $this->file_size;

        foreach ([['GB', 1073741824], ['MB', 1048576], ['KB', 1024]] as [$unit, $step]) {
            if ($bytes >= $step) {
                return round($bytes / $step, 1).' '.$unit;
            }
        }

        return $bytes.' B';
    }
}
