<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesDocumentLines;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    use ValidatesDocumentLines;

    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            ? ($this->user()?->can('update', $invoice) ?? false)
            : ($this->user()?->can('create', Invoice::class) ?? false);
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
            'notes' => ['nullable', 'string', 'max:2000'],
        ], $this->documentLineRules());
    }

    protected function prepareForValidation(): void
    {
        $this->prepareDocumentLines();
        foreach (['contact_id', 'ticket_id'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }
}
