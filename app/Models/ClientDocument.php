<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClientDocument extends Model
{
    use HasFactory, BelongsToTeam, LogsActivity;

    protected $fillable = [
        'team_id',
        'client_id',
        'sop_run_id',
        'title',
        'markdown',
        'published_by',
        'published_at',
        'revoked_at',
    ];

    protected $appends = [
        'status',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function getStatusAttribute(): string
    {
        return $this->revoked_at ? 'revoked' : 'published';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sopRun(): BelongsTo
    {
        return $this->belongsTo(SopRun::class, 'sop_run_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Scope a query to only include active (non-revoked) documents.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'client_id', 'sop_run_id', 'published_at', 'revoked_at'])
            ->logOnlyDirty()
            ->useLogName('client_documents')
            ->setDescriptionForEvent(fn (string $eventName) => "Documento de cliente {$eventName}");
    }
}
