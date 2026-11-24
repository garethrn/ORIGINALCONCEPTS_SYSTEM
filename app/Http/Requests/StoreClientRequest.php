<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Client;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client
            ? ($this->user()?->can('update', $client) ?? false)
            : ($this->user()?->can('create', Client::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'trading_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'registration_number' => ['nullable', 'string', 'max:40'],
            'payment_terms_days' => ['required', 'integer', 'in:0,7,14,30,60'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
            'labour_rate' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'call_out_fee' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'travel_rate' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clientAttributes(): array
    {
        $data = $this->validated();
        foreach ([
            'labour_rate' => 'labour_rate_cents',
            'call_out_fee' => 'call_out_fee_cents',
            'travel_rate' => 'travel_rate_cents',
        ] as $input => $column) {
            if (! array_key_exists($input, $data)) {
                continue;
            }
            $raw = trim((string) ($data[$input] ?? ''));
            $data[$column] = $raw === '' ? null : Money::randsToCents($raw);
            unset($data[$input]);
        }

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
            'country' => $this->input('country') ?: 'South Africa',
        ]);
    }
}
