<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Site;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Client::class);
        $clients = Client::query()
            ->when($request->string('q')->toString(), function ($query, string $q) {
                $like = '%'.addcslashes(mb_strtolower($q), '%_\\').'%';
                $query->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(trading_name, "")) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(email, "")) LIKE ?', [$like]);
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        $this->authorize('create', Client::class);

        return view('clients.form', ['client' => new Client(['country' => 'South Africa', 'payment_terms_days' => 30, 'is_active' => true])]);
    }

    public function store(StoreClientRequest $request, AuditLogger $audit): RedirectResponse
    {
        $client = Client::query()->create($request->clientAttributes());
        $audit->log('client.created', $client, null, ['name' => $client->name]);

        return redirect()->route('clients.show', $client)->with('status', 'Customer saved.');
    }

    public function show(Client $client): View
    {
        $this->authorize('view', $client);
        $client->load(['contacts', 'sites', 'tickets' => fn ($q) => $q->latest()->limit(10), 'invoices' => fn ($q) => $q->latest()->limit(10)]);

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client): View
    {
        $this->authorize('update', $client);

        return view('clients.form', compact('client'));
    }

    public function update(StoreClientRequest $request, Client $client, AuditLogger $audit): RedirectResponse
    {
        $before = $client->only(['name', 'vat_number', 'payment_terms_days', 'is_active']);
        $client->update($request->clientAttributes());
        $audit->log('client.updated', $client, $before, $client->only(['name', 'vat_number', 'payment_terms_days', 'is_active']));

        return redirect()->route('clients.show', $client)->with('status', 'Customer updated.');
    }

    public function storeContact(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:80'],
            'is_primary' => ['nullable', 'boolean'],
        ]);
        $data['is_primary'] = $request->boolean('is_primary');
        $client->contacts()->create($data);

        return back()->with('status', 'Contact added.');
    }

    public function destroyContact(Client $client, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $client);
        abort_unless($contact->client_id === $client->id, 404);
        $contact->delete();

        return back()->with('status', 'Contact removed.');
    }

    public function storeSite(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $client->sites()->create($data);

        return back()->with('status', 'Site added.');
    }

    public function destroySite(Client $client, Site $site): RedirectResponse
    {
        $this->authorize('update', $client);
        abort_unless($site->client_id === $client->id, 404);
        $site->delete();

        return back()->with('status', 'Site removed.');
    }
}
