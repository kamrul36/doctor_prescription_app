<?php

namespace App\Domain\Document;

use App\Domain\Document\Support\PrintOptions;

/**
 * A money receipt, already in the print language (spec §8.2): receipt and
 * invoice numbers, patient, Ref Dr. / Under Dr., the bill lines with item
 * codes, this payment, and a PAID / PARTIAL / DUE stamp (VOID for a voided
 * payment). Built by ReceiptDocumentBuilder; the view only lays it out.
 */
final readonly class ReceiptDocument
{
    /**
     * @param  array{name: list<string>, address: list<string>, phone: string|null}  $clinic
     * @param  list<array{label: string, value: string}>  $patient
     * @param  list<array{label: string, value: string}>  $meta
     * @param  list<array{code: string, description: string, amount: string}>  $lines
     * @param  list<array{label: string, value: string, strong?: bool}>  $totals
     * @param  array{label: string, tone: string}  $stamp
     * @param  array<string, string>  $words
     */
    public function __construct(
        public PrintOptions $options,
        public string $receiptNo,
        public string $date,
        public array $clinic,
        public array $patient,
        public array $meta,
        public array $lines,
        public array $totals,
        public array $stamp,
        public array $words,
    ) {}
}
