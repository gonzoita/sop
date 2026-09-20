<?php

namespace App\Actions\Sop;

use App\Models\Sop;
use App\Models\SopVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSop
{
    /**
     * Create a new SOP and its initial draft version (v1).
     *
     * @param  User  $user
     * @param  array{title: string, description?: ?string, category?: ?string, is_template?: bool, blocks?: ?array, team_id?: ?int}  $data
     * @return Sop
     */
    public function execute(User $user, array $data): Sop
    {
        $teamId = $data['team_id'] ?? $user->current_team_id ?? $user->currentTeam?->id;

        return DB::transaction(function () use ($user, $data, $teamId) {
            $baseSlug = Str::slug($data['title']);
            $slug = $baseSlug ?: 'sop';
            $count = 1;

            while (Sop::withoutGlobalScopes()->where('team_id', $teamId)->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$count}";
                $count++;
            }

            $sop = Sop::create([
                'team_id' => $teamId,
                'title' => $data['title'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'status' => 'draft',
                'is_template' => (bool) ($data['is_template'] ?? false),
                'current_version_id' => null,
                'created_by' => $user->id,
            ]);

            $blocks = $data['blocks'] ?? [
                'schema_version' => 1,
                'blocks' => [],
            ];

            SopVersion::create([
                'team_id' => $teamId,
                'sop_id' => $sop->id,
                'version_number' => 1,
                'blocks' => $blocks,
                'changelog' => 'Versión inicial (borrador)',
                'published_at' => null,
                'created_by' => $user->id,
            ]);

            return $sop->fresh(['currentVersion', 'versions']);
        });
    }
}
