<?php

namespace App\Domain\Document;

use App\Domain\Document\Support\PrintOptions;

/**
 * Everything a printed prescription shows, already in the print language
 * (built by PrescriptionDocumentBuilder; the print views only lay it out).
 *
 * A section item is a block of lines: `title` (e.g. a medicine name) and the lines under it.
 *
 * @phpstan-type Item array{title: string|null, lines: list<string>}
 * @phpstan-type Section array{key: string, label: string, items: list<Item>}
 */
final readonly class PrescriptionDocument
{
    /**
     * @param  array{name: list<string>, credentials: list<string>, designation: list<string>, registration: string|null}  $doctor
     * @param  array{name: list<string>, address: list<string>, phone: string|null, hours: list<string>, branches: list<string>}  $chamber
     * @param  list<array{label: string, value: string}>  $patient  empty when the template hides the patient block
     * @param  array{header: list<Section>, left: list<Section>, right: list<Section>, footer: list<Section>}  $zones
     * @param  array{top: int, right: int, bottom: int, left: int}  $margins  mm
     * @param  array{signature: string, draft: string, rx_no: string}  $words  fixed words of the layout, translated
     */
    public function __construct(
        public PrintOptions $options,
        public bool $isDraft,
        public ?string $prescriptionNo,
        public string $date,
        public array $doctor,
        public array $chamber,
        public array $patient,
        public array $zones,
        public array $margins,
        public bool $twoColumns,
        public bool $showSignature,
        public bool $showVisitingHours,
        public bool $showBranchFooter,
        public array $words,
    ) {}
}
