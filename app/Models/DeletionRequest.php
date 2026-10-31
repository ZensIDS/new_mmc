<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletionRequest extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const FAILED = 'failed';

    protected $fillable = [
        'requested_by', 'module', 'target_type', 'target_id', 'target_label', 'target_snapshot',
        'reason', 'status', 'pending_key', 'reviewed_by', 'reviewed_at', 'review_note',
        'executed_at', 'error_message', 'route_name', 'route_params',
    ];

    protected $casts = [
        'target_snapshot' => 'array',
        'route_params' => 'array',
        'reviewed_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** Bisa diproses superadmin: pending, atau failed (boleh dicoba lagi). */
    public function isActionable(): bool
    {
        return in_array($this->status, [self::PENDING, self::FAILED], true);
    }

    public static function pendingCount(): int
    {
        return static::where('status', self::PENDING)->count();
    }

    /** Data target saat ini (null bila sudah terhapus / tak ada). */
    public function currentTarget(): ?Model
    {
        $class = $this->target_type;

        if (! $class || ! $this->target_id || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class::query()->find($this->target_id);
    }

    /** True bila target berubah sesudah request diajukan (peringatan saat approve). */
    public function targetChangedSinceRequest(): bool
    {
        $target = $this->currentTarget();
        $snapshotAt = $this->target_snapshot['_updated_at'] ?? null;

        if (! $target || ! $snapshotAt || ! $target->updated_at) {
            return false;
        }

        return $target->updated_at->gt(\Illuminate\Support\Carbon::parse($snapshotAt));
    }
}