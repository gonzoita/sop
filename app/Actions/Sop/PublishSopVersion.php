<?php

namespace App\Actions\Sop;

use App\Exceptions\SopValidationException;
use App\Models\Sop;
use App\Models\SopVersion;
use App\Models\User;
use App\Support\Sop\BlockValidator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PublishSopVersion
{
    /**
     * Validate blocks and publish a version.
     *
     * @param  Sop  $sop
     * @param  SopVersion|null  $version
     * @param  string|null  $changelog
     * @param  User|null  $user
     * @return SopVersion
     *
     * @throws SopValidationException|InvalidArgumentException
     */
    public function execute(
        Sop $sop,
        ?SopVersion $version = null,
        ?string $changelog = null,
        ?User $user = null
    ): SopVersion {
        $targetVersion = $version ?? $sop->versions()
            ->whereNull('published_at')
            ->latest('version_number')
            ->first();

        if (! $targetVersion) {
            throw new InvalidArgumentException('No hay ningún borrador pendiente de publicar.');
        }

        // Validate strictly using BlockValidator
        BlockValidator::validate($targetVersion->blocks, throw: true);

        return DB::transaction(function () use ($sop, $targetVersion, $changelog, $user) {
            $targetVersion->update([
                'published_at' => now(),
                'changelog' => $changelog ?? $targetVersion->changelog ?? "Publicación de versión {$targetVersion->version_number}",
            ]);

            $sop->update([
                'status' => 'published',
                'current_version_id' => $targetVersion->id,
            ]);

            activity('sops')
                ->performedOn($sop)
                ->causedBy($user)
                ->withProperties([
                    'version_number' => $targetVersion->version_number,
                    'version_id' => $targetVersion->id,
                ])
                ->log("Versión {$targetVersion->version_number} publicada");

            return $targetVersion->fresh();
        });
    }
}
