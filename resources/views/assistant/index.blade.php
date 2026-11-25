@extends('layouts.app')
@section('content')
    <h1 class="mb-2 text-2xl font-semibold text-oc-navy">Assistant</h1>
    <p class="mb-4 text-sm text-slate-600">Ask in plain language. The assistant only prepares a draft. Issuing an invoice or sending email needs the confirm box below.</p>
    @unless ($enabled)
        <div class="rounded-2xl bg-amber-50 p-4 text-sm">The assistant is switched off until an AI API key is configured.</div>
    @else
        <div class="mb-4 space-y-2">
            @foreach ($transcript as $entry)
                <div class="rounded-2xl bg-white p-3 text-sm shadow-sm"><span class="text-xs uppercase text-slate-500">{{ $entry['role'] }}</span><div class="whitespace-pre-wrap">{{ $entry['text'] }}</div></div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('assistant.store') }}" class="space-y-2">
            @csrf
            <textarea class="field" name="message" rows="3" maxlength="2000" required placeholder="Invoice Acme for 3 hours IT support at R650 and a 4MP IP camera"></textarea>
            <button class="btn btn-primary" type="submit">Draft</button>
        </form>
        @if ($draft && $draft->status === 'pending')
            <section class="mt-4 rounded-2xl border-2 border-oc-gold bg-white p-4 shadow-sm">
                <h2 class="font-semibold">Preview — nothing has been issued or emailed</h2>
                <p class="text-sm">{{ $draft->preview['customer'] ?? '' }}</p>
                @if (! empty($draft->preview['subject']))
                    <p class="mt-2">{{ $draft->preview['subject'] }} · {{ $draft->preview['priority'] ?? '' }} · {{ $draft->preview['category'] ?? '' }}</p>
                    <p class="whitespace-pre-wrap text-sm">{{ $draft->preview['description'] ?? '' }}</p>
                @endif
                @if (! empty($draft->preview['lines']))
                    <ul class="mt-2 text-sm">@foreach ($draft->preview['lines'] as $line)<li>{{ $line['description'] }} · {{ $line['quantity'] }} · {{ $line['total'] }} (VAT {{ $line['vat'] }})</li>@endforeach</ul>
                    <p class="mt-2 font-semibold">Total {{ $draft->preview['total'] }} · VAT {{ $draft->preview['vat'] }}</p>
                @endif
                <form method="POST" action="{{ route('assistant.confirm', $draft) }}" class="mt-4 space-y-2">
                    @csrf
                    <label class="block text-sm"><input type="checkbox" name="confirm" value="1" required> I have checked this draft and I want to continue</label>
                    @if ($draft->type === 'ticket')
                        <button class="btn btn-primary" name="decision" value="create" type="submit">Create ticket</button>
                    @else
                        <div class="flex flex-wrap gap-2">
                            <button class="btn btn-ghost" name="decision" value="save" type="submit">Save draft only</button>
                            <button class="btn btn-primary" name="decision" value="issue" type="submit">Issue now</button>
                            <button class="btn btn-gold" name="decision" value="issue_and_email" type="submit">Issue and email PDF</button>
                        </div>
                    @endif
                </form>
                <form class="mt-2" method="POST" action="{{ route('assistant.cancel', $draft) }}">@csrf<button class="text-sm text-red-700" type="submit">Discard</button></form>
            </section>
        @endif
    @endunless
@endsection
