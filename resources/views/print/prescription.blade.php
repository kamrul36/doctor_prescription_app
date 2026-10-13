{{--
    A4 prescription for the browser's Print / Save as PDF (spec §5, §6). Everything shown comes from
    PrescriptionDocument, already in the print language; this view only lays it out. Plain CSS (no Vite)
    so the API export and the later server-PDF step render the same page.
--}}
@use('App\Domain\Document\Support\PrintLocale')
@use('App\Domain\Practice\PrintMode')
@php
    $o = $doc->options;
    $letterhead = $o->mode === PrintMode::WithLetterhead;
    $m = $doc->margins;
@endphp
<!DOCTYPE html>
<html lang="{{ $o->locale === PrintLocale::Bn ? 'bn' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $doc->prescriptionNo ?? $doc->words['draft'] }} · {{ config('app.name') }}</title>
    @include('partials.fonts')
    <style>
        :root {
            --ink: #111827;
            --muted: #4b5563;
            --line: #d1d5db;
            --accent: #115e59;
            --paper: #ffffff;
            --desk: #e5e7eb;
        }
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            background: var(--desk);
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', 'Noto Serif Bengali', Roboto, 'Noto Sans', sans-serif;
            font-size: 11pt;
            line-height: 1.45;
        }
        .sheet {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 24px;
            padding: {{ $m['top'] }}mm {{ $m['right'] }}mm {{ $m['bottom'] }}mm {{ $m['left'] }}mm;
            background: var(--paper);
            box-shadow: 0 2px 12px rgb(0 0 0 / .12);
            display: flex;
            flex-direction: column;
        }
        .letterhead { display: flex; justify-content: space-between; gap: 12mm; padding-bottom: 4mm; border-bottom: 2px solid var(--accent); }
        .letterhead .doctor-name { font-size: 15pt; font-weight: 700; color: var(--accent); }
        .letterhead .right { text-align: right; }
        .letterhead .chamber-name { font-weight: 600; }
        .small { font-size: 9.5pt; color: var(--muted); }
        .patient { display: flex; flex-wrap: wrap; gap: 1mm 6mm; padding: 2.5mm 0; border-bottom: 1px solid var(--line); font-size: 10.5pt; }
        .patient b { font-weight: 600; }
        .meta { display: flex; justify-content: space-between; padding: 1.5mm 0; font-size: 9.5pt; color: var(--muted); }
        .body { flex: 1; display: grid; gap: 6mm; padding-top: 3mm; }
        .body.two { grid-template-columns: 36% 1fr; }
        .body.two .left { border-right: 1px solid var(--line); padding-right: 4mm; }
        section { margin-bottom: 4mm; break-inside: avoid; }
        h2 { margin: 0 0 1mm; font-size: 9.5pt; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: var(--accent); }
        h2.rx { font-family: Georgia, serif; font-size: 18pt; letter-spacing: 0; text-transform: none; }
        ul, ol { margin: 0; padding-left: 5mm; }
        li { margin-bottom: .8mm; }
        .lines { white-space: pre-line; }
        .item-title { font-weight: 600; }
        ol.medicines > li { margin-bottom: 2mm; }
        ol.medicines .line { display: block; color: #374151; }
        .footer { margin-top: auto; padding-top: 6mm; }
        .signature { display: flex; justify-content: flex-end; }
        .signature span { min-width: 55mm; padding-top: 1mm; border-top: 1px solid var(--ink); text-align: center; font-size: 10pt; }
        .branches { margin-top: 3mm; padding-top: 2mm; border-top: 1px solid var(--line); text-align: center; }
        .watermark {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none;
            font-size: 96pt; font-weight: 800; color: rgb(220 38 38 / .12); transform: rotate(-30deg);
        }
        .toolbar {
            position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
            padding: 10px 16px; margin-bottom: 16px; background: #fff; border-bottom: 1px solid var(--line); font-size: 14px;
        }
        .toolbar a, .toolbar button, .toolbar select, .toolbar label.lang {
            font: inherit; border-radius: 6px; padding: 5px 10px; border: 1px solid var(--line); background: #fff; color: var(--ink); text-decoration: none; cursor: pointer;
        }
        .toolbar .group { display: inline-flex; gap: 0; }
        .toolbar .group label.lang { border-radius: 0; margin-left: -1px; }
        .toolbar .group label.lang:first-child { border-radius: 6px 0 0 6px; margin-left: 0; }
        .toolbar .group label.lang:last-child { border-radius: 0 6px 6px 0; }
        .toolbar input[type=radio] { position: absolute; opacity: 0; }
        .toolbar label.lang:has(input:checked) { background: var(--accent); border-color: var(--accent); color: #fff; }
        .toolbar label.lang:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 2px; }
        .toolbar .print { margin-left: auto; background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; }
        @media screen and (max-width: 820px) {
            .sheet { width: 100%; min-height: auto; padding: 16px; }
            .body.two { grid-template-columns: 1fr; }
            .body.two .left { border-right: 0; padding-right: 0; }
        }
        @media print {
            body { background: none; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: 297mm; }
        }
    </style>
</head>
<body>
    @if ($toolbar)
        {{-- Screen only. Changing an option reloads the page with the new query. --}}
        <form method="GET" class="toolbar" lang="{{ app()->getLocale() }}">
            <a href="{{ route('prescriptions.show', $case) }}">← {{ __('Back') }}</a>
            <span class="group" role="radiogroup" aria-label="{{ __('Print language') }}">
                @foreach (PrintLocale::cases() as $locale)
                    <label class="lang">
                        <input type="radio" name="lang" value="{{ $locale->value }}" @checked($o->locale === $locale) onchange="this.form.submit()">
                        {{ $locale->label() }}
                    </label>
                @endforeach
            </span>
            <label>
                <input type="hidden" name="bn_digits" value="0">
                <input type="checkbox" name="bn_digits" value="1" @checked($o->banglaDigits) @disabled($o->locale === PrintLocale::En) onchange="this.form.submit()">
                {{ __('Bangla digits') }} (২+০+২)
            </label>
            <select name="mode" aria-label="{{ __('Print mode') }}" onchange="this.form.submit()">
                @foreach (PrintMode::cases() as $mode)
                    <option value="{{ $mode->value }}" @selected($o->mode === $mode)>{{ $mode->label() }}</option>
                @endforeach
            </select>
            <noscript><button type="submit">{{ __('Apply') }}</button></noscript>
            <button type="button" class="print" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
        </form>
    @endif

    <main class="sheet">
        @if ($doc->isDraft)
            <div class="watermark" aria-hidden="true">{{ $doc->words['draft'] }}</div>
        @endif

        @if ($letterhead)
            <header class="letterhead">
                <div>
                    @foreach ($doc->doctor['name'] as $line)
                        <div class="doctor-name">{{ $line }}</div>
                    @endforeach
                    @if ($doc->doctor['credentials'])
                        <div>{{ implode(', ', $doc->doctor['credentials']) }}</div>
                    @endif
                    @foreach ($doc->doctor['designation'] as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                    @if ($doc->doctor['registration'])
                        <div class="small">{{ $doc->doctor['registration'] }}</div>
                    @endif
                </div>
                <div class="right">
                    @foreach ($doc->chamber['name'] as $line)
                        <div class="chamber-name">{{ $line }}</div>
                    @endforeach
                    @foreach ($doc->chamber['address'] as $line)
                        <div class="small lines">{{ $line }}</div>
                    @endforeach
                    @if ($doc->chamber['phone'])
                        <div class="small">{{ $doc->chamber['phone'] }}</div>
                    @endif
                    @if ($doc->showVisitingHours)
                        @foreach ($doc->chamber['hours'] as $line)
                            <div class="small lines">{{ $line }}</div>
                        @endforeach
                    @endif
                </div>
            </header>
        @endif

        @if ($doc->patient)
            <div class="patient">
                @foreach ($doc->patient as $row)
                    <span>{{ $row['label'] }}: <b>{{ $row['value'] }}</b></span>
                @endforeach
            </div>
        @endif

        <div class="meta">
            <span>@if ($doc->prescriptionNo){{ $doc->words['rx_no'] }}: {{ $doc->prescriptionNo }}@endif</span>
            <span>{{ $doc->date }}</span>
        </div>

        @foreach ($doc->zones['header'] as $section)
            @include('print.section', ['section' => $section])
        @endforeach

        <div @class(['body', 'two' => $doc->twoColumns && $doc->zones['left'] !== []])>
            @if ($doc->zones['left'] !== [])
                <div class="left">
                    @foreach ($doc->zones['left'] as $section)
                        @include('print.section', ['section' => $section])
                    @endforeach
                </div>
            @endif
            <div class="right">
                @foreach ($doc->zones['right'] as $section)
                    @include('print.section', ['section' => $section])
                @endforeach
            </div>
        </div>

        <footer class="footer">
            @foreach ($doc->zones['footer'] as $section)
                @include('print.section', ['section' => $section])
            @endforeach
            @if ($doc->showSignature)
                <div class="signature"><span>{{ $doc->words['signature'] }}</span></div>
            @endif
            @if ($letterhead && $doc->showBranchFooter && $doc->chamber['branches'])
                <div class="branches small">{{ implode(' · ', $doc->chamber['branches']) }}</div>
            @endif
        </footer>
    </main>
</body>
</html>
