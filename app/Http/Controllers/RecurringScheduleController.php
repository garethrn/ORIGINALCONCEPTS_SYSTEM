<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\RunRecurringSchedules;
use App\Enums\RecurringFrequency;
use App\Enums\VatCode;
use App\Models\Client;
use App\Models\RecurringSchedule;
use App\Services\Billing\DocumentLineWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecurringScheduleController extends Controller
{
    public function index(): View
    {
        $this->guard();
        $schedules = RecurringSchedule::query()->with('client')->orderBy('name')->paginate(20);

        return view('recurring.index', compact('schedules'));
    }

    public function create(): View
    {
        $this->guard();

        return view('recurring.form', [
            'schedule' => new RecurringSchedule(['frequency' => RecurringFrequency::Monthly, 'next_on' => today(), 'active' => true]),
            'clients' => $this->clients(),
            'lines' => [['description' => '', 'quantity' => '1', 'unit_price' => '0.00', 'includes_vat' => false, 'vat_code' => 'standard']],
        ]);
    }

    public function store(Request $request, DocumentLineWriter $writer): RedirectResponse
    {
        $this->guard();
        $schedule = new RecurringSchedule(['created_by' => $request->user()->id]);
        $this->save($request, $schedule, $writer);

        return redirect()->route('recurring.show', $schedule)->with('status', 'Recurring invoice saved.');
    }

    public function show(RecurringSchedule $schedule): View
    {
        $this->guard();
        $schedule->load('client', 'lines');

        return view('recurring.show', compact('schedule'));
    }

    public function edit(RecurringSchedule $schedule): View
    {
        $this->guard();
        $schedule->load('lines');

        return view('recurring.form', [
            'schedule' => $schedule,
            'clients' => $this->clients(),
            'lines' => $schedule->lines->map(fn ($line) => [
                'description' => $line->description,
                'quantity' => rtrim(rtrim((string) $line->quantity, '0'), '.') ?: '0',
                'unit_price' => $line->unitPriceRands(),
                'includes_vat' => (bool) $line->price_includes_vat,
                'vat_code' => $line->vat_code->value,
                'product_id' => $line->product_id,
            ])->all(),
        ]);
    }

    public function update(Request $request, RecurringSchedule $schedule, DocumentLineWriter $writer): RedirectResponse
    {
        $this->guard();
        $this->save($request, $schedule, $writer);

        return redirect()->route('recurring.show', $schedule)->with('status', 'Recurring invoice updated.');
    }

    public function run(RecurringSchedule $schedule, RunRecurringSchedules $runner): RedirectResponse
    {
        $this->guard();
        $created = $runner->run($schedule);

        return back()->with('status', $created === 0 ? 'Nothing was due.' : $created.' invoice'.($created === 1 ? '' : 's').' created.');
    }

    public function destroy(RecurringSchedule $schedule): RedirectResponse
    {
        $this->guard();
        $schedule->delete();

        return redirect()->route('recurring.index')->with('status', 'Recurring invoice removed.');
    }

    private function guard(): void
    {
        abort_unless(request()->user()?->role->canBill(), 403);
    }

    private function clients()
    {
        return Client::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function save(Request $request, RecurringSchedule $schedule, DocumentLineWriter $writer): void
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:150'],
            'frequency' => ['required', Rule::enum(RecurringFrequency::class)],
            'next_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date'],
            'auto_issue' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,3})?$/'],
            'lines.*.unit_price' => ['required', 'regex:/^-?\d+(\.\d{1,2})?$/'],
            'lines.*.includes_vat' => ['nullable', 'boolean'],
            'lines.*.vat_code' => ['required', Rule::enum(VatCode::class)],
            'lines.*.product_id' => ['nullable', 'integer'],
        ]);
        foreach ($data['lines'] as $index => $line) {
            $data['lines'][$index]['includes_vat'] = $request->boolean('lines.'.$index.'.includes_vat');
        }
        $built = $writer->build($data['lines']);
        $schedule->fill([
            'client_id' => $data['client_id'],
            'name' => $data['name'],
            'frequency' => $data['frequency'],
            'next_on' => $data['next_on'],
            'ends_on' => ($data['ends_on'] ?? null) ?: null,
            'auto_issue' => $request->boolean('auto_issue'),
            'active' => $request->boolean('active'),
            'notes' => $data['notes'] ?? null,
        ]);
        if (! $schedule->exists) {
            $schedule->created_by = $request->user()->id;
        }
        $schedule->save();
        $schedule->lines()->delete();
        foreach ($built['lines'] as $line) {
            unset($line['source_id']);
            $schedule->lines()->create($line);
        }
    }
}
