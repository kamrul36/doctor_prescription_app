<?php

namespace App\Domain\Document;

use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Document\Builders\PrescriptionDocumentBuilder;
use App\Domain\Document\Builders\ReceiptDocumentBuilder;
use App\Domain\Document\Support\PrintOptions;
use App\Domain\Finance\Models\Payment;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\PrintMode;

/**
 * Renders documents for printing (spec §11): the A4 prescription and the A5
 * money receipt, as HTML for the browser's Print / Save as PDF. A server-side
 * PDF engine is deferred past Phase 1 (decided 2026-11-20); it would take the
 * same document objects.
 */
final class ExportService
{
    public function __construct(
        private readonly PrescriptionDocumentBuilder $builder,
        private readonly ReceiptDocumentBuilder $receipts,
    ) {}

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

    /** @param  bool  $toolbar  the on-screen language switch (web); off for API exports */
    public function receiptHtml(Payment $payment, PrintOptions $options, bool $toolbar = false): string
    {
        return view('print.receipt', [
            'doc' => $this->receipts->build($payment, $options),
            'payment' => $payment,
            'toolbar' => $toolbar,
        ])->render();
    }
}
