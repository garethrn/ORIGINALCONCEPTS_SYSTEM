<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesDocumentLines;
use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends FormRequest
{
    use ValidatesDocumentLines;

    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote
            ? ($this->user()?->can('update', $quote) ?? false)
            : ($this->user()?->can('create', Quote::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('is_active', true)],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('client_id', (int) $this->input('client_id'))],
            'ticket_id' => ['nullable', 'integer', Rule::exists('tickets', 'id')->where('client_id', (int) $this->input('client_id'))],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], $this->documentLineRules());
    }

    protected function prepareForValidation(): void
    {
        $this->prepareDocumentLines();
        foreach (['contact_id', 'ticket_id', 'valid_until'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }
}
