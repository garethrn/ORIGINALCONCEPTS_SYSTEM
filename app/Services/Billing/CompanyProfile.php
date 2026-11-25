<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Setting;
use App\Services\Audit\AuditLogger;

final class CompanyProfile
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $merged = config('support.company');
        foreach (Setting::query()->pluck('value', 'key') as $key => $value) {
            $merged[$key] = (string) $value;
        }

        return $merged;
    }

    public function get(string $key, string $default = ''): string
    {
        return (string) ($this->all()[$key] ?? $default);
    }

    public function hasVatNumber(): bool
    {
        return trim($this->get('vat_number')) !== '';
    }

    /**
     * @return list<string>
     */
    public function addressLines(): array
    {
        $profile = $this->all();

        return array_values(array_filter([
            $profile['address_line1'] ?? '',
            $profile['address_line2'] ?? '',
            trim(implode(' ', array_filter([
                $profile['city'] ?? '',
                $profile['province'] ?? '',
                $profile['postal_code'] ?? '',
            ]))),
            $profile['country'] ?? '',
        ], fn (string $line) => $line !== ''));
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function update(array $values): void
    {
        $before = [];
        $after = [];
        foreach ($values as $key => $value) {
            $before[$key] = Setting::query()->where('key', $key)->value('value');
            Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) ($value ?? '')]);
            $after[$key] = (string) ($value ?? '');
        }

        $this->audit->log('settings.updated', null, $before, $after);
    }
}
