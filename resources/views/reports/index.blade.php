@extends('layouts.app')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-oc-navy">Reports</h1>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($groups as $category => $reports)
            <section class="rounded-2xl bg-white p-4 shadow-sm">
                <h2 class="mb-2 font-semibold">{{ $category }}</h2>
                <ul class="space-y-1 text-sm">
                    @foreach ($reports as $report)
                        <li><a href="{{ route('reports.show', $report['key']) }}">{{ $report['title'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endsection
