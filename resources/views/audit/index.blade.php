@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Audit log</h1>
    <div class="space-y-3">
        @foreach ($logs as $log)
            <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
                <div class="text-xs text-slate-500">{{ $log->created_at->format('d M Y H:i') }} · {{ $log->user?->name ?: 'System' }} · {{ $log->ip_address }}</div>
                <div class="font-semibold">{{ $log->action }}</div>
                @if ($log->before)<pre class="mt-2 overflow-x-auto text-xs">{{ json_encode($log->before, JSON_PRETTY_PRINT) }}</pre>@endif
                @if ($log->after)<pre class="mt-2 overflow-x-auto text-xs">{{ json_encode($log->after, JSON_PRETTY_PRINT) }}</pre>@endif
            </article>
        @endforeach
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
