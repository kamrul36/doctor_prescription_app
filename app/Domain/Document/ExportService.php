<?php

namespace App\Domain\Document;

use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Document\Builders\PrescriptionDocumentBuilder;
use App\Domain\Document\Support\PrintOptions;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\PrintMode;

/**
 * Renders a prescription for printing (spec §11). Today: A4 HTML for the browser's
 * Print / Save as PDF. The server PDF exporter (P1.6 engine step) will take the same
 * PrescriptionDocument; print logs and "Copy" marks arrive with it.
 */
final class ExportService
{
    public function __construct(private readonly PrescriptionDocumentBuilder $builder) {}

    /** The template's print mode, used when the request names none. */
    public function defaultMode(CaseHistory $case): PrintMode
    {
        return PrescriptionDocumentBuilder::templateFor($case)->default_print_mode ?? PrintMode::WithLetterhead;
    }

    /**
     * @param  bool  $toolbar  the on-screen language / mode switch (web); off for API exports
     * @param  PrescriptionTemplate|null  $template  lay out with this (unsaved) template instead of the visit's
     */
    public function html(CaseHistory $case, PrintOptions $options, bool $toolbar = false, ?PrescriptionTemplate $template = null): string
    {
        return view('print.prescription', [
            'doc' => $this->builder->build($case, $options, $template),
            'case' => $case,
            'toolbar' => $toolbar,
        ])->render();
    }
}
