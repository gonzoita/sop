<?php

namespace App\Actions\Sop;

use App\Models\Sop;
use App\Models\SopVersion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ExportSopToPdf
{
    /**
     * Generate a downloadable PDF for an SOP version.
     */
    public function execute(Sop $sop, ?SopVersion $version = null): Response
    {
        $v = $version ?? $sop->currentVersion ?? $sop->versions()->latest('version_number')->first();

        $pdf = Pdf::loadView('sops.pdf', [
            'sop' => $sop,
            'version' => $v,
        ])->setPaper('a4', 'portrait');

        $filename = "{$sop->slug}-v" . ($v?->version_number ?? 1) . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
