<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Role;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ticket::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();
        if ($user?->role === Role::Client) {
            $this->merge([
                'client_id' => $user->client_id,
                'assigned_to' => null,
            ]);
        }
        foreach (['site_id', 'contact_id', 'assigned_to', 'location'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('is_active', true)],
            'site_id' => ['nullable', 'integer', Rule::exists('sites', 'id')->where('client_id', (int) $this->input('client_id'))],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('client_id', (int) $this->input('client_id'))],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', '!=', Role::Client->value)->where('is_active', true))],
        ];
    }
}
