{{--
    A5 money receipt for the browser's Print / Save as PDF (spec §8.2). Everything shown comes from
    ReceiptDocument, already in the print language. Plain CSS (no Vite), like the prescription print.
--}}
@use('App\Domain\Document\Support\PrintLocale')
@php($o = $doc->options)
<!DOCTYPE html>
<html lang="{{ $o->locale === PrintLocale::Bn ? 'bn' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $doc->receiptNo }} · {{ config('app.name') }}</title>
    @include('partials.fonts')
    <style>
        :root { --ink: #111827; --muted: #4b5563; --line: #d1d5db; --accent: #115e59; --paper: #ffffff; --desk: #e5e7eb; }
        @page { size: A5; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            background: var(--desk); color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', 'Noto Serif Bengali', Roboto, 'Noto Sans', sans-serif;
            font-size: 10pt; line-height: 1.4;
        }
        .sheet {
            position: relative; width: 148mm; min-height: 210mm; margin: 0 auto 24px; padding: 10mm;
            background: var(--paper); box-shadow: 0 2px 12px rgb(0 0 0 / .12); display: flex; flex-direction: column;
        }
        .head { text-align: center; padding-bottom: 3mm; border-bottom: 2px solid var(--accent); }
        .head .name { font-size: 13pt; font-weight: 700; color: var(--accent); }
        .small { font-size: 8.5pt; color: var(--muted); }
        h1 { margin: 3mm 0 1mm; text-align: center; font-size: 12pt; letter-spacing: .05em; text-transform: uppercase; }
        .row { display: flex; justify-content: space-between; gap: 4mm; font-size: 9pt; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: .6mm 3mm; margin: 2.5mm 0; font-size: 9pt; }
        dt { color: var(--muted); }
        dd { margin: 0; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 2mm; font-size: 9pt; }
        th, td { padding: 1.2mm 1mm; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        th { color: var(--muted); font-weight: 600; }
        td.amount, th.amount { text-align: right; white-space: nowrap; }
        td.code { font-family: ui-monospace, monospace; font-size: 8pt; color: var(--muted); }
        .totals { margin-left: auto; margin-top: 2mm; min-width: 60%; font-size: 9pt; }
        .totals div { display: flex; justify-content: space-between; gap: 6mm; padding: .6mm 0; }
        .totals .strong { font-weight: 700; }
        .stamp {
            position: absolute; top: 58mm; right: 12mm; padding: 1.5mm 4mm; border: 3px solid; border-radius: 3mm;
            font-size: 18pt; font-weight: 800; letter-spacing: .08em; transform: rotate(-12deg); opacity: .8;
        }
        .stamp.paid { color: #047857; }
        .stamp.partial { color: #b45309; }
        .stamp.due, .stamp.void { color: #b91c1c; }
        .footer { margin-top: auto; padding-top: 10mm; display: flex; justify-content: flex-end; }
        .footer span { min-width: 45mm; padding-top: 1mm; border-top: 1px solid var(--ink); text-align: center; font-size: 9pt; }
        .toolbar {
            position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
            padding: 10px 16px; margin-bottom: 16px; background: #fff; border-bottom: 1px solid var(--line); font-size: 14px;
        }
        .toolbar a, .toolbar button, .toolbar label.lang {
            font: inherit; border-radius: 6px; padding: 5px 10px; border: 1px solid var(--line); background: #fff; color: var(--ink); text-decoration: none; cursor: pointer;
        }
        .toolbar .group { display: inline-flex; }
        .toolbar .group label.lang { border-radius: 0; margin-left: -1px; }
        .toolbar .group label.lang:first-child { border-radius: 6px 0 0 6px; margin-left: 0; }
        .toolbar .group label.lang:last-child { border-radius: 0 6px 6px 0; }
        .toolbar input[type=radio] { position: absolute; opacity: 0; }
        .toolbar label.lang:has(input:checked) { background: var(--accent); border-color: var(--accent); color: #fff; }
        .toolbar .print { margin-left: auto; background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; }
        @media screen and (max-width: 600px) { .sheet { width: 100%; min-height: auto; } }
        @media print {
            body { background: none; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: 210mm; }
        }
    </style>
</head>
<body>
    @if ($toolbar)
        <form method="GET" class="toolbar" lang="{{ app()->getLocale() }}">
            <a href="{{ route('invoices.show', $payment->invoice_id) }}">← {{ __('Back') }}</a>
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
            <noscript><button type="submit">{{ __('Apply') }}</button></noscript>
            <button type="button" class="print" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
        </form>
    @endif

    <main class="sheet">
        <header class="head">
            @foreach ($doc->clinic['name'] as $line)
                <div class="name">{{ $line }}</div>
            @endforeach
            @foreach ($doc->clinic['address'] as $line)
                <div class="small">{{ $line }}</div>
            @endforeach
            @if ($doc->clinic['phone'])
                <div class="small">{{ $doc->clinic['phone'] }}</div>
            @endif
        </header>

        <h1>{{ $doc->words['title'] }}</h1>
        <div class="row">
            <span>{{ $doc->words['receipt_no'] }}: <b>{{ $doc->receiptNo }}</b></span>
            <span>{{ $doc->words['date'] }}: {{ $doc->date }}</span>
        </div>

        <div class="stamp {{ $doc->stamp['tone'] }}" aria-label="{{ $doc->stamp['label'] }}">{{ $doc->stamp['label'] }}</div>

        <dl>
            @foreach ([...$doc->patient, ...$doc->meta] as $row)
                <dt>{{ $row['label'] }}</dt><dd>{{ $row['value'] }}</dd>
            @endforeach
        </dl>

        <table>
            <thead>
                <tr><th>{{ $doc->words['code'] }}</th><th>{{ $doc->words['item'] }}</th><th class="amount">{{ $doc->words['amount'] }}</th></tr>
            </thead>
            <tbody>
                @foreach ($doc->lines as $line)
                    <tr><td class="code">{{ $line['code'] }}</td><td>{{ $line['description'] }}</td><td class="amount">{{ $line['amount'] }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            @foreach ($doc->totals as $row)
                <div @class(['strong' => $row['strong'] ?? false])><span>{{ $row['label'] }}</span><span>{{ $row['value'] }}</span></div>
            @endforeach
        </div>

        <footer class="footer"><span>{{ $doc->words['signature'] }}</span></footer>
    </main>
</body>
</html>
