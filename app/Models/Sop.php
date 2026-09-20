<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Sop extends Model
{
    use HasFactory, SoftDeletes, BelongsToTeam, LogsActivity;

    protected $fillable = [
        'team_id',
        'title',
        'slug',
        'description',
        'category',
        'status',
        'is_template',
        'current_version_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_template' => 'boolean',
        ];
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(SopVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SopVersion::class)->orderBy('version_number', 'desc');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SopRun::class)->latest();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'description', 'category', 'status', 'is_template', 'current_version_id'])
            ->logOnlyDirty()
            ->useLogName('sops')
            ->setDescriptionForEvent(fn (string $eventName) => "SOP {$eventName}");
    }
}