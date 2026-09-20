<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SopVersion extends Model
{
    use HasFactory, BelongsToTeam;

    const UPDATED_AT = null;

    protected $fillable = [
        'team_id',
        'sop_id',
        'version_number',
        'blocks',
        'changelog',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'published_at' => 'datetime',
            'version_number' => 'integer',
        ];
    }

    public function isPublished(): bool
    {
        return ! is_null($this->published_at);
    }

    public function isDraft(): bool
    {
        return is_null($this->published_at);
    }

    public function sop(): BelongsTo
    {
        return $this->belongsTo(Sop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}