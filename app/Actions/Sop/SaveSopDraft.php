<?php

namespace App\Actions\Sop;

use App\Models\Sop;
use App\Models\SopVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveSopDraft
{
    /**
     * Save blocks into an existing draft or start a new draft version.
     *
     * @param  Sop  $sop
     * @param  array  $blocks
     * @param  User|null  $user
     * @param  string|null  $changelog
     * @return SopVersion
     */
    public function execute(Sop $sop, array $blocks, ?User $user = null, ?string $changelog = null): SopVersion
    {
        return DB::transaction(function () use ($sop, $blocks, $user, $changelog) {
            // Find existing unpublished draft
            $draft = $sop->versions()
                ->whereNull('published_at')
                ->latest('version_number')
                ->first();

            if ($draft) {
                $draft->blocks = $blocks;
                if ($changelog !== null) {
                    $draft->changelog = $changelog;
                }
                $draft->save();

                return $draft;
            }

            // If no draft exists (all published), create next draft version
            $maxVersion = (int) $sop->versions()->max('version_number');
            $nextVersionNumber = $maxVersion + 1;

            return SopVersion::create([
                'team_id' => $sop->team_id,
                'sop_id' => $sop->id,
                'version_number' => $nextVersionNumber,
                'blocks' => $blocks,
                'changelog' => $changelog,
                'published_at' => null,
                'created_by' => $user?->id,
            ]);
        });
    }
}
