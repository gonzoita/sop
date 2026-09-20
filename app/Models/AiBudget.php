<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AiBudget extends Model
{
    use HasFactory, BelongsToTeam, LogsActivity;

    protected $fillable = [
        'team_id',
        'monthly_limit_usd',
        'alert_at_percent',
    ];

    protected function casts(): array
    {
        return [
            'monthly_limit_usd' => 'decimal:2',
            'alert_at_percent' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['monthly_limit_usd', 'alert_at_percent'])
            ->logOnlyDirty()
            ->useLogName('ai_budgets')
            ->setDescriptionForEvent(fn (string $eventName) => "Presupuesto de IA {$eventName}");
    }
}
