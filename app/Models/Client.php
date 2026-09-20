<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Client extends Model
{
    use HasFactory, SoftDeletes, BelongsToTeam, LogsActivity;

    protected $fillable = [
        'team_id',
        'name',
        'slug',
        'contact_email',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'contact_email', 'status', 'meta'])
            ->logOnlyDirty()
            ->useLogName('clients')
            ->setDescriptionForEvent(fn (string $eventName) => "Cliente {$eventName}");
    }
}