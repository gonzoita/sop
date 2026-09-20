<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AutomationTrigger extends Model
{
    use HasFactory, BelongsToTeam, LogsActivity;

    protected $fillable = [
        'team_id',
        'event',
        'sop_id',
        'conditions',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function sop(): BelongsTo
    {
        return $this->belongsTo(Sop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['event', 'sop_id', 'is_active', 'conditions'])
            ->logOnlyDirty()
            ->useLogName('automation_triggers')
            ->setDescriptionForEvent(fn (string $eventName) => "Disparador de automatización {$eventName}");
    }
}
