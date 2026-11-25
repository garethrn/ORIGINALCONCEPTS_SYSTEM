@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Bank feeds by email</h1>
    <form method="POST" action="{{ route('bank-feeds.update') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-2">
        @csrf @method('PUT')
        <label class="text-sm">IMAP host<input class="field" name="imap_host" value="{{ old('imap_host', $mailbox->imap_host) }}" placeholder="imap.example.co.za"></label>
        <label class="text-sm">Port<input class="field" name="imap_port" value="{{ old('imap_port', $mailbox->imap_port ?: 993) }}" required></label>
        <label class="text-sm">Encryption
            <select class="field" name="imap_encryption">
                @foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'None'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('imap_encryption', $mailbox->imap_encryption ?: 'ssl') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Folder<input class="field" name="imap_folder" value="{{ old('imap_folder', $mailbox->imap_folder ?: 'INBOX') }}" required></label>
        <label class="text-sm">Username<input class="field" name="imap_username" value="{{ old('imap_username', $mailbox->imap_username) }}"></label>
        <label class="text-sm">Password<input class="field" type="password" name="imap_password" placeholder="{{ $mailbox->exists && $mailbox->imap_password ? 'Saved encrypted. Leave blank to keep it.' : 'Mailbox password' }}" autocomplete="new-password"></label>
        <label class="text-sm md:col-span-2">Forwarding address<input class="field" type="email" name="forwarding_address" value="{{ old('forwarding_address', $mailbox->forwarding_address) }}" placeholder="bank-alerts@originalconcepts.co.za"></label>
        <label class="text-sm md:col-span-2">Allowed sender addresses, one per line. Bank domains for FNB, Standard Bank, ABSA, Nedbank, Capitec and Investec are already accepted.
            <textarea class="field" name="allowed_senders" rows="4">{{ old('allowed_senders', $mailbox->allowed_senders) }}</textarea>
        </label>
        <label class="text-sm">Auto-allocate at or above
            <input class="field" type="number" min="50" max="100" name="confidence_threshold" value="{{ old('confidence_threshold', $mailbox->confidence_threshold ?: 90) }}" required>
        </label>
        <div class="flex flex-col justify-end gap-2">
            <input type="hidden" name="auto_allocate" value="0">
            <label class="text-sm"><input type="checkbox" name="auto_allocate" value="1" @checked(old('auto_allocate', $mailbox->auto_allocate ?? true))> Post a customer payment when the match is confident</label>
            <input type="hidden" name="active" value="0">
            <label class="text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $mailbox->active ?? true))> Check this mailbox every five minutes</label>
        </div>
        <div class="md:col-span-2"><button class="btn btn-primary" type="submit">Save bank feed</button></div>
    </form>
    <p class="mt-4 text-sm text-slate-500">Point each bank's transaction alerts at the mailbox above, or forward them from your own address after you add that address to the allowed senders. The account's last four digits, set on the bank account, decide which account receives the transaction.</p>
@endsection
