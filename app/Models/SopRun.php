<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SopRun extends Model
{
    use HasFactory, BelongsToTeam, LogsActivity;

    protected $fillable = [
        'team_id',
        'sop_id',
        'sop_version_id',
        'client_id',
        'title',
        'status',
        'inputs',
        'outputs',
        'started_by',
        'assigned_to',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'inputs' => 'array',
            'outputs' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function sop(): BelongsTo
    {
        return $this->belongsTo(Sop::class);
    }

    public function sopVersion(): BelongsTo
    {
        return $this->belongsTo(SopVersion::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SopRunStep::class, 'sop_run_id')->orderBy('id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function getInputValue(string $key, mixed $default = null): mixed
    {
        return $this->inputs[$key] ?? $default;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'client_id', 'assigned_to', 'completed_at'])
            ->logOnlyDirty()
            ->useLogName('sop_runs')
            ->setDescriptionForEvent(fn (string $eventName) => "Ejecución de SOP {$eventName}");
    }
}
