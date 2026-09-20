<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AiCredential extends Model
{
    use HasFactory, BelongsToTeam, LogsActivity;

    protected $fillable = [
        'team_id',
        'provider',
        'label',
        'api_key',
        'is_active',
        'last_used_at',
        'created_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'api_key',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'masked_api_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Show only the last 4 characters of the API key for security.
     */
    protected function maskedApiKey(): Attribute
    {
        return Attribute::make(
            get: function () {
                $raw = $this->api_key ?? '';
                if (strlen($raw) <= 4) {
                    return '••••••••';
                }
                return '••••••••' . substr($raw, -4);
            }
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['provider', 'label', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('ai_credentials')
            ->setDescriptionForEvent(fn (string $eventName) => "Credencial de IA {$eventName}");
    }
}
