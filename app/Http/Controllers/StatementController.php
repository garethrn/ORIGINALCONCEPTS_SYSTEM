<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\Billing\CompanyProfile;
use App\Services\Billing\CustomerStatement;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatementController extends Controller
{
    public function show(Request $request, Client $client, CustomerStatement $statements): View
    {
        $this->authorize('view', $client);
        [$from, $to, $statement] = $this->prepare($request, $client, $statements);

        return view('statements.show', compact('client', 'from', 'to', 'statement'));
    }

    public function pdf(Request $request, Client $client, CustomerStatement $statements): Response
    {
        $this->authorize('view', $client);
        [$from, $to, $statement] = $this->prepare($request, $client, $statements);
        $pdf = Pdf::loadView('pdf.statement', [
            'client' => $client,
            'from' => $from,
            'to' => $to,
            'statement' => $statement,
            'company' => app(CompanyProfile::class),
        ])->setPaper('a4')->setOption('isRemoteEnabled', false);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="statement-'.$client->id.'.pdf"',
        ]);
    }

    public function csv(Request $request, Client $client, CustomerStatement $statements): StreamedResponse
    {
        $this->authorize('view', $client);
        [$from, $to, $statement] = $this->prepare($request, $client, $statements);

        return response()->streamDownload(function () use ($statement) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Type', 'Number', 'Detail', 'Debit', 'Credit', 'Balance']);
            fputcsv($out, ['', 'Opening balance', '', '', '', '', Money::centsToRands($statement['opening_cents'])]);
            foreach ($statement['rows'] as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['type'],
                    $row['number'],
                    $row['detail'],
                    $row['debit_cents'] ? Money::centsToRands($row['debit_cents']) : '',
                    $row['credit_cents'] ? Money::centsToRands($row['credit_cents']) : '',
                    Money::centsToRands($row['balance_cents']),
                ]);
            }
            fputcsv($out, ['', 'Closing balance', '', '', '', '', Money::centsToRands($statement['closing_cents'])]);
            fclose($out);
        }, 'statement-'.$client->id.'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function prepare(Request $request, Client $client, CustomerStatement $statements): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $from = $data['from'] ?? today()->startOfMonth()->toDateString();
        $to = $data['to'] ?? today()->toDateString();
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to, $statements->build($client, \Carbon\Carbon::parse($from), \Carbon\Carbon::parse($to))];
    }
}
