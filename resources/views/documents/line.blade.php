<div data-line class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-12">
    @if (! empty($line['id']))
        <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line['id'] }}">
    @endif
    <input class="field md:col-span-5" name="lines[{{ $index }}][description]" value="{{ $line['description'] ?? '' }}" placeholder="Description" required>
    <input class="field md:col-span-2" name="lines[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? '1' }}" inputmode="decimal" required>
    <input class="field md:col-span-2" name="lines[{{ $index }}][unit_price]" value="{{ $line['unit_price'] ?? '0.00' }}" inputmode="decimal" placeholder="Unit price, negative for a discount" required>
    <select class="field md:col-span-2" name="lines[{{ $index }}][vat_code]">
        @foreach (['standard' => 'Standard VAT', 'zero' => 'Zero-rated', 'exempt' => 'Exempt'] as $value => $label)
            <option value="{{ $value }}" @selected(($line['vat_code'] ?? 'standard') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <input type="hidden" name="lines[{{ $index }}][includes_vat]" value="0">
    <label class="flex items-center gap-2 text-sm md:col-span-12"><input type="checkbox" name="lines[{{ $index }}][includes_vat]" value="1" @checked($line['includes_vat'] ?? false)> Price includes VAT</label>
    <input type="hidden" name="lines[{{ $index }}][product_id]" value="{{ $line['product_id'] ?? '' }}">
    <button class="text-left text-sm text-red-700 md:col-span-12" type="button" data-remove-line>Remove line</button>
</div>
