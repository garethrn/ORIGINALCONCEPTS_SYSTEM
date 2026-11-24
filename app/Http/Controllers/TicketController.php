<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\BillTicketWork;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\RecordPartUsed;
use App\Actions\Tickets\RecordTimeEntry;
use App\Actions\Tickets\StoreTicketAttachment;
use App\Actions\Tickets\TransitionTicket;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Enums\VatCode;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);
        $tickets = Ticket::query()
            ->visibleTo($request->user())
            ->with(['client', 'assignee'])
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('q')->toString(), function ($q, $term) {
                $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(subject) LIKE ?', [$like]);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('tickets.index', compact('tickets'));
    }

    public function create(): View
    {
        $this->authorize('create', Ticket::class);

        return view('tickets.form', [
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(),
            'staff' => User::query()->where('is_active', true)->where('role', '!=', Role::Client->value)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreTicketRequest $request, CreateTicket $action): RedirectResponse
    {
        $ticket = $action->handle($request->user(), $request->validated());

        return redirect()->route('tickets.show', $ticket)->with('status', 'Ticket '.$ticket->reference().' opened.');
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);
        $ticket->load([
            'client', 'site', 'contact', 'assignee', 'events.user', 'timeEntries.user', 'parts',
            'comments' => function ($query) {
                if (! request()->user()->isStaff()) {
                    $query->where('is_internal', false);
                }
                $query->with('user')->latest();
            },
            'attachments.comment',
        ]);

        return view('tickets.show', [
            'ticket' => $ticket,
            'staff' => User::query()->where('is_active', true)->where('role', '!=', Role::Client->value)->orderBy('name')->get(),
            'statuses' => $ticket->status->allowedTransitions(),
        ]);
    }

    public function transition(Request $request, Ticket $ticket, TransitionTicket $action): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $data = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $action->handle(
            $request->user(),
            $ticket,
            TicketStatus::from($data['status']),
            array_key_exists('assigned_to', $data) && $data['assigned_to'] ? (int) $data['assigned_to'] : null,
        );

        return back()->with('status', 'Ticket updated.');
    }

    public function comment(Request $request, Ticket $ticket, AddTicketComment $action): RedirectResponse
    {
        $this->authorize('comment', $ticket);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);
        $action->handle($request->user(), $ticket, $data['body'], $request->boolean('is_internal'));

        return back()->with('status', 'Comment added.');
    }

    public function attachment(Request $request, Ticket $ticket, StoreTicketAttachment $action): RedirectResponse
    {
        $this->authorize('comment', $ticket);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,txt,doc,docx,xls,xlsx,zip'],
        ]);
        $mime = (string) $data['file']->getMimeType();
        if (in_array($mime, ['text/html', 'image/svg+xml', 'application/x-php', 'text/x-php'], true)) {
            return back()->withErrors(['file' => 'That file type is not allowed.']);
        }
        $action->handle($request->user(), $ticket, $data['file']);

        return back()->with('status', 'File attached.');
    }

    public function download(Request $request, Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $ticket);
        abort_unless($attachment->ticket_id === $ticket->id, 404);
        if ($attachment->comment?->is_internal && ! $request->user()->isStaff()) {
            abort(403);
        }

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function time(Request $request, Ticket $ticket, RecordTimeEntry $action): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'description' => ['required', 'string', 'max:255'],
            'hourly_rate' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'billable' => ['nullable', 'boolean'],
            'vat_code' => ['required', Rule::enum(VatCode::class)],
        ]);
        $data['billable'] = $request->boolean('billable');
        $action->handle($request->user(), $ticket, $data);

        return back()->with('status', 'Time recorded.');
    }

    public function part(Request $request, Ticket $ticket, RecordPartUsed $action): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'regex:/^\d+(\.\d{1,3})?$/'],
            'unit_price' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'includes_vat' => ['nullable', 'boolean'],
            'vat_code' => ['required', Rule::enum(VatCode::class)],
            'billable' => ['nullable', 'boolean'],
        ]);
        $data['billable'] = $request->boolean('billable');
        $data['includes_vat'] = $request->boolean('includes_vat');
        $action->handle($request->user(), $ticket, $data);

        return back()->with('status', 'Part recorded.');
    }

    public function bill(Ticket $ticket, BillTicketWork $action): RedirectResponse
    {
        $this->authorize('bill', $ticket);
        $invoice = $action->handle(request()->user(), $ticket);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice created from unbilled work.');
    }
}
