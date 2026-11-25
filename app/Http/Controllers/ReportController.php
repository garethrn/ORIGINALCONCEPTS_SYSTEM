<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\JournalLine;
use App\Services\Accounting\ReportCentre;
use App\Services\Billing\CompanyProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportCentre $reports): View
    {
        $this->guard();

        return view('reports.index', ['groups' => $reports->catalogue()]);
    }

    public function show(Request $request, string $report, ReportCentre $reports): View|Response|StreamedResponse
    {
        $this->guard();
        [$from, $to, $compare] = $this->range($request);
        $data = $reports->run($report, $from, $to, $compare);
        $data['from'] = $from->toDateString();
        $data['to'] = $to->toDateString();
        $data['compare'] = $compare;
        $data['key'] = $report;

        if ($request->query('export') === 'csv') {
            return $this->csv($data);
        }
        if ($request->query('export') === 'pdf') {
            $pdf = Pdf::loadView('pdf.report', $data + ['company' => app(CompanyProfile::class)])
                ->setPaper('a4', 'landscape')->setOption('isRemoteEnabled', false);

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$report.'.pdf"',
            ]);
        }

        return view('reports.show', $data);
    }

    public function drill(Request $request): View
    {
        $this->guard();
        $data = $request->validate([
            'account' => ['nullable', 'integer', 'exists:accounts,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $lines = JournalLine::query()->with('entry', 'account', 'client')
            ->when($data['account'] ?? null, fn ($query, $account) => $query->where('account_id', $account))
            ->whereHas('entry', function ($query) use ($data) {
                if (! empty($data['from'])) {
                    $query->whereDate('entry_date', '>=', $data['from']);
                }
                if (! empty($data['to'])) {
                    $query->whereDate('entry_date', '<=', $data['to']);
                }
            })
            ->limit(300)
            ->get()
            ->sortBy(fn (JournalLine $line) => $line->entry?->entry_date?->toDateString().$line->id);

        return view('reports.drill', ['lines' => $lines, 'from' => $data['from'] ?? '', 'to' => $data['to'] ?? '']);
    }

    private function guard(): void
    {
        abort_unless(request()->user()?->role->canBill(), 403);
    }

    /**
     * @return array{0: \Carbon\CarbonInterface, 1: \Carbon\CarbonInterface, 2: bool}
     */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'compare' => ['nullable', 'in:0,1'],
        ]);
        $from = \Carbon\Carbon::parse($data['from'] ?? today()->startOfMonth()->toDateString());
        $to = \Carbon\Carbon::parse($data['to'] ?? today()->toDateString());
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to, ($data['compare'] ?? '0') === '1'];
    }

    /**
     * @param  array{title: string, columns: list<string>, rows: list<array{cells: list<string>}>}  $data
     */
    private function csv(array $data): StreamedResponse
    {
        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $data['columns']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, $row['cells']);
            }
            fclose($out);
        }, $data['key'].'.csv', ['Content-Type' => 'text/csv']);
    }
}
