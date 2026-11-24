@if (session('status'))
    <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-950">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
