<?php

namespace App\Domain\Document\Builders;

use App\Domain\Document\ReceiptDocument;
use App\Domain\Document\Support\PrintLocale;
use App\Domain\Document\Support\PrintOptions;
use App\Domain\Document\Support\Translator;
use App\Domain\Finance\InvoiceStatus;
use App\Domain\Finance\Models\InvoiceLine;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Money;
use App\Support\Bilingual;

/**
 * Turns a payment into a ReceiptDocument in the print language (spec §6, §8.2).
 * A finalized visit's receipt shows the patient and chamber as snapshotted;
 * otherwise the current data. Amounts print with Bangla digits on Bangla prints.
 */
final class ReceiptDocumentBuilder
{
    private Translator $t;

    public function build(Payment $payment, PrintOptions $options): ReceiptDocument
    {
        $this->t = new Translator($options);
        $t = $this->t;

        $payment->loadMissing(['invoice.lines', 'invoice.patient', 'invoice.underDoctor', 'invoice.caseHistory.chamber', 'invoice.caseHistory.doctor', 'receiver']);
        $invoice = $payment->invoice;
        $case = $invoice->caseHistory;

        $snapshot = $case === null ? null : $case->patient_snapshot;
        $patient = $snapshot ?? [
            'code' => $invoice->patient->code, 'name' => $invoice->patient->name, 'name_bn' => $invoice->patient->name_bn,
            'phone' => $invoice->patient->phone, 'address' => $invoice->patient->address,
        ];
        $chamber = $case === null ? [] : ($case->doctor_snapshot['chamber'] ?? [
            'name_en' => $case->chamber->name_en, 'name_bn' => $case->chamber->name_bn,
            'address_en' => $case->chamber->address_en, 'address_bn' => $case->chamber->address_bn, 'phones' => $case->chamber->phones ?? [],
        ]);

        $under = $invoice->underDoctor;

        return new ReceiptDocument(
            options: $options,
            receiptNo: $payment->receipt_no,
            date: $t->date($payment->received_at),
            clinic: [
                'name' => $t->text($chamber['name_en'] ?? null, $chamber['name_bn'] ?? null),
                'address' => $t->text($chamber['address_en'] ?? null, $chamber['address_bn'] ?? null),
                'phone' => filled($chamber['phones'] ?? null) ? implode(', ', $chamber['phones']) : ($chamber['phone'] ?? null),
            ],
            patient: $this->rows([
                [$t->ui('Name'), $this->name($patient['name'] ?? null, $patient['name_bn'] ?? null)],
                [$t->ui('ID'), (string) ($patient['code'] ?? '')],
                [$t->ui('Mobile'), (string) ($patient['phone'] ?? '')],
                [$t->ui('Address'), (string) ($patient['address'] ?? '')],
            ]),
            meta: $this->rows([
                [$t->ui('Invoice no.'), $invoice->invoice_no],
                [$t->ui('Rx no.'), (string) $case?->prescription_no],
                [$t->ui('Ref Dr.'), (string) $invoice->referred_by],
                [$t->ui('Under Dr.'), $under === null ? '' : implode(' / ', $t->text($under->name_en, $under->name_bn))],
                [$t->ui('Payment method'), $t->enum($payment->method).($payment->reference ? ' · '.$payment->reference : '')],
                [$t->ui('Received by'), (string) $payment->receiver?->name],
            ]),
            lines: $invoice->lines->map(fn (InvoiceLine $line) => [
                'code' => (string) $line->item_code,
                'description' => implode(' / ', $t->text($line->description_en, $line->description_bn)),
                'amount' => $this->money($line->line_total),
            ])->values()->all(),
            totals: array_values(array_filter([
                ['label' => $t->ui('Subtotal'), 'value' => $this->money($invoice->subtotal)],
                $invoice->discount->isZero() ? null : ['label' => $t->ui('Discount'), 'value' => '-'.$this->money($invoice->discount)],
                ['label' => $t->ui('Total'), 'value' => $this->money($invoice->total), 'strong' => true],
                ['label' => $t->ui('This payment'), 'value' => $this->money($payment->amount), 'strong' => true],
                ['label' => $t->ui('Paid in total'), 'value' => $this->money($invoice->paid)],
                ['label' => $t->ui('Due'), 'value' => $this->money($invoice->due), 'strong' => true],
            ])),
            stamp: $this->stamp($payment),
            words: [
                'title' => $t->ui('Money receipt'),
                'receipt_no' => $t->ui('Receipt no.'),
                'date' => $t->ui('Date'),
                'code' => $t->ui('Code'),
                'item' => $t->ui('Item'),
                'amount' => $t->ui('Amount'),
                'signature' => $t->ui('Signature'),
            ],
        );
    }

    /** @return array{label: string, tone: string} */
    private function stamp(Payment $payment): array
    {
        if (! $payment->isActive()) {
            return ['label' => $this->t->ui('VOID'), 'tone' => 'void'];
        }

        return match ($payment->invoice->status) {
            InvoiceStatus::Paid => ['label' => $this->t->ui('PAID'), 'tone' => 'paid'],
            InvoiceStatus::PartiallyPaid => ['label' => $this->t->ui('PARTIAL'), 'tone' => 'partial'],
            InvoiceStatus::Voided => ['label' => $this->t->ui('VOID'), 'tone' => 'void'],
            InvoiceStatus::Issued => ['label' => $this->t->ui('DUE'), 'tone' => 'due'],
        };
    }

    private function money(Money $amount): string
    {
        return $this->t->number($amount->format());
    }

    private function name(?string $en, ?string $bn): string
    {
        return $this->t->locale() === PrintLocale::Bilingual
            ? implode(' / ', array_values(array_unique(array_filter([$en, $bn], 'filled'))))
            : (string) Bilingual::pick($en, $bn, $this->t->locale()->value);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows
     * @return list<array{label: string, value: string}>
     */
    private function rows(array $rows): array
    {
        return array_values(array_map(
            fn (array $row) => ['label' => $row[0], 'value' => $row[1]],
            array_filter($rows, fn (array $row) => filled($row[1])),
        ));
    }
}
