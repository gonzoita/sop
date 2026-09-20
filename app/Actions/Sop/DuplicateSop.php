<?php

namespace App\Actions\Sop;

use App\Models\Sop;
use App\Models\User;

class DuplicateSop
{
    public function __construct(
        protected CreateSop $createSop
    ) {}

    /**
     * Duplicate an existing SOP (or instantiate from template).
     *
     * @param  Sop  $originalSop
     * @param  User  $user
     * @param  string|null  $newTitle
     * @return Sop
     */
    public function execute(Sop $originalSop, User $user, ?string $newTitle = null): Sop
    {
        $title = $newTitle ?: "{$originalSop->title} (Copia)";

        // Get blocks from latest version or current version
        $latestVersion = $originalSop->currentVersion ?? $originalSop->versions()->latest('version_number')->first();
        $blocks = $latestVersion ? $latestVersion->blocks : [
            'schema_version' => 1,
            'blocks' => [],
        ];

        return $this->createSop->execute($user, [
            'team_id' => $user->current_team_id ?? $user->currentTeam?->id ?? $originalSop->team_id,
            'title' => $title,
            'description' => $originalSop->description,
            'category' => $originalSop->category,
            'is_template' => false,
            'blocks' => $blocks,
        ]);
    }
}
