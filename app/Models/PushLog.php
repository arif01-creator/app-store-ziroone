<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outcome of one NotifyDevicesOfUpdate run — one row per version pushed.
 */
class PushLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'app_version_id',
        'devices_targeted',
        'devices_sent',
        'devices_failed',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'devices_targeted' => 'integer',
            'devices_sent' => 'integer',
            'devices_failed' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function appVersion(): BelongsTo
    {
        return $this->belongsTo(AppVersion::class);
    }
}
