<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesDocumentLines;
use App\Models\CreditNote;
use Illuminate\Foundation\Http\FormRequest;

class StoreCreditNoteRequest extends FormRequest
{
    use ValidatesDocumentLines;

    public function authorize(): bool
    {
        return $this->user()?->can('create', CreditNote::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'notes' => ['nullable', 'string', 'max:2000'],
        ], $this->documentLineRules());
    }

    protected function prepareForValidation(): void
    {
        $this->prepareDocumentLines();
    }
}
