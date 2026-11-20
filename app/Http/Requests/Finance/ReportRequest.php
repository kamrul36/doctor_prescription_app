<?php

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Reports\CashBookReport;
use App\Domain\Finance\Reports\DailyCollectionReport;
use App\Domain\Finance\Reports\DuesReport;
use App\Domain\Finance\Reports\MonthlySummaryReport;
use App\Domain\Finance\Reports\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Report periods: `date` (daily collection, dues "as of"), `month` (`YYYY-MM`,
 * monthly summary), `from` / `to` (cash book, at most 366 days). Missing
 * values mean today / this month. Authorization: the controller.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** `YYYY-MM-DD`; default today. */
            'date' => ['nullable', 'date_format:Y-m-d', 'before:'.now()->addDay()->toDateString()],
            /** `YYYY-MM`; default this month. */
            'month' => ['nullable', 'date_format:Y-m'],
            /** `YYYY-MM-DD`; default the first of this month. */
            'from' => ['nullable', 'date_format:Y-m-d'],
            /** `YYYY-MM-DD`; default today. */
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            /** `csv` to download (web). */
            'format' => ['nullable', 'in:csv'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date.before' => __('The date cannot be in the future.'),
            'to.after_or_equal' => __('The end date must be on or after the start date.'),
        ];
    }

    /**
     * Runs the report for the requested period (web page, CSV and API share this).
     *
     * @return array<string, mixed>
     */
    public function run(Report $report): array
    {
        return match ($report) {
            Report::DailyCollection => DailyCollectionReport::run($this->day()),
            Report::MonthlySummary => MonthlySummaryReport::run(...$this->month()),
            Report::Dues => DuesReport::run($this->day()),
            Report::CashBook => CashBookReport::run(...$this->period()),
        };
    }

    public function day(): Carbon
    {
        return $this->filled('date') ? Carbon::parse((string) $this->validated('date')) : now()->startOfDay();
    }

    /** @return array{int, int} */
    public function month(): array
    {
        $month = $this->filled('month') ? Carbon::createFromFormat('!Y-m', (string) $this->validated('month'))->startOfMonth() : now()->startOfMonth();

        return [$month->year, $month->month];
    }

    /** @return array{Carbon, Carbon} */
    public function period(): array
    {
        $from = $this->filled('from') ? Carbon::parse((string) $this->validated('from')) : now()->startOfMonth();
        $to = $this->filled('to') ? Carbon::parse((string) $this->validated('to')) : now()->startOfDay();

        // One year at most, so a careless request cannot load the whole history.
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }

        return [$from, $to];
    }
}
