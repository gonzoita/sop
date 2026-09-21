<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneration extends Model
{
    use HasFactory, BelongsToTeam;

    protected $fillable = [
        'team_id',
        'sop_run_id',
        'sop_run_step_id',
        'skill_version_id',
        'provider',
        'model',
        'prompt',
        'response',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'latency_ms',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'latency_ms' => 'integer',
            'cost_usd' => 'decimal:6',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SopRun::class, 'sop_run_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(SopRunStep::class, 'sop_run_step_id');
    }

    public function skillVersion(): BelongsTo
    {
        return $this->belongsTo(SkillVersion::class, 'skill_version_id');
    }
}
