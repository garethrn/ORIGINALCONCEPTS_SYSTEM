<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\VatCode;
use App\Models\User;
use Illuminate\Validation\Rule;

trait ValidatesDocumentLines
{
    /**
     * @return array<string, mixed>
     */
    protected function documentLineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,3})?$/', function (string $attribute, mixed $value, \Closure $fail): void {
                if (bccomp((string) $value, '0', 3) !== 1) {
                    $fail('Quantity must be greater than zero.');
                }
            }],
            'lines.*.unit_price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'lines.*.includes_vat' => ['required', 'boolean'],
            'lines.*.vat_code' => ['required', Rule::enum(VatCode::class)],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
        ];
    }

    protected function prepareDocumentLines(): void
    {
        $lines = $this->input('lines', []);
        if (! is_array($lines)) {
            return;
        }

        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                continue;
            }
            $lines[$index]['includes_vat'] = filter_var($line['includes_vat'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $lines[$index]['quantity'] = trim((string) ($line['quantity'] ?? ''));
            $lines[$index]['unit_price'] = trim((string) ($line['unit_price'] ?? ''));
            if (($line['product_id'] ?? '') === '') {
                $lines[$index]['product_id'] = null;
            }
        }

        $this->merge(['lines' => $lines]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function fromPayload(User $user, array $payload): array
    {
        /** @var static $request */
        $request = static::create('/internal/validate', 'POST', $payload);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);
        $request->validateResolved();

        return $request->validated();
    }
}
