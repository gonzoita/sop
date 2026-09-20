<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SopRunStep extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'sop_run_id',
        'block_id',
        'block_type',
        'status',
        'assigned_to',
        'output',
        'ai_generation_id',
        'notes',
        'due_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'output' => 'array',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SopRun::class, 'sop_run_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'approved']);
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_to', 'completed_at', 'notes'])
            ->logOnlyDirty()
            ->useLogName('sop_run_steps')
            ->setDescriptionForEvent(fn (string $eventName) => "Paso de ejecución {$eventName}");
    }
}
