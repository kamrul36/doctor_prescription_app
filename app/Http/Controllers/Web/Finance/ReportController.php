<?php

namespace App\Http\Controllers\Web\Finance;

use App\Domain\Finance\Money;
use App\Domain\Finance\Reports\Report;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ReportRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The finance reports (spec §8.4) as pages, with a CSV download (`?format=csv`). PDF: the browser's Print. */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(Report::anyVisibleTo($user), 403);

        return view('reports.index', ['reports' => Report::visibleTo($user)]);
    }

    public function show(ReportRequest $request, Report $report): View|StreamedResponse
    {
        Gate::authorize('viewReport', $report);

        $data = $request->run($report);

        if ($request->validated('format') === 'csv') {
            return $this->csv($report, $data);
        }

        return view('reports.'.$report->value, ['data' => $data, 'report' => $report]);
    }

    /** @param  array<string, mixed>  $data */
    private function csv(Report $report, array $data): StreamedResponse
    {
        $money = fn (?Money $m) => $m?->toDecimal() ?? '';

        $rows = match ($report) {
            Report::DailyCollection => [
                [__('Receipt no.'), __('Invoice no.'), __('Patient'), __('ID'), __('Payment method'), __('Amount'), __('Time'), __('Received by')],
                ...array_map(fn (array $p) => [$p['receipt_no'], $p['invoice_no'], $p['patient'], $p['patient_code'], $p['method']->label(), $money($p['amount']), $p['received_at'], (string) $p['received_by']], $data['payments']),
                [],
                [__('Collected'), $money($data['collected'])],
                [__('Invoiced'), $money($data['invoiced'])],
                [__('Patients'), (string) $data['patient_count']],
            ],
            Report::MonthlySummary => [
                [__('Date'), __('Income'), __('Expenses')],
                ...array_map(fn (array $d) => [$d['date'], $money($d['income']), $money($d['expenses'])], $data['daily']),
                [],
                [__('Income'), $money($data['income'])],
                [__('Expenses'), $money($data['expenses'])],
                [__('Net'), $money($data['net'])],
            ],
            Report::Dues => [
                [__('Patient'), __('ID'), __('Phone'), __('0–7 days'), __('8–30 days'), __('Over 30 days'), __('Due')],
                ...array_map(fn (array $p) => [$p['patient'], $p['patient_code'], (string) $p['phone'], $money($p['buckets']['0_7']), $money($p['buckets']['8_30']), $money($p['buckets']['30_plus']), $money($p['due'])], $data['patients']),
                [],
                [__('Total'), '', '', $money($data['buckets']['0_7']), $money($data['buckets']['8_30']), $money($data['buckets']['30_plus']), $money($data['total'])],
            ],
            Report::CashBook => [
                [__('Date'), __('Reference'), __('Item'), __('Payment method'), __('Money in'), __('Money out'), __('Balance')],
                ['', '', __('Opening balance'), '', '', '', $money($data['opening'])],
                ...array_map(fn (array $e) => [$e['date'], $e['reference'], $e['description'], $e['method']->label(), $money($e['in']), $money($e['out']), $money($e['balance'])], $data['entries']),
                ['', '', __('Closing balance'), '', $money($data['money_in']), $money($data['money_out']), $money($data['closing'])],
            ],
        };

        $name = $report->value.'-'.($data['date'] ?? $data['month'] ?? $data['as_of'] ?? ($data['from'].'_'.$data['to'])).'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Bangla correctly
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
