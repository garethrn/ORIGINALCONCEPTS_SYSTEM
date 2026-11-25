@extends('layouts.app')
@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <div class="text-sm text-slate-500">{{ $category }}</div>
            <h1 class="text-2xl font-semibold text-oc-navy">{{ $title }}</h1>
        </div>
        <div class="flex gap-2">
            <a class="btn btn-ghost" href="{{ route('reports.show', [$key, 'from' => $from, 'to' => $to, 'compare' => $compare ? 1 : 0, 'export' => 'csv']) }}">CSV</a>
            <a class="btn btn-ghost" href="{{ route('reports.show', [$key, 'from' => $from, 'to' => $to, 'compare' => $compare ? 1 : 0, 'export' => 'pdf']) }}">PDF</a>
        </div>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
        <label class="text-sm">From<input class="field" type="date" name="from" value="{{ $from }}"></label>
        <label class="text-sm">To<input class="field" type="date" name="to" value="{{ $to }}"></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="compare" value="1" @checked($compare)> Compare with previous period</label>
        <button class="btn btn-primary" type="submit">Update</button>
    </form>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr>@foreach ($columns as $column)<th class="px-4 py-2">{{ $column }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr class="border-t {{ $row['strong'] ? 'font-semibold' : '' }}">
                    @foreach ($row['cells'] as $index => $cell)
                        <td class="px-4 py-3">
                            @if ($index === 0 && $row['href'])<a href="{{ $row['href'] }}">{{ $cell }}</a>@else{{ $cell }}@endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="px-4 py-6 text-slate-500" colspan="{{ count($columns) }}">Nothing in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
