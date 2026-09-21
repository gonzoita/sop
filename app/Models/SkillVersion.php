<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SkillVersion extends Model
{
    use HasFactory, LogsActivity;

    public $timestamps = false;

    protected $fillable = [
        'skill_id',
        'version_number',
        'instructions',
        'variables',
        'source',
        'changelog',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'version_number' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['version_number', 'source', 'changelog'])
            ->useLogName('skill_versions')
            ->setDescriptionForEvent(fn (string $eventName) => "Versión de Skill {$eventName}");
    }
}
