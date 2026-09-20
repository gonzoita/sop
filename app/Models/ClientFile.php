<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClientFile extends Model
{
    use HasFactory, BelongsToTeam, LogsActivity;

    protected $fillable = [
        'team_id',
        'client_id',
        'sop_run_id',
        'uploaded_by',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SopRun::class, 'sop_run_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['original_name', 'mime_type', 'file_size'])
            ->logOnlyDirty()
            ->useLogName('client_files')
            ->setDescriptionForEvent(fn (string $eventName) => "Archivo de cliente {$eventName}");
    }
}
